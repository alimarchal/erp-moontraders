<?php

namespace App\Services;

use App\Exceptions\PayrollImportException;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryComponent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Excel templates and imports for payroll, keyed by employee code:
 *  - the salary set-up (salary type, pay method, basic, and each standing earning or deduction with its calculation);
 *  - incentives & adjustments (category, amount, one time or every month, narration).
 * Templates carry drop-down lists so they import cleanly. An import is all or nothing: every row is checked first and
 * nothing is saved while any row has a mistake.
 */
class PayrollExcelService
{
    public const SHEET_SALARIES = 'Salaries';

    public const SHEET_COMPONENTS = 'Earnings & Deductions';

    public const SHEET_ADJUSTMENTS = 'Incentives & Adjustments';

    public const SHEET_LISTS = 'Lists';

    /** Rows of the template that carry drop-down lists. */
    private const LIST_ROWS = 2000;

    /** @var array<string, string> */
    public const CALCULATION_NAMES = [
        SalaryComponent::CALC_FIXED => 'Fixed amount',
        SalaryComponent::CALC_PERCENT => '% of basic',
        SalaryComponent::CALC_PERCENT_COMPONENT => '% of another allowance',
    ];

    public const REPEAT_ONCE = 'One time';

    public const REPEAT_MONTHLY = 'Every month';

    public function __construct(private PayrollService $payroll) {}

    /**
     * Salary set-up template, filled with every active employee the user can see and what they have today, so it can be
     * edited and uploaded back.
     */
    public function salaryTemplate(User $user): Spreadsheet
    {
        $employees = $this->employees($user)->load(['salary', 'salaryComponents.component', 'salaryComponents.baseComponent']);
        $standing = $this->standingComponents();
        $book = new Spreadsheet;

        $salaries = $book->getActiveSheet()->setTitle(self::SHEET_SALARIES);
        $this->header($salaries, ['Employee Code', 'Employee Name', 'Designation', 'Salary Type', 'Pay By', 'Basic Salary', 'Notes']);
        $row = 2;
        foreach ($employees as $employee) {
            $salaries->fromArray([
                (string) $employee->employee_code, $employee->name, $employee->designation,
                $employee->salary ? ($employee->salary->salary_type === EmployeeSalary::TYPE_FIXED ? 'Fixed' : 'Basic') : 'Basic',
                $employee->salary ? ucfirst($employee->salary->pay_method) : 'Cash',
                $employee->salary ? (float) $employee->salary->basic_salary : null,
                $employee->salary?->notes,
            ], null, 'A'.$row++, true);
        }
        $salaries->getStyle('A2:A'.self::LIST_ROWS)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $salaries->getStyle('F2:F'.self::LIST_ROWS)->getNumberFormat()->setFormatCode('#,##0.00');
        $this->dropDown($salaries, 'D2:D'.self::LIST_ROWS, '"Basic,Fixed"', 'Basic: absent days cut the pay. Fixed: never cut.');
        $this->dropDown($salaries, 'E2:E'.self::LIST_ROWS, '"Cash,Bank"', 'How the salary is paid.');

        $lines = $book->createSheet()->setTitle(self::SHEET_COMPONENTS);
        $this->header($lines, ['Employee Code', 'Employee Name', 'Category', 'Kind', 'Calculation', 'Value', '% Of']);
        $row = 2;
        foreach ($employees as $employee) {
            foreach ($employee->salaryComponents->filter(fn ($line) => $line->component && $line->component->is_active && $line->component->isStanding()) as $line) {
                $lines->fromArray([
                    (string) $employee->employee_code, $employee->name, $line->component->name, null,
                    self::CALCULATION_NAMES[$line->effectiveCalculation()] ?? 'Fixed amount', (float) $line->value,
                    $line->effectiveCalculation() === SalaryComponent::CALC_PERCENT_COMPONENT ? $line->baseComponent?->name : null,
                ], null, 'A'.$row++, true);
            }
        }
        $last = max($row - 1, 1);
        for ($r = 2; $r <= max($last, 200); $r++) {
            $lines->setCellValue('D'.$r, '=IF(C'.$r.'="","",IFERROR(VLOOKUP(C'.$r.',\''.self::SHEET_LISTS.'\'!$A:$B,2,FALSE),"Unknown"))');
        }
        $lines->getStyle('A2:A'.self::LIST_ROWS)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $count = max($standing->count(), 1) + 1;
        $this->dropDown($lines, 'C2:C'.self::LIST_ROWS, '\''.self::SHEET_LISTS.'\'!$A$2:$A$'.$count, 'An allowance (earning) or a standing deduction.');
        $this->dropDown($lines, 'E2:E'.self::LIST_ROWS, '"'.implode(',', self::CALCULATION_NAMES).'"', 'Fixed amount in Rs, or a percentage.');
        $this->dropDown($lines, 'G2:G'.self::LIST_ROWS, '\''.self::SHEET_LISTS.'\'!$A$2:$A$'.$count, 'Only for "% of another allowance": which allowance.');

        $lists = $book->createSheet()->setTitle(self::SHEET_LISTS);
        $this->header($lists, ['Category', 'Kind', 'Usual calculation']);
        $row = 2;
        foreach ($standing as $component) {
            $lists->fromArray([$component->name, $component->isEarning() ? 'Earning' : 'Deduction', self::CALCULATION_NAMES[$component->calculation] ?? 'Fixed amount'], null, 'A'.$row++, true);
        }

        $this->instructions($book, [
            'How to fill this template',
            '1. Sheet "Salaries": one row per employee. Keep the Employee Code; choose Salary Type and Pay By from the list; type the Basic Salary. A row with an empty Basic Salary is left as it is.',
            '2. Sheet "Earnings & Deductions": one row for each allowance or standing deduction of an employee. Choose the Category and the Calculation from the list and type the Value (Rs, or the percentage).',
            '3. For "% of another allowance", choose in "% Of" which allowance of the same employee it is a percentage of.',
            '4. A Value of 0 takes that category off the employee. Employees listed in "Earnings & Deductions" keep only the categories listed there when "Replace" is ticked on upload.',
            '5. The Kind column fills itself. Do not rename the sheets or the headings in row 1.',
            '6. If any row has a mistake nothing is saved; the screen lists each mistake with its row number.',
        ]);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * Incentives & adjustments template, one row per active employee for the given month; rows left without an amount
     * are skipped on import.
     */
    public function adjustmentTemplate(User $user, CarbonInterface $month): Spreadsheet
    {
        $employees = $this->employees($user);
        $components = $this->inputComponents();
        $book = new Spreadsheet;

        $sheet = $book->getActiveSheet()->setTitle(self::SHEET_ADJUSTMENTS);
        $this->header($sheet, ['Employee Code', 'Employee Name', 'Month', 'Category', 'Kind', 'Amount', 'Repeats', 'Last Month', 'Narration']);
        $sheet->getStyle('A2:A'.self::LIST_ROWS)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('C2:C'.self::LIST_ROWS)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('H2:H'.self::LIST_ROWS)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $row = 2;
        foreach ($employees as $employee) {
            $sheet->setCellValueExplicit('A'.$row, (string) $employee->employee_code, DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$row, $employee->name);
            $sheet->setCellValueExplicit('C'.$row, $month->format('Y-m'), DataType::TYPE_STRING);
            $sheet->setCellValue('E'.$row, '=IF(D'.$row.'="","",IFERROR(VLOOKUP(D'.$row.',\''.self::SHEET_LISTS.'\'!$A:$B,2,FALSE),"Unknown"))');
            $sheet->setCellValue('G'.$row, self::REPEAT_ONCE);
            $row++;
        }
        $sheet->getStyle('F2:F'.self::LIST_ROWS)->getNumberFormat()->setFormatCode('#,##0.00');

        $count = max($components->count(), 1) + 1;
        $this->dropDown($sheet, 'D2:D'.self::LIST_ROWS, '\''.self::SHEET_LISTS.'\'!$A$2:$A$'.$count, 'Incentive, commission, fine... (Lists sheet). New categories are added on the Categories tab.');
        $this->dropDown($sheet, 'G2:G'.self::LIST_ROWS, '"'.self::REPEAT_ONCE.','.self::REPEAT_MONTHLY.'"', 'One time: this month only. Every month: until the Last Month or until stopped.');

        $lists = $book->createSheet()->setTitle(self::SHEET_LISTS);
        $this->header($lists, ['Category', 'Kind']);
        $row = 2;
        foreach ($components as $component) {
            $lists->fromArray([$component->name, $component->isEarning() ? 'Earning' : 'Deduction'], null, 'A'.$row++, true);
        }

        $this->instructions($book, [
            'How to fill this template',
            '1. Each row is one incentive or adjustment for one employee. Keep the Employee Code; copy a row to give an employee more than one.',
            '2. Month is the month of the payslip, written as YYYY-MM (for example '.$month->format('Y-m').').',
            '3. Choose the Category from the list. Its Kind (Earning or Deduction) fills itself.',
            '4. Type the Amount in Rs. Rows without an Amount are skipped.',
            '5. Repeats: "One time" is paid in that month only; "Every month" is paid each month from Month until Last Month (optional, YYYY-MM) or until it is stopped.',
            '6. Narration is shown on the payslip next to the category, e.g. "October sales target achieved".',
            '7. If any row has a mistake nothing is saved; the screen lists each mistake with its row number.',
        ]);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * @return array{salaries: int, lines: int, employees: int}
     *
     * @throws PayrollImportException
     */
    public function importSalaries(string $path, User $user, bool $replace = true): array
    {
        $book = $this->load($path);
        $salaryRows = $this->rows($book, self::SHEET_SALARIES, false);
        $lineRows = $this->rows($book, self::SHEET_COMPONENTS, false);

        if ($salaryRows === null && $lineRows === null) {
            throw new PayrollImportException(['The file has neither a "'.self::SHEET_SALARIES.'" nor an "'.self::SHEET_COMPONENTS.'" sheet. Download the template and fill it in.']);
        }

        $employees = $this->employees($user)->load(['salary', 'salaryComponents'])->keyBy(fn (Employee $e) => mb_strtolower(trim((string) $e->employee_code)));
        $standing = $this->standingComponents();
        $byName = $standing->keyBy(fn (SalaryComponent $c) => mb_strtolower(trim($c->name)));
        $byCode = $standing->keyBy(fn (SalaryComponent $c) => mb_strtolower(trim((string) $c->code)));
        $calculations = collect(self::CALCULATION_NAMES)->mapWithKeys(fn ($label, $key) => [mb_strtolower($label) => $key])
            ->merge(['fixed' => SalaryComponent::CALC_FIXED, 'percent_of_basic' => SalaryComponent::CALC_PERCENT, 'percent_of_component' => SalaryComponent::CALC_PERCENT_COMPONENT]);
        $errors = [];
        $salaries = [];
        $lines = [];

        foreach ($salaryRows ?? [] as $number => $row) {
            $where = self::SHEET_SALARIES.' row '.$number;
            $employee = $this->findEmployee($employees, $row['employee_code'] ?? '', $where, $errors);
            if (! $employee) {
                continue;
            }

            $basic = $this->number($row['basic_salary'] ?? null);
            if ($basic === false || ($basic !== null && $basic < 0)) {
                $errors[] = $where.': Basic Salary must be a number of 0 or more.';

                continue;
            }
            if ($basic === null && ! $employee->salary) {
                continue;
            }

            $type = mb_strtolower(trim((string) ($row['salary_type'] ?? '')));
            $pay = mb_strtolower(trim((string) ($row['pay_by'] ?? '')));
            if ($type !== '' && ! in_array($type, [EmployeeSalary::TYPE_BASIC, EmployeeSalary::TYPE_FIXED], true)) {
                $errors[] = $where.': Salary Type must be Basic or Fixed.';

                continue;
            }
            if ($pay !== '' && ! in_array($pay, ['cash', 'bank'], true)) {
                $errors[] = $where.': Pay By must be Cash or Bank.';

                continue;
            }
            if (isset($salaries[$employee->id])) {
                $errors[] = $where.': '.$employee->employee_code.' is listed twice.';

                continue;
            }

            $salaries[$employee->id] = [
                'salary_type' => $type ?: ($employee->salary?->salary_type ?? EmployeeSalary::TYPE_BASIC),
                'pay_method' => $pay ?: ($employee->salary?->pay_method ?? 'cash'),
                'basic_salary' => $basic ?? (float) $employee->salary->basic_salary,
                'notes' => array_key_exists('notes', $row) ? (trim((string) $row['notes']) ?: null) : $employee->salary?->notes,
            ];
        }

        foreach ($lineRows ?? [] as $number => $row) {
            $where = self::SHEET_COMPONENTS.' row '.$number;
            $employee = $this->findEmployee($employees, $row['employee_code'] ?? '', $where, $errors);
            if (! $employee) {
                continue;
            }

            $name = mb_strtolower(trim((string) ($row['category'] ?? '')));
            $component = $byName[$name] ?? $byCode[$name] ?? null;
            if (! $component) {
                $errors[] = $where.': Category "'.($row['category'] ?? '').'" is not an active allowance or standing deduction.';

                continue;
            }

            $value = $this->number($row['value'] ?? null);
            if ($value === null) {
                continue;
            }
            $calcText = mb_strtolower(trim((string) ($row['calculation'] ?? '')));
            $calculation = $calcText === '' ? ($component->isStanding() ? $component->calculation : SalaryComponent::CALC_FIXED) : ($calculations[$calcText] ?? null);
            if (! $calculation) {
                $errors[] = $where.': Calculation must be one of '.implode(', ', self::CALCULATION_NAMES).'.';

                continue;
            }
            if ($value === false || $value < 0 || ($calculation !== SalaryComponent::CALC_FIXED && $value > 1000)) {
                $errors[] = $where.': Value must be a number of 0 or more'.($calculation !== SalaryComponent::CALC_FIXED ? ' and a percentage of at most 1000' : '').'.';

                continue;
            }

            $base = null;
            if ($calculation === SalaryComponent::CALC_PERCENT_COMPONENT) {
                $baseName = mb_strtolower(trim((string) ($row['of'] ?? '')));
                $base = $byName[$baseName] ?? $byCode[$baseName] ?? null;
                if (! $base || ! $base->isEarning() || $base->id === $component->id) {
                    $errors[] = $where.': choose in "% Of" another allowance (earning) of this employee.';

                    continue;
                }
            }
            if (isset($lines[$employee->id][$component->id])) {
                $errors[] = $where.': '.$component->name.' is listed twice for '.$employee->employee_code.'.';

                continue;
            }

            $lines[$employee->id][$component->id] = ['row' => $where, 'salary_component_id' => $component->id, 'calculation' => $calculation, 'base_component_id' => $base?->id, 'value' => $value];
        }

        foreach ($lines as $set) {
            foreach ($set as $line) {
                if ($line['base_component_id'] && ($set[$line['base_component_id']]['value'] ?? 0) <= 0) {
                    $errors[] = $line['row'].': the allowance in "% Of" must also be listed for this employee with a value.';
                }
            }
        }

        if ($errors !== []) {
            throw new PayrollImportException($errors);
        }

        $count = 0;
        DB::transaction(function () use ($salaries, $lines, $employees, $replace, $user, &$count) {
            $byId = $employees->keyBy('id');

            foreach ($salaries as $employeeId => $salary) {
                EmployeeSalary::updateOrCreate(['employee_id' => $employeeId], $salary + ['updated_by' => $user->id]);
            }

            foreach ($lines as $employeeId => $set) {
                $employee = $byId[$employeeId];
                if ($replace) {
                    $employee->salaryComponents()->whereNotIn('salary_component_id', array_keys($set))->delete();
                }
                foreach ($set as $componentId => $line) {
                    if ($line['value'] > 0) {
                        $employee->salaryComponents()->updateOrCreate(['salary_component_id' => $componentId], collect($line)->except('row')->all());
                        $count++;
                    } else {
                        $employee->salaryComponents()->where('salary_component_id', $componentId)->delete();
                    }
                }
            }
        });

        return ['salaries' => count($salaries), 'lines' => $count, 'employees' => count(array_unique([...array_keys($salaries), ...array_keys($lines)]))];
    }

    /**
     * @return array{items: int, employees: int}
     *
     * @throws PayrollImportException
     */
    public function importAdjustments(string $path, User $user): array
    {
        $rows = $this->rows($this->load($path), self::SHEET_ADJUSTMENTS, true);
        $employees = $this->employees($user)->keyBy(fn (Employee $e) => mb_strtolower(trim((string) $e->employee_code)));
        $components = $this->inputComponents();
        $byName = $components->keyBy(fn (SalaryComponent $c) => mb_strtolower(trim($c->name)));
        $byCode = $components->keyBy(fn (SalaryComponent $c) => mb_strtolower(trim((string) $c->code)));
        $errors = [];
        $items = [];

        foreach ($rows as $number => $row) {
            $where = 'Row '.$number;
            $amount = $this->number($row['amount'] ?? null);
            if ($amount === null) {
                continue;
            }

            $employee = $this->findEmployee($employees, $row['employee_code'] ?? '', $where, $errors);
            if (! $employee) {
                continue;
            }
            if ($amount === false || $amount <= 0) {
                $errors[] = $where.': Amount must be a number more than 0.';

                continue;
            }

            $name = mb_strtolower(trim((string) ($row['category'] ?? '')));
            $component = $byName[$name] ?? $byCode[$name] ?? null;
            if (! $component) {
                $errors[] = $where.': Category "'.($row['category'] ?? '').'" is not an active incentive or adjustment category.';

                continue;
            }

            $month = $this->month($row['month'] ?? null);
            if (! $month) {
                $errors[] = $where.': Month must be written as YYYY-MM, for example '.now()->format('Y-m').'.';

                continue;
            }

            $repeat = mb_strtolower(trim((string) ($row['repeats'] ?? '')));
            $recurring = in_array($repeat, ['every month', 'monthly', 'recurring', 'yes'], true);
            if ($repeat !== '' && ! $recurring && ! in_array($repeat, ['one time', 'once', 'one-time', 'no'], true)) {
                $errors[] = $where.': Repeats must be "'.self::REPEAT_ONCE.'" or "'.self::REPEAT_MONTHLY.'".';

                continue;
            }

            $end = null;
            if ($recurring && trim((string) ($row['last_month'] ?? '')) !== '') {
                $end = $this->month($row['last_month']);
                if (! $end || $end->lessThan($month)) {
                    $errors[] = $where.': Last Month must be YYYY-MM and not before Month.';

                    continue;
                }
            }

            $items[] = ['row' => $where, 'employee' => $employee, 'component' => $component, 'month' => $month, 'amount' => $amount,
                'recurring' => $recurring, 'end' => $end, 'note' => mb_substr(trim((string) ($row['narration'] ?? '')), 0, 300) ?: null];
        }

        if ($errors === [] && $items === []) {
            $errors[] = 'No row has an Amount, so there is nothing to import.';
        }
        if ($errors !== []) {
            throw new PayrollImportException($errors);
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                try {
                    $this->payroll->addAdjustment($item['employee'], $item['component']->id, $item['month'], $item['amount'], $item['note'], $item['recurring'], $item['end']);
                } catch (RuntimeException $e) {
                    throw new PayrollImportException([$item['row'].': '.$e->getMessage()]);
                }
            }
        });

        return ['items' => count($items), 'employees' => collect($items)->pluck('employee.id')->unique()->count()];
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employees(User $user): Collection
    {
        return Employee::query()
            ->where('is_active', true)
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, SalaryComponent>
     */
    private function standingComponents(): Collection
    {
        return SalaryComponent::active()->where('calculation', '!=', SalaryComponent::CALC_INPUT)->orderBy('type')->get();
    }

    /**
     * @return Collection<int, SalaryComponent>
     */
    private function inputComponents(): Collection
    {
        return SalaryComponent::active()->where('calculation', SalaryComponent::CALC_INPUT)->orderBy('type')->get();
    }

    private function load(string $path): Spreadsheet
    {
        try {
            return IOFactory::load($path);
        } catch (Throwable) {
            throw new PayrollImportException(['The file could not be read. Upload the Excel template (.xlsx).']);
        }
    }

    /**
     * The rows of a sheet keyed by spreadsheet row number, each keyed by its heading in snake case. Fully empty rows are
     * left out. Null when the sheet is missing and not required.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function rows(Spreadsheet $book, string $title, bool $required): ?array
    {
        $sheet = $book->getSheetByName($title);

        if (! $sheet) {
            if ($required) {
                throw new PayrollImportException(['The file has no "'.$title.'" sheet. Download the template and fill it in.']);
            }

            return null;
        }

        $data = $sheet->toArray(null, true, false, false);
        $headings = array_map(fn ($heading) => trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(str_replace('%', '', (string) $heading))), '_'), $data[0] ?? []);
        $rows = [];

        foreach (array_slice($data, 1) as $index => $values) {
            $row = [];
            foreach ($headings as $column => $key) {
                if ($key !== '') {
                    $row[$key] = $values[$column] ?? null;
                }
            }

            $filled = collect($row)->except(['employee_name', 'designation', 'kind', 'month', 'repeats'])->filter(fn ($value) => trim((string) $value) !== '');
            if ($filled->isEmpty() || ($filled->keys()->all() === ['employee_code'])) {
                continue;
            }

            $rows[$index + 2] = $row;
        }

        return $rows;
    }

    /**
     * @param  Collection<string, Employee>  $employees
     * @param  list<string>  $errors
     */
    private function findEmployee(Collection $employees, mixed $code, string $where, array &$errors): ?Employee
    {
        $key = mb_strtolower(trim((string) $code));

        if ($key === '') {
            $errors[] = $where.': Employee Code is empty.';

            return null;
        }

        $employee = $employees[$key] ?? null;
        if (! $employee) {
            $errors[] = $where.': no active employee of yours has the code "'.trim((string) $code).'".';
        }

        return $employee;
    }

    /**
     * Null for an empty cell, false for text that is not a number.
     */
    private function number(mixed $value): float|false|null
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $clean = is_string($value) ? str_replace([',', 'Rs', 'rs', ' '], '', $value) : $value;

        return is_numeric($clean) ? round((float) $clean, 2) : false;
    }

    /**
     * A month typed as 2026-11, 11/2026, Nov 2026, a full date or an Excel date.
     */
    private function month(mixed $value): ?Carbon
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && (float) $value > 20000)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfMonth();
        }

        $text = trim((string) $value);
        foreach (['Y-m', 'Y-m-d', 'Y/m', 'm/Y', 'm-Y', 'M Y', 'F Y', 'M-Y', 'M-y', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
            } catch (Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $text) {
                return $date->startOfMonth();
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $titles
     */
    private function header(Worksheet $sheet, array $titles): void
    {
        $sheet->fromArray($titles, null, 'A1');
        $last = $sheet->getHighestColumn();
        $style = $sheet->getStyle('A1:'.$last.'1');
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E79');
        $sheet->freezePane('A2');

        foreach (range('A', $last) as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['B', 'I'], true) ? 30 : 20);
        }
    }

    private function dropDown(Worksheet $sheet, string $range, string $formula, string $prompt): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Choose from the list')
            ->setError('Pick a value from the drop-down list.')
            ->setPromptTitle('Choose from the list')
            ->setPrompt($prompt)
            ->setFormula1($formula);

        $sheet->setDataValidation($range, $validation);
    }

    /**
     * @param  list<string>  $lines
     */
    private function instructions(Spreadsheet $book, array $lines): void
    {
        $sheet = $book->createSheet()->setTitle('Instructions');
        foreach ($lines as $i => $line) {
            $sheet->setCellValue('A'.($i + 1), $line);
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getColumnDimension('A')->setWidth(140);
    }
}

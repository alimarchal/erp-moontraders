<?php

namespace App\Http\Controllers;

use App\Exceptions\PayrollImportException;
use App\Services\PayrollExcelService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel templates and uploads for the salary set-up and for incentives & adjustments, by employee code. Only holders of
 * payroll-import-excel (super-admin and admin) may use them.
 */
class PayrollImportController extends Controller implements HasMiddleware
{
    public const KINDS = ['salaries', 'adjustments'];

    public function __construct(private PayrollExcelService $excel) {}

    public static function middleware(): array
    {
        return [new Middleware('can:payroll-import-excel')];
    }

    public function template(Request $request, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? Carbon::parse($request->query('month').'-01') : now()->startOfMonth();
        $book = $kind === 'salaries' ? $this->excel->salaryTemplate($request->user()) : $this->excel->adjustmentTemplate($request->user(), $month);
        $name = $kind === 'salaries' ? 'salary-setup-template.xlsx' : 'incentives-template-'.$month->format('Y-m').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']], ['file.mimes' => 'Upload the Excel template (.xlsx).']);
        $back = redirect()->route('payroll.index', ['tab' => $kind]);

        try {
            if ($kind === 'salaries') {
                $result = $this->excel->importSalaries($request->file('file')->getRealPath(), $request->user(), $request->boolean('replace'));
                $message = 'Imported the salary set-up of '.$result['employees'].' '.Str::plural('employee', $result['employees']).': '
                    .$result['salaries'].' basic '.Str::plural('salary', $result['salaries']).' and '.$result['lines'].' earnings / deductions.';
            } else {
                $result = $this->excel->importAdjustments($request->file('file')->getRealPath(), $request->user());
                $message = 'Imported '.$result['items'].' '.($result['items'] === 1 ? 'incentive / adjustment' : 'incentives / adjustments').' for '.$result['employees'].' '.Str::plural('employee', $result['employees']).'. They go on the payslips by themselves.';
            }
        } catch (PayrollImportException $e) {
            return $back->with('error', 'Nothing was imported: fix the rows below and upload again.')
                ->with('import_errors', array_slice($e->errors, 0, 50))
                ->with('import_error_count', count($e->errors))
                ->with('import_kind', $kind);
        }

        return $back->with('success', $message);
    }
}

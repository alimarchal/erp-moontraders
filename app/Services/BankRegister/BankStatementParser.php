<?php

namespace App\Services\BankRegister;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reads a bank-statement workbook (the accountant's Excel layout, or a plain CSV/XLSX with the same
 * column names) into normalized rows. Columns are found by header name, so their order does not matter.
 */
class BankStatementParser
{
    /** @var array<string, array<int, string>> */
    private const HEADERS = [
        'statement_id' => ['statement id', 'sr', 'sr no', 's no'],
        'posting_date' => ['posting date', 'date', 'txn date', 'transaction date'],
        'value_date' => ['value date'],
        'party_statement' => ['party name in statement', 'party name', 'name'],
        'party_client' => ['client party name - unnamed entries', 'client party name'],
        'party_summary' => ['party used for summary'],
        'debit' => ['debit (pkr)', 'debit', 'withdrawal', 'withdrawals', 'dr'],
        'credit' => ['credit (pkr)', 'credit', 'deposit', 'deposits', 'cr'],
        'reference' => ['transaction / reference', 'reference', 'transaction', 'chq no', 'cheque no'],
        'narration' => ['original statement narration', 'narration', 'description', 'particulars'],
        'source_statement' => ['source statement'],
        'pdf_page' => ['pdf page'],
        'response_status' => ['response status'],
        'balance' => ['balance', 'running balance'],
        'amount' => ['amount', 'amount (pkr)', 'txn amount'],
        'dr_cr' => ['dr/cr', 'dr cr', 'type', 'debit/credit', 'cr/dr'],
    ];

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, array{row: int, message: string}>}
     */
    public function parse(string $absolutePath): array
    {
        $sheets = Excel::toArray(new class implements ToArray
        {
            public function array(array $array): void {}
        }, $absolutePath);

        $sheet = $sheets[0] ?? [];
        [$headerIndex, $columns] = $this->findHeader($sheet);

        $rows = [];
        $errors = [];

        foreach (array_slice($sheet, $headerIndex + 1, null, true) as $index => $line) {
            $lineNo = $index + 1;

            if ($this->isBlank($line)) {
                continue;
            }

            $get = fn (string $key) => isset($columns[$key]) ? ($line[$columns[$key]] ?? null) : null;

            try {
                $date = $this->date($get('posting_date'));
            } catch (\Throwable) {
                $date = null;
            }

            $debit = $this->amount($get('debit'));
            $credit = $this->amount($get('credit'));

            // layouts with one Amount column: a Dr/Cr marker, or a minus sign for money out
            if (isset($columns['amount']) && ! isset($columns['debit']) && ! isset($columns['credit'])) {
                $single = $this->amount($get('amount'));
                $marker = mb_strtolower((string) $this->text($get('dr_cr')));
                $isDebit = $marker !== '' ? str_starts_with($marker, 'd') : $single < 0;
                $debit = $isDebit ? abs($single) : 0.0;
                $credit = $isDebit ? 0.0 : abs($single);
            }

            if (! $date) {
                $errors[] = ['row' => $lineNo, 'message' => 'Posting date is missing or not a date.'];

                continue;
            }

            if ($debit > 0 && $credit > 0) {
                $errors[] = ['row' => $lineNo, 'message' => 'Both debit and credit are filled in.'];

                continue;
            }

            if ($debit <= 0 && $credit <= 0) {
                $errors[] = ['row' => $lineNo, 'message' => 'Debit and credit are both empty or zero.'];

                continue;
            }

            $statementParty = $this->text($get('party_statement'));
            $summaryParty = $this->text($get('party_summary'));
            $clientParty = $this->text($get('party_client'));
            $party = $summaryParty ?: ($clientParty ?: $statementParty);
            $status = mb_strtolower((string) $this->text($get('response_status')));

            $rows[] = [
                'row' => $lineNo,
                'statement_ref_no' => is_numeric($get('statement_id')) ? (int) $get('statement_id') : null,
                'posting_date' => $date->toDateString(),
                'value_date' => $this->safeDate($get('value_date')),
                'reference' => $this->text($get('reference')),
                'narration' => $this->text($get('narration')),
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => isset($columns['balance']) && is_numeric($get('balance')) ? round((float) $get('balance'), 2) : null,
                'party' => $statementParty ?: $party,
                'party_resolved' => $party,
                'source_statement' => $this->text($get('source_statement')),
                'pdf_page' => is_numeric($get('pdf_page')) ? (int) $get('pdf_page') : null,
                'classification_source' => match (true) {
                    str_contains($status, 'client') => 'client_applied',
                    str_contains($status, 'original') => 'original',
                    default => 'unresolved',
                },
                'needs_review' => str_contains($status, 'yellow') || ($party !== null && str_starts_with(mb_strtolower($party), 'unidentified')),
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  array<int, array<int, mixed>>  $sheet
     * @return array{0: int, 1: array<string, int>}
     */
    private function findHeader(array $sheet): array
    {
        foreach (array_slice($sheet, 0, 25, true) as $index => $line) {
            $columns = [];

            foreach ($line as $position => $cell) {
                $label = mb_strtolower(trim((string) $cell));

                foreach (self::HEADERS as $key => $names) {
                    if (! isset($columns[$key]) && in_array($label, $names, true)) {
                        $columns[$key] = $position;
                    }
                }
            }

            if (isset($columns['posting_date']) && (isset($columns['debit']) || isset($columns['credit']) || isset($columns['amount']))) {
                return [$index, $columns];
            }
        }

        throw new BankRegisterException('The file layout was not recognised. It needs a header row with at least "Posting date" and "Debit" / "Credit" columns.');
    }

    /**
     * @param  array<int, mixed>  $line
     */
    private function isBlank(array $line): bool
    {
        return collect($line)->every(fn ($cell) => $cell === null || trim((string) $cell) === '');
    }

    private function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
        }

        $text = trim((string) $value);

        // Pakistani statements print day first: 15/10/2026, 15-10-2026, 15-Oct-2026
        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'd-M-Y', 'd M Y', 'd/m/y', 'd-m-y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $text);
            } catch (\Throwable) {
                continue;
            }

            if ($parsed->format($format) === $text) {
                return $parsed;
            }
        }

        return Carbon::parse($text)->startOfDay();
    }

    private function safeDate(mixed $value): ?string
    {
        try {
            return $this->date($value)?->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function amount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^\d.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : preg_replace('/\s+/u', ' ', $text);
    }
}

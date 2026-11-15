<?php

namespace App\Exports;

use App\Models\SalesSettlementCheque;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * The Cheque Register as an Excel sheet, with the same company scope, filters, status tab and sort as the screen.
 *
 * The query arrives from the register with the company, salesman, customer and deposit bank already joined.
 */
class ChequeRegisterExport extends DefaultValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping
{
    /**
     * @param  Builder<SalesSettlementCheque>  $query
     */
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * Text is written as text, so a name typed as "=..." cannot run as a formula when the sheet is opened.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Cheque No',
            'Cheque Date',
            'Entry Date',
            'Status',
            'Company',
            'Salesman',
            'Customer',
            'Address',
            'Bank',
            'Settlement',
            'Amount',
            'Cleared Date',
            'Cleared Via',
            'Deposit Bank',
            'Cleared Amount',
        ];
    }

    /**
     * @param  SalesSettlementCheque  $cheque
     * @return array<int, mixed>
     */
    public function map($cheque): array
    {
        $isCleared = $cheque->status === 'cleared';

        return [
            (string) $cheque->cheque_number,
            $cheque->cheque_date?->format('Y-m-d'),
            $cheque->created_at?->format('Y-m-d'),
            ucfirst((string) $cheque->status),
            (string) ($cheque->supplier_name ?? ''),
            (string) ($cheque->employee_name ?? ''),
            (string) ($cheque->customer_name ?? ''),
            (string) ($cheque->customer_address ?? ''),
            (string) ($cheque->bank_name ?? ''),
            (string) ($cheque->settlement_number ?? ''),
            (float) $cheque->amount,
            $isCleared ? $cheque->cleared_date?->format('Y-m-d') : null,
            $isCleared ? match ($cheque->cleared_via) {
                'bank' => 'By bank',
                'cash' => 'Cash back',
                default => '',
            } : '',
            $isCleared ? (string) ($cheque->deposit_bank_name ?? '') : '',
            $isCleared ? (float) ($cheque->cleared_amount ?? $cheque->amount) : null,
        ];
    }
}

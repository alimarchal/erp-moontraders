<?php

namespace App\Exports;

use App\Models\CustomerEmployeeAccountTransaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OpeningCustomerBalanceExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<CustomerEmployeeAccountTransaction>  $query
     */
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Sr#',
            'Date',
            'Supplier',
            'Salesman',
            'Employee Code',
            'Account#',
            'Customer',
            'Customer Code',
            'Address',
            'Opening Balance',
            'Status',
            'Reference',
            'Description',
        ];
    }

    /**
     * @param  CustomerEmployeeAccountTransaction  $transaction
     * @return array<int, mixed>
     */
    public function map($transaction): array
    {
        static $index = 0;
        $index++;

        return [
            $index,
            $transaction->transaction_date->format('d-m-Y'),
            $transaction->account->employee->supplier->supplier_name ?? '',
            $transaction->account->employee->name ?? '',
            $transaction->account->employee->employee_code ?? '',
            $transaction->account->account_number ?? '',
            $transaction->account->customer->customer_name ?? '',
            $transaction->account->customer->customer_code ?? '',
            $transaction->account->customer->address ?? '',
            $transaction->debit,
            $transaction->isPosted() ? 'Posted' : 'Draft',
            $transaction->reference_number ?? '',
            $transaction->description ?? '',
        ];
    }
}

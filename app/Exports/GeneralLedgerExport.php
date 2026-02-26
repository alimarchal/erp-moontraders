<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GeneralLedgerExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<\App\Models\GeneralLedgerEntry>  $query
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
            'Date',
            'Journal #',
            'Line #',
            'Account Code',
            'Account Name',
            'Journal Description',
            'Line Description',
            'Reference',
            'Debit',
            'Credit',
            'Cost Center',
            'Currency',
            'FX Rate',
            'Status',
        ];
    }

    /**
     * @param  \App\Models\GeneralLedgerEntry  $entry
     * @return array<int, mixed>
     */
    public function map($entry): array
    {
        return [
            $entry->entry_date?->format('Y-m-d') ?? '',
            $entry->journal_entry_id ?? '',
            $entry->line_no ?? '',
            $entry->account_code ?? '',
            $entry->account_name ?? '',
            $entry->journal_description ?? '',
            $entry->line_description ?? '',
            $entry->reference ?? '',
            $entry->debit,
            $entry->credit,
            $entry->cost_center_code ?? '',
            $entry->currency_code ?? '',
            $entry->fx_rate_to_base ?? '',
            ucfirst($entry->status ?? 'draft'),
        ];
    }
}

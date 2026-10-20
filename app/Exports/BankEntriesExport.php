<?php

namespace App\Exports;

use App\Models\BankStatementEntry;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BankEntriesExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<BankStatementEntry>  $query
     */
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with(['company', 'category', 'bankAccount'])->orderBy('posting_date')->orderBy('id');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Posting date', 'Bank account', 'Party in statement', 'Company', 'Category', 'Reference', 'Narration', 'Description', 'Debit', 'Credit', 'Status', 'Receipt no.'];
    }

    /**
     * @param  BankStatementEntry  $entry
     * @return array<int, mixed>
     */
    public function map($entry): array
    {
        return [
            $entry->posting_date->format('Y-m-d'),
            $entry->bankAccount->account_name,
            $entry->party_name_in_statement,
            $entry->company?->company_name,
            $entry->category?->name,
            $entry->reference,
            $entry->narration,
            $entry->description,
            (float) $entry->debit,
            (float) $entry->credit,
            $entry->status_label,
            $entry->receipt_no,
        ];
    }
}

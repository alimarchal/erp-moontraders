<?php

namespace App\Services\BankRegister;

use App\Models\BankStatementEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * One place that turns the search form into a query, so the entries list, its Excel export and
 * the reports all filter identically.
 */
class BankStatementSearch
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<BankStatementEntry>
     */
    public function query(array $filters): Builder
    {
        $query = BankStatementEntry::query();

        $query
            ->when($filters['bank_account_id'] ?? null, fn (Builder $q, $v) => $q->where('bank_account_id', $v))
            ->when($filters['company_id'] ?? null, fn (Builder $q, $v) => $v === 'none' ? $q->whereNull('company_id') : $q->where('company_id', $v))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $v === 'none' ? $q->whereNull('bank_entry_category_id') : $q->where('bank_entry_category_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['import_id'] ?? null, fn (Builder $q, $v) => $q->where('import_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('posting_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('posting_date', '<=', $v))
            ->when($filters['amount_min'] ?? null, fn (Builder $q, $v) => $q->whereRaw('(debit + credit) >= ?', [$v]))
            ->when($filters['amount_max'] ?? null, fn (Builder $q, $v) => $q->whereRaw('(debit + credit) <= ?', [$v]))
            ->when($filters['receipt_no'] ?? null, fn (Builder $q, $v) => $q->where('receipt_no', 'like', '%'.$v.'%'))
            ->when($filters['reference'] ?? null, fn (Builder $q, $v) => $this->like($q, 'reference', $v))
            ->when($filters['submitted_by'] ?? null, fn (Builder $q, $v) => $q->where('submitted_by', $v))
            ->when($filters['posted_by'] ?? null, fn (Builder $q, $v) => $q->where('posted_by', $v));

        match ($filters['direction'] ?? null) {
            'credit' => $query->where('credit', '>', 0),
            'debit' => $query->where('debit', '>', 0),
            default => null,
        };

        match ($filters['has_attachment'] ?? null) {
            'yes' => $query->has('attachments'),
            'no' => $query->doesntHave('attachments'),
            default => null,
        };

        match ($filters['needs_review'] ?? null) {
            'yes' => $query->where('needs_review', true),
            'no' => $query->where('needs_review', false),
            default => null,
        };

        match ($filters['reconciled'] ?? null) {
            'yes' => $query->where('status', BankStatementEntry::STATUS_RECONCILED),
            'no' => $query->where('status', '!=', BankStatementEntry::STATUS_RECONCILED),
            default => null,
        };

        if ($text = trim((string) ($filters['q'] ?? ''))) {
            $term = '%'.mb_strtolower($text).'%';

            $query->where(function (Builder $q) use ($term) {
                foreach (['narration', 'reference', 'party_name_in_statement', 'description', 'receipt_no'] as $column) {
                    $q->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$term]);
                }

                $q->orWhereHas('company', fn (Builder $c) => $c->whereRaw('LOWER(supplier_name) LIKE ?', [$term]))
                    ->orWhereHas('attachments', fn (Builder $a) => $a
                        ->whereRaw('LOWER(file_name) LIKE ?', [$term])
                        ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ?", [$term]));
            });
        }

        return $query;
    }

    /**
     * @param  Builder<BankStatementEntry>  $query
     */
    private function like(Builder $query, string $column, string $value): Builder
    {
        return $query->whereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", ['%'.mb_strtolower($value).'%']);
    }

    /**
     * Counts and money per status for the chips above the list (same filters, except status itself).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, array{count: int, amount: float}>
     */
    public function statusSummary(array $filters): array
    {
        unset($filters['status']);

        $rows = $this->query($filters)
            ->reorder()
            ->selectRaw('status, COUNT(*) AS total, SUM(debit + credit) AS total_amount')
            ->groupBy('status')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [$row->status => ['count' => (int) $row->total, 'amount' => (float) $row->total_amount]])->all();
    }
}

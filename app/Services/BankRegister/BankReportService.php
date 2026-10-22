<?php

namespace App\Services\BankRegister;

use App\Models\BankAccount;
use App\Models\BankEntryAuditLog;
use App\Models\BankReceiptScan;
use App\Models\BankReconciliation;
use App\Models\BankStatementEntry;
use App\Models\BankStatementImport;
use App\Models\JournalEntryDetail;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds every Bank Register report as the same plain shape (title, headings, rows, totals) so one
 * screen, one Excel export and one PDF template can show all of them.
 *
 * @phpstan-type Report array{title: string, subtitle: string, headings: array<int, string>, numeric: array<int, int>, rows: array<int, array<int, mixed>>, totals: array<int, mixed>|null, notes: array<int, string>}
 */
class BankReportService
{
    /**
     * slug => [title, permission, description, filter fields it uses]
     *
     * @var array<string, array{title: string, permission: string, description: string, filters: array<int, string>}>
     */
    public const REPORTS = [
        'entries' => ['title' => 'Register entries', 'permission' => 'report-bank-entries', 'description' => 'Every register entry with filters: account, dates, received / paid, company, category, status, receipt. Also gives company ledgers, unclassified, unreconciled, no-receipt and pending-approval lists.', 'filters' => ['bank_account_id', 'date_from', 'date_to', 'direction', 'company_id', 'category_id', 'status', 'receipt', 'sort']],
        'summary' => ['title' => 'Register summary', 'permission' => 'report-bank-summary', 'description' => 'Totals grouped by company, category, bank account, month or day (or company / category by month), with the same filters.', 'filters' => ['group_by', 'bank_account_id', 'date_from', 'date_to', 'direction', 'company_id', 'category_id', 'status']],
        'reconciliation-statement' => ['title' => 'Bank reconciliation statement', 'permission' => 'report-bank-reconciliation-statement', 'description' => 'Statement balance, outstanding items, book balance and difference of one reconciliation.', 'filters' => ['reconciliation_id']],
        'unlinked-scans' => ['title' => 'Scanned receipts not yet linked', 'permission' => 'report-bank-unlinked-scans', 'description' => 'Receipts waiting in the scan inbox, oldest first, so none is forgotten.', 'filters' => ['bank_account_id']],
        'live-gap' => ['title' => 'Not yet in the live books', 'permission' => 'report-bank-live-gap', 'description' => 'Statement lines for which the live ledger of the account (settlements, supplier payments ...) has no entry of the same amount within 3 days.', 'filters' => ['bank_account_id', 'date_from', 'date_to']],
        'audit-log' => ['title' => 'Audit log', 'permission' => 'report-bank-audit-log', 'description' => 'Who classified, submitted, approved, posted or reversed what, and when.', 'filters' => ['date_from', 'date_to']],
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(string $slug, array $filters): array
    {
        return match ($slug) {
            'entries' => $this->entries($filters),
            'summary' => $this->summary($filters),
            'reconciliation-statement' => $this->reconciliationStatement($filters),
            'unlinked-scans' => $this->unlinkedScans($filters),
            'live-gap' => $this->liveGap($filters),
            'audit-log' => $this->auditLog($filters),
            default => throw new BankRegisterException('Unknown report.'),
        };

    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<BankStatementEntry>
     */
    private function base(array $filters): Builder
    {
        return BankStatementEntry::query()
            ->when($filters['bank_account_id'] ?? null, fn (Builder $q, $v) => $q->where('bank_account_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('posting_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('posting_date', '<=', $v))
            ->when($filters['company_id'] ?? null, fn (Builder $q, $v) => $q->where('bank_statement_entries.company_id', $v))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $q->where('bank_statement_entries.bank_entry_category_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('bank_statement_entries.status', $v))
            ->when(($filters['direction'] ?? null) === 'credit', fn (Builder $q) => $q->where('bank_statement_entries.credit', '>', 0))
            ->when(($filters['direction'] ?? null) === 'debit', fn (Builder $q) => $q->where('bank_statement_entries.debit', '>', 0))
            ->when(($filters['receipt'] ?? null) === 'without', fn (Builder $q) => $q->where(fn (Builder $w) => $w->doesntHave('attachments')->orWhereNull('receipt_no')))
            ->when(($filters['receipt'] ?? null) === 'with', fn (Builder $q) => $q->has('attachments')->whereNotNull('receipt_no'));
    }

    /**
     * One flexible list of register entries; every filter is optional.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function entries(array $filters): array
    {
        $sort = $filters['sort'] ?? 'date';
        $query = $this->base($filters)->with(['company', 'category'])->withCount('attachments');

        match ($sort) {
            'amount' => $query->orderByRaw('(debit + credit) DESC'),
            'party' => $query->orderBy('party_name_in_statement')->orderBy('posting_date'),
            default => $query->orderBy('posting_date')->orderBy('id'),
        };

        $entries = $query->limit(2000)->get();
        $ledger = ! empty($filters['company_id']);
        $balance = 0.0;

        $rows = $entries->map(function (BankStatementEntry $e) use ($ledger, &$balance) {
            $balance += (float) $e->credit - (float) $e->debit;

            return [
                $e->posting_date->format('d-m-Y'),
                $e->company?->supplier_name ?: $e->party_name_in_statement,
                $e->receipt_no ?: $e->reference,
                $e->category?->name ?? '-',
                $e->status_label,
                $e->attachments_count,
                (float) $e->debit,
                (float) $e->credit,
                ...($ledger ? [round($balance, 2)] : []),
            ];
        })->all();

        $totals = ['Total', '', '', '', '', '', round(array_sum(array_column($rows, 6)), 2), round(array_sum(array_column($rows, 7)), 2)];
        if ($ledger) {
            $totals[] = round($balance, 2);
        }

        return $this->shape(
            'Register entries',
            $this->subtitle($filters),
            ['Date', 'Party / company', 'Receipt / reference', 'Category', 'Status', 'Files', 'Paid out', 'Received', ...($ledger ? ['Balance'] : [])],
            $ledger ? [5, 6, 7, 8] : [5, 6, 7],
            $rows,
            $totals,
            $entries->count() === 2000 ? ['Showing the first 2,000 entries. Narrow the dates to see the rest.'] : [],
        );
    }

    /**
     * Totals grouped by the chosen dimension.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function summary(array $filters): array
    {
        $groupBy = $filters['group_by'] ?? 'company';

        if (in_array($groupBy, ['company_month', 'category_month'], true)) {
            return $this->monthSummary($filters + ['type' => $filters['direction'] ?? 'credit'], $groupBy === 'company_month' ? 'company' : 'category');
        }

        $label = match ($groupBy) {
            'category' => "COALESCE(bank_entry_categories.name, 'Uncategorised')",
            'account' => 'bank_accounts.account_name',
            default => "COALESCE(suppliers.supplier_name, NULLIF(bank_statement_entries.party_name_in_statement, ''), 'Unassigned')",
        };

        $groups = [];
        $query = $this->base($filters)
            ->leftJoin('suppliers', 'suppliers.id', '=', 'bank_statement_entries.company_id')
            ->leftJoin('bank_entry_categories', 'bank_entry_categories.id', '=', 'bank_statement_entries.bank_entry_category_id')
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'bank_statement_entries.bank_account_id')
            ->select(['bank_statement_entries.posting_date', 'bank_statement_entries.debit', 'bank_statement_entries.credit', DB::raw("{$label} as label")]);

        foreach ($query->cursor() as $row) {
            $key = match ($groupBy) {
                'month' => Carbon::parse($row->posting_date)->format('Y-m'),
                'day' => Carbon::parse($row->posting_date)->format('Y-m-d'),
                default => (string) $row->label,
            };
            $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
            $groups[$key]['in'] = ($groups[$key]['in'] ?? 0) + (float) $row->credit;
            $groups[$key]['out'] = ($groups[$key]['out'] ?? 0) + (float) $row->debit;
        }

        in_array($groupBy, ['month', 'day'], true) ? ksort($groups) : uasort($groups, fn ($a, $b) => ($b['in'] + $b['out']) <=> ($a['in'] + $a['out']));

        $rows = [];
        foreach ($groups as $key => $group) {
            $name = match ($groupBy) {
                'month' => Carbon::createFromFormat('Y-m', (string) $key)->format('M Y'),
                'day' => Carbon::parse((string) $key)->format('d-m-Y'),
                default => (string) $key,
            };
            $rows[] = [$name, $group['count'], round($group['in'], 2), round($group['out'], 2), round($group['in'] - $group['out'], 2)];
        }

        $heading = ['company' => 'Party / company', 'category' => 'Category', 'account' => 'Bank account', 'month' => 'Month', 'day' => 'Day'][$groupBy] ?? 'Party / company';

        return $this->shape(
            'Register summary by '.strtolower($heading),
            $this->subtitle($filters),
            [$heading, 'Entries', 'Received', 'Paid out', 'Net'],
            [1, 2, 3, 4],
            $rows,
            ['Total', array_sum(array_column($rows, 1)), round(array_sum(array_column($rows, 2)), 2), round(array_sum(array_column($rows, 3)), 2), round(array_sum(array_column($rows, 4)), 2)],
            [],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function monthSummary(array $filters, string $by): array
    {
        $type = ($filters['type'] ?? 'credit') === 'debit' ? 'debit' : 'credit';
        $label = $by === 'company'
            ? "COALESCE(suppliers.supplier_name, NULLIF(bank_statement_entries.party_name_in_statement, ''), 'Unassigned')"
            : "COALESCE(bank_entry_categories.name, 'Uncategorised')";

        $query = $this->base($filters)
            ->leftJoin('suppliers', 'suppliers.id', '=', 'bank_statement_entries.company_id')
            ->leftJoin('bank_entry_categories', 'bank_entry_categories.id', '=', 'bank_statement_entries.bank_entry_category_id')
            ->where("bank_statement_entries.{$type}", '>', 0)
            ->select(['bank_statement_entries.posting_date', "bank_statement_entries.{$type} as line_amount", DB::raw("{$label} as label")]);

        $groups = [];
        $minDate = null;
        $maxDate = null;

        foreach ($query->cursor() as $row) {
            $date = Carbon::parse($row->posting_date);
            $month = $date->format('Y-m');
            $minDate = $minDate && $minDate->lt($date) ? $minDate : $date;
            $maxDate = $maxDate && $maxDate->gt($date) ? $maxDate : $date;

            $groups[$row->label]['count'] = ($groups[$row->label]['count'] ?? 0) + 1;
            $groups[$row->label]['months'][$month] = ($groups[$row->label]['months'][$month] ?? 0) + (float) $row->line_amount;
        }

        $months = [];
        if ($minDate) {
            $cursor = $minDate->copy()->startOfMonth();
            while ($cursor->lte($maxDate) && count($months) < 36) {
                $months[$cursor->format('Y-m')] = $cursor->format('M-y');
                $cursor->addMonth();
            }
        }

        $rows = [];
        foreach ($groups as $name => $group) {
            $rows[] = [
                $name,
                $group['count'],
                round(array_sum($group['months']), 2),
                ...array_map(fn (string $key) => round($group['months'][$key] ?? 0, 2), array_keys($months)),
            ];
        }
        usort($rows, fn ($a, $b) => $b[2] <=> $a[2]);

        $totals = ['Total', array_sum(array_column($rows, 1)), round(array_sum(array_column($rows, 2)), 2)];
        foreach (array_keys($months) as $i => $key) {
            $totals[] = round(array_sum(array_column($rows, 3 + $i)), 2);
        }

        $notes = [];
        if (! empty($filters['bank_account_id'])) {
            $control = BankStatementImport::query()
                ->where('bank_account_id', $filters['bank_account_id'])
                ->where('status', 'imported')
                ->sum($type === 'debit' ? 'control_total_debit' : 'control_total_credit');

            if ($control > 0) {
                $notes[] = 'Statement control total: '.number_format($control, 2).' | Difference: '.number_format($totals[2] - $control, 2);
            }
        }

        return $this->shape(
            ($by === 'company' ? 'Company' : 'Category').'-wise '.($type === 'credit' ? 'credit (money in)' : 'debit (money out)').' by month',
            $this->subtitle($filters),
            [$by === 'company' ? 'Party / company' : 'Category', 'Entries', 'Total (PKR)', ...array_values($months)],
            range(1, 3 + count($months)),
            $rows,
            $totals,
            $notes,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function reconciliationStatement(array $filters): array
    {
        $reconciliation = BankReconciliation::with('bankAccount')->find($filters['reconciliation_id'] ?? null);

        if (! $reconciliation) {
            return $this->shape('Bank reconciliation statement', 'Choose a reconciliation.', ['Item', 'Amount (PKR)'], [1], [], null, []);
        }

        $totals = app(BankReconciliationService::class)->refreshTotals($reconciliation);

        $rows = [
            ['Balance as per bank statement', (float) $reconciliation->statement_balance],
            ['Add: deposits in transit (in books, not on statement)', $totals['deposits_in_transit']],
            ['Less: outstanding payments (in books, not on statement)', -$totals['outstanding_payments']],
            ['Adjusted statement balance', $totals['adjusted_statement']],
            ['Balance as per books (ledger)', $totals['book_balance']],
            ['Difference', $totals['difference']],
        ];

        foreach ($totals['unmatched_entries'] as $entry) {
            $rows[] = ['Unmatched statement line: '.$entry->posting_date->format('d-m-Y').' '.$entry->reference, $entry->direction === 'credit' ? (float) $entry->credit : -(float) $entry->debit];
        }

        return $this->shape(
            'Bank reconciliation statement - '.$reconciliation->bankAccount->account_name,
            $reconciliation->period_from->format('d-m-Y').' to '.$reconciliation->period_to->format('d-m-Y').' ('.ucfirst(str_replace('_', ' ', $reconciliation->status)).')',
            ['Item', 'Amount (PKR)'],
            [1],
            $rows,
            null,
            [],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function unlinkedScans(array $filters): array
    {
        $scans = BankReceiptScan::query()->with(['bankAccount', 'uploader'])
            ->where('status', BankReceiptScan::STATUS_PENDING)
            ->when($filters['bank_account_id'] ?? null, fn (Builder $q, $v) => $q->where('bank_account_id', $v))
            ->orderBy('scan_date')->get();

        $rows = $scans->map(fn (BankReceiptScan $scan) => [
            $scan->scan_date->format('d-m-Y'),
            (int) $scan->scan_date->diffInDays(now()),
            $scan->file_name,
            $scan->bankAccount?->account_name ?? '-',
            $scan->note ?? '-',
            $scan->uploader?->name ?? '-',
            $scan->amount ? (float) $scan->amount : 0.0,
        ])->all();

        return $this->shape('Scanned receipts not yet linked', 'Oldest first', ['Scanned on', 'Days waiting', 'File', 'Account', 'Note', 'Uploaded by', 'Amount'], [1, 6], $rows, null, []);
    }

    /**
     * Statement lines whose amount cannot be found in the live ledger of the same account: money that moved in the
     * bank but that GRN / settlement / supplier payment have not booked (yet). The register itself never touches the
     * live ledger, so this is the bridge between the two sets of books.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function liveGap(array $filters): array
    {
        $rows = [];
        $matched = 0;

        $accounts = BankAccount::query()->whereNotNull('chart_of_account_id')
            ->when($filters['bank_account_id'] ?? null, fn (Builder $q, $v) => $q->where('id', $v))
            ->get();

        foreach ($accounts as $account) {
            $entries = $this->base($filters)->where('bank_account_id', $account->id)->orderBy('posting_date')->get();

            if ($entries->isEmpty()) {
                continue;
            }

            $from = $entries->min('posting_date')->copy()->subDays(3);
            $to = $entries->max('posting_date')->copy()->addDays(3);

            $live = JournalEntryDetail::query()
                ->with('journalEntry:id,entry_date,reference')
                ->where('chart_of_account_id', $account->chart_of_account_id)
                ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))
                ->get()
                ->map(fn ($line) => ['id' => $line->id, 'date' => $line->journalEntry->entry_date, 'in' => (float) $line->debit, 'out' => (float) $line->credit])
                ->all();

            $used = [];

            foreach ($entries as $entry) {
                $found = false;

                foreach ($live as $i => $line) {
                    $same = $entry->direction === 'credit' ? abs($line['in'] - $entry->amount) < 0.01 : abs($line['out'] - $entry->amount) < 0.01;

                    if (! isset($used[$i]) && $same && abs($line['date']->diffInDays($entry->posting_date)) <= 3) {
                        $used[$i] = true;
                        $found = true;
                        $matched++;
                        break;
                    }
                }

                if (! $found) {
                    $rows[] = [
                        $account->account_name,
                        $entry->posting_date->format('d-m-Y'),
                        $entry->company?->supplier_name ?: $entry->party_name_in_statement,
                        $entry->reference,
                        $entry->category?->name ?? '-',
                        $entry->receipt_no ?: '-',
                        (float) $entry->debit,
                        (float) $entry->credit,
                    ];
                }
            }
        }

        return $this->shape(
            'Not yet in the live books',
            $this->subtitle($filters),
            ['Account', 'Date', 'Party / company', 'Reference', 'Category', 'Receipt no.', 'Paid out', 'Received'],
            [6, 7],
            $rows,
            ['Total', '', '', '', '', '', round(array_sum(array_column($rows, 6)), 2), round(array_sum(array_column($rows, 7)), 2)],
            ["{$matched} statement line(s) were found in the live ledger. The lines above were not."],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function auditLog(array $filters): array
    {
        $logs = BankEntryAuditLog::query()
            ->with(['user', 'entry'])
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('created_at')->latest('id')
            ->limit(1000)
            ->get();

        $rows = $logs->map(fn (BankEntryAuditLog $log) => [
            $log->created_at->format('d-m-Y H:i'),
            $log->user?->name ?? 'System',
            ucfirst(str_replace('_', ' ', $log->action)),
            $log->entry?->receipt_no ?: ($log->entry?->reference ?? '#'.$log->bank_statement_entry_id),
            collect($log->new_values ?? [])->map(fn ($v, $k) => $k.': '.(is_scalar($v) ? $v : json_encode($v)))->implode(', '),
        ])->all();

        return $this->shape('Audit log', $this->subtitle($filters), ['When', 'Who', 'Action', 'Entry', 'Details'], [], $rows, null, $logs->count() === 1000 ? ['Showing the latest 1,000 lines.'] : []);
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, int>  $numeric
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, mixed>|null  $totals
     * @param  array<int, string>  $notes
     * @return array<string, mixed>
     */
    private function shape(string $title, string $subtitle, array $headings, array $numeric, array $rows, ?array $totals, array $notes): array
    {
        return compact('title', 'subtitle', 'headings', 'numeric', 'rows', 'totals', 'notes');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function subtitle(array $filters): string
    {
        $parts = [];

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $parts[] = ($filters['date_from'] ?? '...').' to '.($filters['date_to'] ?? '...');
        }

        if (! empty($filters['bank_account_id']) && $account = BankAccount::find($filters['bank_account_id'])) {
            $parts[] = $account->account_name;
        }

        if (isset($filters['opening_balance']) && $filters['opening_balance'] !== '') {
            $parts[] = 'Opening balance '.number_format((float) $filters['opening_balance'], 2);
        }

        return implode(' | ', $parts) ?: 'All dates, all bank accounts';
    }
}

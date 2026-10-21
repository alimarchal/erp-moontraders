<?php

namespace App\Services\BankRegister;

use App\Models\BankAccount;
use App\Models\BankEntryAuditLog;
use App\Models\BankReceiptScan;
use App\Models\BankReconciliation;
use App\Models\BankStatementEntry;
use App\Models\BankStatementImport;
use App\Models\Company;
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
        'company-month-summary' => ['title' => 'Company-wise month-wise summary', 'permission' => 'report-bank-company-month-summary', 'description' => 'Credits or debits per party/company for every month, with the statement control-total check.', 'filters' => ['bank_account_id', 'date_from', 'date_to', 'type']],
        'category-month-summary' => ['title' => 'Category-wise month-wise summary', 'permission' => 'report-bank-category-month-summary', 'description' => 'Credits or debits per category for every month.', 'filters' => ['bank_account_id', 'date_from', 'date_to', 'type']],
        'company-ledger' => ['title' => 'Company ledger (statement of account)', 'permission' => 'report-bank-company-ledger', 'description' => 'Every bank entry of one company with a running balance.', 'filters' => ['company_id', 'bank_account_id', 'date_from', 'date_to']],
        'reconciliation-statement' => ['title' => 'Bank reconciliation statement', 'permission' => 'report-bank-reconciliation-statement', 'description' => 'Statement balance, outstanding items, book balance and difference of one reconciliation.', 'filters' => ['reconciliation_id']],
        'unclassified' => ['title' => 'Unclassified entries (largest first)', 'permission' => 'report-bank-unclassified', 'description' => 'Entries still waiting for a category, biggest amounts first.', 'filters' => ['bank_account_id', 'date_from', 'date_to']],
        'unreconciled' => ['title' => 'Unreconciled entries', 'permission' => 'report-bank-unreconciled', 'description' => 'Entries that are not yet part of a completed reconciliation.', 'filters' => ['bank_account_id', 'date_from', 'date_to']],
        'missing-attachments' => ['title' => 'Entries without receipt / attachment', 'permission' => 'report-bank-missing-attachments', 'description' => 'Entries that have no attachment, or no receipt number yet.', 'filters' => ['bank_account_id', 'date_from', 'date_to']],
        'pending-approvals' => ['title' => 'Pending approvals', 'permission' => 'report-bank-pending-approvals', 'description' => 'Entries waiting for a checker or senior approver, oldest first.', 'filters' => ['bank_account_id']],
        'cash-flow' => ['title' => 'Cash-flow summary', 'permission' => 'report-bank-cash-flow', 'description' => 'Receipts and payments per category.', 'filters' => ['bank_account_id', 'date_from', 'date_to']],
        'charges-tax' => ['title' => 'Bank charges and tax', 'permission' => 'report-bank-charges-tax', 'description' => 'Month-wise bank charges and tax / government payments.', 'filters' => ['bank_account_id', 'date_from', 'date_to']],
        'top-parties' => ['title' => 'Top parties', 'permission' => 'report-bank-top-parties', 'description' => 'The 25 biggest paying and paid parties.', 'filters' => ['bank_account_id', 'date_from', 'date_to']],
        'account-summary' => ['title' => 'All accounts at a glance', 'permission' => 'report-bank-account-summary', 'description' => 'For every bank account: entries, money in and out, what is unclassified, waiting for approval or still without a receipt.', 'filters' => ['date_from', 'date_to']],
        'daily-book' => ['title' => 'Daily book (day by day)', 'permission' => 'report-bank-daily-book', 'description' => 'One account day by day: received, paid out and the closing balance after each day.', 'filters' => ['bank_account_id', 'date_from', 'date_to', 'opening_balance']],
        'unlinked-scans' => ['title' => 'Scanned receipts not yet linked', 'permission' => 'report-bank-unlinked-scans', 'description' => 'Receipts waiting in the scan inbox, oldest first, so none is forgotten.', 'filters' => ['bank_account_id']],
        'audit-log' => ['title' => 'Audit log', 'permission' => 'report-bank-audit-log', 'description' => 'Who classified, submitted, approved, posted or reversed what, and when.', 'filters' => ['date_from', 'date_to']],
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(string $slug, array $filters): array
    {
        return match ($slug) {
            'company-month-summary' => $this->monthSummary($filters, 'company'),
            'category-month-summary' => $this->monthSummary($filters, 'category'),
            'company-ledger' => $this->companyLedger($filters),
            'reconciliation-statement' => $this->reconciliationStatement($filters),
            'unclassified' => $this->entryList($filters, 'Unclassified entries (largest first)', fn (Builder $q) => $q->whereIn('status', ['unclassified', 'rejected'])->orderByRaw('(debit + credit) DESC')),
            'unreconciled' => $this->entryList($filters, 'Unreconciled entries', fn (Builder $q) => $q->where('status', '!=', BankStatementEntry::STATUS_RECONCILED)->orderBy('posting_date')),
            'missing-attachments' => $this->entryList($filters, 'Entries without receipt / attachment', fn (Builder $q) => $q->where(fn (Builder $w) => $w->doesntHave('attachments')->orWhereNull('receipt_no'))->orderBy('posting_date')),
            'pending-approvals' => $this->pendingApprovals($filters),
            'cash-flow' => $this->cashFlow($filters),
            'charges-tax' => $this->chargesTax($filters),
            'top-parties' => $this->topParties($filters),
            'account-summary' => $this->accountSummary($filters),
            'daily-book' => $this->dailyBook($filters),
            'unlinked-scans' => $this->unlinkedScans($filters),
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
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('posting_date', '<=', $v));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function monthSummary(array $filters, string $by): array
    {
        $type = ($filters['type'] ?? 'credit') === 'debit' ? 'debit' : 'credit';
        $label = $by === 'company'
            ? "COALESCE(companies.company_name, NULLIF(bank_statement_entries.party_name_in_statement, ''), 'Unassigned')"
            : "COALESCE(bank_entry_categories.name, 'Uncategorised')";

        $query = $this->base($filters)
            ->leftJoin('companies', 'companies.id', '=', 'bank_statement_entries.company_id')
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
    private function companyLedger(array $filters): array
    {
        if (empty($filters['company_id'])) {
            return $this->shape('Company ledger', 'Choose a company to see its ledger.', ['Date', 'Receipt / reference', 'Narration', 'Paid out', 'Received', 'Balance'], [3, 4, 5], [], null, []);
        }

        $company = Company::find($filters['company_id']);
        $balance = 0.0;
        $rows = [];

        foreach ($this->base($filters)->where('company_id', $filters['company_id'])->orderBy('posting_date')->orderBy('id')->get() as $entry) {
            $balance += (float) $entry->credit - (float) $entry->debit;
            $rows[] = [$entry->posting_date->format('d-m-Y'), $entry->receipt_no ?: $entry->reference, $entry->description ?: $entry->narration, (float) $entry->debit, (float) $entry->credit, round($balance, 2)];
        }

        return $this->shape(
            'Company ledger - '.($company?->company_name ?? ''),
            $this->subtitle($filters),
            ['Date', 'Receipt / reference', 'Narration', 'Paid out', 'Received', 'Balance'],
            [3, 4, 5],
            $rows,
            ['Total', '', '', round(array_sum(array_column($rows, 3)), 2), round(array_sum(array_column($rows, 4)), 2), round($balance, 2)],
            [],
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
     * @param  callable(Builder<BankStatementEntry>): mixed  $scope
     * @return array<string, mixed>
     */
    private function entryList(array $filters, string $title, callable $scope): array
    {
        $query = $this->base($filters)->with(['company', 'category'])->withCount('attachments');
        $scope($query);
        $entries = $query->limit(2000)->get();

        $rows = $entries->map(fn (BankStatementEntry $e) => [
            $e->posting_date->format('d-m-Y'),
            $e->company?->company_name ?: $e->party_name_in_statement,
            $e->reference,
            $e->category?->name ?? '-',
            $e->status_label,
            $e->receipt_no ?: '-',
            $e->attachments_count,
            (float) $e->debit,
            (float) $e->credit,
        ])->all();

        return $this->shape($title, $this->subtitle($filters), ['Date', 'Party / company', 'Reference', 'Category', 'Status', 'Receipt no.', 'Files', 'Debit', 'Credit'], [6, 7, 8], $rows, ['Total', '', '', '', '', '', '', round(array_sum(array_column($rows, 7)), 2), round(array_sum(array_column($rows, 8)), 2)], $entries->count() === 2000 ? ['Showing the first 2,000 entries. Narrow the dates to see the rest.'] : []);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function pendingApprovals(array $filters): array
    {
        $entries = $this->base($filters)->with(['company', 'submitter'])->where('status', BankStatementEntry::STATUS_PENDING_APPROVAL)->orderBy('submitted_at')->get();

        $rows = $entries->map(fn (BankStatementEntry $e) => [
            $e->submitted_at?->format('d-m-Y H:i'),
            $e->company?->company_name ?: $e->party_name_in_statement,
            $e->reference,
            $e->submitter?->name ?? '-',
            $e->approval_level_required === 2 ? 'Checker + senior' : 'Checker',
            (int) $e->submitted_at?->diffInDays(now()),
            $e->amount,
        ])->all();

        return $this->shape('Pending approvals', $this->subtitle($filters), ['Submitted', 'Party / company', 'Reference', 'Maker', 'Needs', 'Days waiting', 'Amount'], [5, 6], $rows, ['Total', '', '', '', '', '', round(array_sum(array_column($rows, 6)), 2)], []);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function cashFlow(array $filters): array
    {
        $rows = $this->base($filters)
            ->leftJoin('bank_entry_categories', 'bank_entry_categories.id', '=', 'bank_statement_entries.bank_entry_category_id')
            ->selectRaw("COALESCE(bank_entry_categories.name, 'Uncategorised') as label, COUNT(*) as entries, SUM(credit) as receipts, SUM(debit) as payments")
            ->groupByRaw("COALESCE(bank_entry_categories.name, 'Uncategorised')")
            ->get()
            ->map(fn ($r) => [$r->label, (int) $r->entries, (float) $r->receipts, (float) $r->payments, (float) $r->receipts - (float) $r->payments])
            ->sortByDesc(fn ($r) => $r[2] + $r[3])->values()->all();

        return $this->shape('Cash-flow summary', $this->subtitle($filters), ['Category', 'Entries', 'Receipts', 'Payments', 'Net'], [1, 2, 3, 4], $rows, ['Total', array_sum(array_column($rows, 1)), round(array_sum(array_column($rows, 2)), 2), round(array_sum(array_column($rows, 3)), 2), round(array_sum(array_column($rows, 4)), 2)], []);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function chargesTax(array $filters): array
    {
        $entries = $this->base($filters)
            ->whereHas('category', fn (Builder $q) => $q->whereIn('slug', ['bank-charges', 'tax-government']))
            ->with('category')
            ->orderBy('posting_date')
            ->get();

        $rows = $entries->map(fn (BankStatementEntry $e) => [$e->posting_date->format('d-m-Y'), $e->category->name, $e->reference, $e->description ?: $e->narration, (float) $e->debit])->all();

        return $this->shape('Bank charges and tax', $this->subtitle($filters), ['Date', 'Category', 'Reference', 'Narration', 'Amount'], [4], $rows, ['Total', '', '', '', round(array_sum(array_column($rows, 4)), 2)], []);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function topParties(array $filters): array
    {
        $rows = [];

        foreach (['credit' => 'Received from', 'debit' => 'Paid to'] as $column => $label) {
            $this->base($filters)
                ->leftJoin('companies', 'companies.id', '=', 'bank_statement_entries.company_id')
                ->where("bank_statement_entries.{$column}", '>', 0)
                ->selectRaw("COALESCE(companies.company_name, NULLIF(bank_statement_entries.party_name_in_statement, ''), 'Unassigned') as party, COUNT(*) as entries, SUM(bank_statement_entries.{$column}) as total")
                ->groupByRaw("COALESCE(companies.company_name, NULLIF(bank_statement_entries.party_name_in_statement, ''), 'Unassigned')")
                ->orderByDesc('total')
                ->limit(25)
                ->get()
                ->each(function ($r, $i) use (&$rows, $label) {
                    $rows[] = [$label, $i + 1, $r->party, (int) $r->entries, (float) $r->total];
                });
        }

        return $this->shape('Top parties', $this->subtitle($filters), ['Direction', 'Rank', 'Party / company', 'Entries', 'Total (PKR)'], [1, 3, 4], $rows, null, []);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function accountSummary(array $filters): array
    {
        $rows = [];

        foreach (BankAccount::orderBy('account_name')->get() as $account) {
            $entries = $this->base($filters)->where('bank_account_id', $account->id);

            $rows[] = [
                $account->account_name.' ('.$account->account_number.')',
                (clone $entries)->count(),
                round((float) (clone $entries)->sum('credit'), 2),
                round((float) (clone $entries)->sum('debit'), 2),
                round((float) (clone $entries)->sum('credit') - (float) (clone $entries)->sum('debit'), 2),
                (clone $entries)->whereIn('status', ['unclassified', 'rejected'])->count(),
                (clone $entries)->where('status', BankStatementEntry::STATUS_PENDING_APPROVAL)->count(),
                (clone $entries)->doesntHave('attachments')->count(),
                (clone $entries)->max('posting_date') ? Carbon::parse((clone $entries)->max('posting_date'))->format('d-m-Y') : '-',
            ];
        }

        $sum = fn (int $i) => array_sum(array_column($rows, $i));

        return $this->shape('All accounts at a glance', $this->subtitle($filters), ['Account', 'Entries', 'Received', 'Paid out', 'Net', 'Unclassified', 'Pending approval', 'No receipt', 'Last entry'], [1, 2, 3, 4, 5, 6, 7], $rows, ['Total', $sum(1), round($sum(2), 2), round($sum(3), 2), round($sum(4), 2), $sum(5), $sum(6), $sum(7), ''], []);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function dailyBook(array $filters): array
    {
        if (empty($filters['bank_account_id'])) {
            return $this->shape('Daily book', 'Choose a bank account.', ['Date', 'Entries', 'Received', 'Paid out', 'Net', 'Closing balance'], [1, 2, 3, 4, 5], [], null, []);
        }

        $opening = (float) ($filters['opening_balance'] ?? 0);

        // money moved before the first day shown is part of the opening position
        if (! empty($filters['date_from'])) {
            $before = BankStatementEntry::query()->where('bank_account_id', $filters['bank_account_id'])->whereDate('posting_date', '<', $filters['date_from']);
            $opening += (float) (clone $before)->sum('credit') - (float) (clone $before)->sum('debit');
        }

        $days = [];
        foreach ($this->base($filters)->orderBy('posting_date')->get(['posting_date', 'debit', 'credit']) as $entry) {
            $key = $entry->posting_date->toDateString();
            $days[$key]['entries'] = ($days[$key]['entries'] ?? 0) + 1;
            $days[$key]['in'] = ($days[$key]['in'] ?? 0) + (float) $entry->credit;
            $days[$key]['out'] = ($days[$key]['out'] ?? 0) + (float) $entry->debit;
        }
        ksort($days);

        $balance = $opening;
        $rows = [['Opening balance', '', '', '', '', round($opening, 2)]];
        foreach ($days as $date => $day) {
            $balance += $day['in'] - $day['out'];
            $rows[] = [Carbon::parse($date)->format('d-m-Y'), $day['entries'], round($day['in'], 2), round($day['out'], 2), round($day['in'] - $day['out'], 2), round($balance, 2)];
        }

        $totals = array_slice($rows, 1);

        return $this->shape('Daily book', $this->subtitle($filters), ['Date', 'Entries', 'Received', 'Paid out', 'Net', 'Closing balance'], [1, 2, 3, 4, 5], $rows, ['Total', array_sum(array_column($totals, 1)), round(array_sum(array_column($totals, 2)), 2), round(array_sum(array_column($totals, 3)), 2), round(array_sum(array_column($totals, 4)), 2), round($balance, 2)], []);
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

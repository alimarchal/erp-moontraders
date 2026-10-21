<?php

namespace App\Http\Controllers;

use App\Exports\BankEntriesExport;
use App\Http\Requests\BankBulkClassifyRequest;
use App\Http\Requests\BankStatementEntryRequest;
use App\Http\Requests\StoreBankStatementEntryRequest;
use App\Models\BankAccount;
use App\Models\BankEntryAuditLog;
use App\Models\BankEntryCategory;
use App\Models\BankEntryRule;
use App\Models\BankPartyAlias;
use App\Models\BankReceiptScan;
use App\Models\BankStatementEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BankRegister\BankApprovalRules;
use App\Services\BankRegister\BankEntryApprovalService;
use App\Services\BankRegister\BankEntryAttachmentService;
use App\Services\BankRegister\BankEntryClassifier;
use App\Services\BankRegister\BankEntryPostingService;
use App\Services\BankRegister\BankReceiptScanService;
use App\Services\BankRegister\BankRegisterException;
use App\Services\BankRegister\BankStatementSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BankStatementEntryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-statement-entry-list', only: ['index', 'show', 'drawer']),
            new Middleware('can:bank-statement-entry-create', only: ['create', 'store']),
            new Middleware('can:bank-statement-entry-export', only: ['export']),
            new Middleware('can:bank-statement-entry-edit', only: ['edit', 'update']),
            new Middleware('can:bank-statement-entry-delete', only: ['destroy']),
            new Middleware('can:bank-statement-entry-classify', only: ['bulkClassify', 'autoClassify', 'flag']),
            new Middleware('can:bank-statement-entry-submit', only: ['submit', 'bulkSubmit']),
            new Middleware('can:bank-statement-entry-reverse', only: ['reverse']),
        ];
    }

    public function index(Request $request, BankStatementSearch $search): View
    {
        $filters = (array) $request->input('filter', []);

        $entries = $search->query($filters)
            ->with(['company', 'category', 'bankAccount'])
            ->withCount('attachments')
            ->orderByDesc('posting_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $summary = $search->statusSummary($filters);
        $withoutReceipt = $search->query(array_merge(array_diff_key($filters, ['status' => 1]), ['has_attachment' => 'no']))->count();

        return view('bank-statement-entries.index', [
            'entries' => $entries,
            'summary' => $summary,
            'withoutReceipt' => $withoutReceipt,
            'pendingScans' => BankReceiptScan::where('status', BankReceiptScan::STATUS_PENDING)->count(),
            'tiles' => $this->tiles($summary),
            'filters' => $filters,
        ] + $this->options());
    }

    /**
     * Type in one bank line by hand (a line the statement file does not have, or a quick correction).
     */
    public function create(Request $request): View
    {
        // arriving from the scan inbox: the scan pre-fills what is known and is attached when the entry is saved
        $scan = BankReceiptScan::query()->where('status', BankReceiptScan::STATUS_PENDING)->find($request->old('scan_id', $request->query('scan_id')));

        return view('bank-statement-entries.create', [
            'scan' => $scan,
            'defaults' => [
                'bank_account_id' => $request->old('bank_account_id', $request->query('bank_account_id', $scan?->bank_account_id)),
                'posting_date' => $request->old('posting_date', $request->query('posting_date', $scan?->scan_date->toDateString() ?? now()->toDateString())),
                'direction' => $request->old('direction', $request->query('direction', $scan?->direction ?? 'credit')),
                'amount' => $request->old('amount', $scan?->amount),
                'description' => $request->old('description', $scan?->note),
            ],
        ] + $this->options(true));
    }

    public function store(
        StoreBankStatementEntryRequest $request,
        BankEntryClassifier $classifier,
        BankEntryAttachmentService $attachments,
        BankEntryApprovalService $approvals,
        BankApprovalRules $rules,
        BankReceiptScanService $scans,
    ): RedirectResponse {
        $data = $request->validated();
        $debit = $data['direction'] === 'debit' ? (float) $data['amount'] : 0.0;
        $credit = $data['direction'] === 'credit' ? (float) $data['amount'] : 0.0;

        $duplicates = $this->possibleDuplicates((int) $data['bank_account_id'], $data['posting_date'], $data['reference'] ?? null, $debit, $credit);

        if ($duplicates->isNotEmpty() && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->with('possible_duplicates', $duplicates->map(fn (BankStatementEntry $e) => [
                'id' => $e->id,
                'text' => $e->posting_date->format('d-m-Y').' · '.($e->reference ?: '—').' · '.number_format($e->amount, 2).' · '.$e->status_label,
            ])->all());
        }

        $entry = DB::transaction(function () use ($request, $data, $debit, $credit, $classifier, $attachments) {
            $user = $request->user();

            $entry = BankStatementEntry::create([
                'bank_account_id' => $data['bank_account_id'],
                'posting_date' => $data['posting_date'],
                'value_date' => $data['value_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'narration' => $data['narration'] ?? null,
                'description' => $data['description'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'party_name_in_statement' => $data['party_name_in_statement'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'bank_entry_category_id' => $data['bank_entry_category_id'] ?? null,
                'classification_source' => 'original',
                'source_statement' => 'Manual entry',
                'status' => BankStatementEntry::STATUS_UNCLASSIFIED,
                'dedupe_hash' => $this->uniqueHash((int) $data['bank_account_id'], $data['posting_date'], $data['reference'] ?? null, $debit, $credit, $data['narration'] ?? null),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Aliases and rules fill in what the person left empty; what they chose is never overwritten.
            if ($entry->bank_entry_category_id) {
                $entry->forceFill(['status' => BankStatementEntry::STATUS_CLASSIFIED, 'classified_by' => $user->id, 'classified_at' => now()])->save();
            } else {
                $classifier->classify($entry);
            }

            BankEntryAuditLog::record($entry, 'created_manually', null, $entry->only(['posting_date', 'debit', 'credit', 'reference']), $user->id);

            if ($request->hasFile('files')) {
                $attachments->attach($entry, $request->file('files'), $request->input('label', 'receipt'), $request->input('attachment_description'), $user);
            }

            return $entry;
        });

        if ($request->filled('scan_id')) {
            $scan = BankReceiptScan::find($request->integer('scan_id'));

            if ($scan && $scan->status === BankReceiptScan::STATUS_PENDING) {
                $scans->link($scan, $entry, $request->user());
            }
        }

        $after = $request->input('after', 'save');
        $message = 'Entry saved.';

        if ($after === 'submit') {
            if (! $request->user()->can('bank-statement-entry-submit')) {
                $message .= ' You do not have permission to submit it, so it was left for the maker.';
            } else {
                try {
                    $entry = $this->submitEntry($entry->refresh(), $request->user(), $approvals, $rules);
                    $message = $entry->status === BankStatementEntry::STATUS_POSTED ? "Entry saved and posted. Receipt {$entry->receipt_no}." : 'Entry saved and sent for approval.';
                } catch (BankRegisterException $e) {
                    return redirect()->route('bank-statement-entries.show', $entry)->with('success', 'Entry saved.')->with('warning', 'Not submitted: '.$e->getMessage());
                }
            }
        }

        if ($after === 'another') {
            return redirect()->route('bank-statement-entries.create', [
                'bank_account_id' => $entry->bank_account_id,
                'posting_date' => $entry->posting_date->toDateString(),
                'direction' => $entry->direction,
            ])->with('success', "{$message} Add the next one below.");
        }

        return redirect()->route('bank-statement-entries.show', $entry)->with('success', $message);
    }

    public function show(BankStatementEntry $bankStatementEntry, BankApprovalRules $rules): View
    {
        return view('bank-statement-entries.show', $this->detailData($bankStatementEntry, $rules));
    }

    /**
     * The same detail panel as the full page, loaded into the side drawer of the list.
     */
    public function drawer(BankStatementEntry $bankStatementEntry, BankApprovalRules $rules): View
    {
        return view('bank-statement-entries.partials.detail', $this->detailData($bankStatementEntry, $rules));
    }

    public function edit(BankStatementEntry $bankStatementEntry): View|RedirectResponse
    {
        if ($bankStatementEntry->isLocked()) {
            return redirect()->route('bank-statement-entries.show', $bankStatementEntry)
                ->with('error', 'A posted entry is part of the books and cannot be edited. Reverse it first.');
        }

        return view('bank-statement-entries.edit', ['entry' => $bankStatementEntry] + $this->options(true));
    }

    public function update(BankStatementEntryRequest $request, BankStatementEntry $bankStatementEntry, BankEntryApprovalService $approvals): RedirectResponse
    {
        if ($bankStatementEntry->isLocked()) {
            return back()->with('error', 'A posted entry cannot be edited. Reverse it first.');
        }

        $data = $request->validated();
        $isManual = $bankStatementEntry->import_id === null && $request->filled('amount');
        $data['direction'] ??= $bankStatementEntry->direction;
        $old = $bankStatementEntry->only(['description', 'company_id', 'bank_entry_category_id', 'needs_review', 'posting_date', 'debit', 'credit', 'reference', 'party_name_in_statement', 'narration']);

        DB::transaction(function () use ($bankStatementEntry, $data, $request, $approvals, $old, $isManual) {
            $bankStatementEntry->fill([
                'description' => $data['description'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'bank_entry_category_id' => $data['bank_entry_category_id'] ?? null,
                'needs_review' => $request->boolean('needs_review'),
                'updated_by' => $request->user()->id,
            ]);

            if ($isManual) {
                $debit = $data['direction'] === 'debit' ? (float) $data['amount'] : 0.0;
                $credit = $data['direction'] === 'credit' ? (float) $data['amount'] : 0.0;
                $bankStatementEntry->fill([
                    'posting_date' => $data['posting_date'] ?? $bankStatementEntry->posting_date,
                    'value_date' => $data['value_date'] ?? null,
                    'debit' => $debit,
                    'credit' => $credit,
                    'reference' => $data['reference'] ?? null,
                    'party_name_in_statement' => $data['party_name_in_statement'] ?? null,
                    'narration' => $data['narration'] ?? null,
                ]);
                $bankStatementEntry->dedupe_hash = $this->uniqueHash(
                    $bankStatementEntry->bank_account_id,
                    $bankStatementEntry->posting_date->toDateString(),
                    $bankStatementEntry->reference,
                    $debit,
                    $credit,
                    $bankStatementEntry->narration,
                    $bankStatementEntry->id,
                );
            }

            $this->syncClassificationStatus($bankStatementEntry, $request->user()->id);
            $bankStatementEntry->save();

            $changed = $old['company_id'] !== $bankStatementEntry->company_id
                || $old['bank_entry_category_id'] !== $bankStatementEntry->bank_entry_category_id
                || (float) $old['debit'] !== (float) $bankStatementEntry->debit
                || (float) $old['credit'] !== (float) $bankStatementEntry->credit
                || ! $old['posting_date']->equalTo($bankStatementEntry->posting_date);

            if ($changed) {
                $approvals->resetApproval($bankStatementEntry, $request->user()->id);
            }

            BankEntryAuditLog::record($bankStatementEntry, 'edited', $old, $bankStatementEntry->only(array_keys($old)), $request->user()->id);
        });

        return redirect()->route('bank-statement-entries.show', $bankStatementEntry)->with('success', 'Entry saved.');
    }

    public function destroy(BankStatementEntry $bankStatementEntry): RedirectResponse
    {
        if ($bankStatementEntry->isLocked() || $bankStatementEntry->status === BankStatementEntry::STATUS_PENDING_APPROVAL) {
            return back()->with('error', 'Only an entry that is not posted or waiting for approval can be deleted.');
        }

        $bankStatementEntry->delete();

        return redirect()->route('bank-statement-entries.index')->with('success', 'Entry deleted.');
    }

    public function export(Request $request, BankStatementSearch $search): BinaryFileResponse
    {
        return Excel::download(
            new BankEntriesExport($search->query((array) $request->input('filter', []))),
            'bank-entries-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * Give the ticked entries one company and/or category, optionally remembering it for next time.
     */
    public function bulkClassify(BankBulkClassifyRequest $request, BankEntryClassifier $classifier): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['company_id']) && empty($data['bank_entry_category_id'])) {
            return back()->with('error', 'Choose a company, a category, or both.');
        }

        $changed = 0;
        $remembered = collect();

        DB::transaction(function () use ($data, $request, &$changed, &$remembered, $classifier) {
            BankStatementEntry::query()->whereIn('id', $data['ids'])->get()->each(function (BankStatementEntry $entry) use ($data, $request, &$changed, &$remembered) {
                if ($entry->isLocked() || $entry->status === BankStatementEntry::STATUS_PENDING_APPROVAL) {
                    return;
                }

                $old = $entry->only(['company_id', 'bank_entry_category_id']);
                $entry->company_id = $data['company_id'] ?? $entry->company_id;
                $entry->bank_entry_category_id = $data['bank_entry_category_id'] ?? $entry->bank_entry_category_id;
                $entry->updated_by = $request->user()->id;
                $this->syncClassificationStatus($entry, $request->user()->id);
                $entry->save();
                BankEntryAuditLog::record($entry, 'bulk_classified', $old, $entry->only(array_keys($old)), $request->user()->id);
                $changed++;

                if ($entry->party_name_in_statement) {
                    $remembered->push($entry->party_name_in_statement);
                }
            });

            if ($request->boolean('remember_alias')) {
                $this->rememberParties($remembered->unique(), $data, $request->user());
                $classifier->refresh();
            }
        });

        return back()->with('success', "{$changed} entries updated.");
    }

    /**
     * Run aliases, rules and the cheque register over every unclassified entry.
     */
    public function autoClassify(BankEntryClassifier $classifier): RedirectResponse
    {
        $changed = $classifier->classifyPending();

        return back()->with('success', "{$changed} entries were classified automatically.");
    }

    public function flag(BankStatementEntry $bankStatementEntry): RedirectResponse
    {
        $bankStatementEntry->update(['needs_review' => ! $bankStatementEntry->needs_review]);

        return back()->with('success', $bankStatementEntry->needs_review ? 'Entry flagged for review.' : 'Review flag removed.');
    }

    public function submit(Request $request, BankStatementEntry $bankStatementEntry, BankEntryApprovalService $approvals, BankApprovalRules $rules): RedirectResponse
    {
        try {
            $entry = $this->submitEntry($bankStatementEntry, $request->user(), $approvals, $rules);
        } catch (BankRegisterException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $entry->status === BankStatementEntry::STATUS_POSTED
            ? "Entry posted. Receipt {$entry->receipt_no}."
            : 'Entry sent for approval.');
    }

    public function bulkSubmit(Request $request, BankEntryApprovalService $approvals, BankApprovalRules $rules): RedirectResponse
    {
        $ids = array_slice((array) $request->input('ids', []), 0, 500);
        $done = 0;
        $failed = [];

        foreach (BankStatementEntry::query()->whereIn('id', $ids)->get() as $entry) {
            try {
                $this->submitEntry($entry, $request->user(), $approvals, $rules);
                $done++;
            } catch (BankRegisterException $e) {
                $failed[] = "#{$entry->id}: {$e->getMessage()}";
            }
        }

        $redirect = back()->with('success', "{$done} entries submitted.");

        return $failed ? $redirect->with('warning', implode("\n", array_slice($failed, 0, 5))) : $redirect;
    }

    public function reverse(Request $request, BankStatementEntry $bankStatementEntry, BankEntryPostingService $posting): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $posting->reverse($bankStatementEntry, $request->user(), $request->string('reason')->value());
        } catch (BankRegisterException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Entry reversed. A reversing journal entry was posted and the line can be corrected and submitted again.');
    }

    /**
     * Lines already in the register with the same account, date, reference and amount.
     *
     * @return Collection<int, BankStatementEntry>
     */
    private function possibleDuplicates(int $bankAccountId, string $date, ?string $reference, float $debit, float $credit)
    {
        return BankStatementEntry::query()
            ->where('bank_account_id', $bankAccountId)
            ->whereDate('posting_date', $date)
            ->where('debit', $debit)
            ->where('credit', $credit)
            ->when($reference, fn ($q) => $q->where('reference', $reference))
            ->limit(5)
            ->get();
    }

    /**
     * A dedupe hash that does not collide with a line already saved (a second identical manual line is legitimate).
     */
    private function uniqueHash(int $bankAccountId, string $date, ?string $reference, float $debit, float $credit, ?string $narration, ?int $ignoreId = null): string
    {
        $occurrence = 1;

        do {
            $hash = BankStatementEntry::makeDedupeHash($bankAccountId, $date, $reference, $debit, $credit, $narration, $occurrence++);
            $taken = BankStatementEntry::withTrashed()->where('dedupe_hash', $hash)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists();
        } while ($taken);

        return $hash;
    }

    /**
     * A person below the approval limit may only post if they hold the post permission.
     */
    private function submitEntry(BankStatementEntry $entry, User $user, BankEntryApprovalService $approvals, BankApprovalRules $rules): BankStatementEntry
    {
        if ($rules->requiredLevel($entry) === 0 && ! $user->can('bank-statement-entry-post')) {
            throw new BankRegisterException('This entry is below the approval limit, so it is posted directly, but you do not have the post permission.');
        }

        return $approvals->submit($entry, $user);
    }

    /**
     * Classified once a category is chosen; back to unclassified if the category is removed.
     */
    private function syncClassificationStatus(BankStatementEntry $entry, int $userId): void
    {
        if ($entry->bank_entry_category_id && in_array($entry->status, [BankStatementEntry::STATUS_UNCLASSIFIED, BankStatementEntry::STATUS_FLAGGED], true)) {
            $entry->status = BankStatementEntry::STATUS_CLASSIFIED;
            $entry->classified_by = $userId;
            $entry->classified_at = now();
        } elseif (! $entry->bank_entry_category_id && $entry->status === BankStatementEntry::STATUS_CLASSIFIED) {
            $entry->status = BankStatementEntry::STATUS_UNCLASSIFIED;
        }
    }

    /**
     * Turn a bulk tagging into an alias (party => company) and a rule (party => category) for next time.
     *
     * @param  Collection<int, string>  $parties
     * @param  array<string, mixed>  $data
     */
    private function rememberParties($parties, array $data, User $user): void
    {
        foreach ($parties as $party) {
            if (! empty($data['company_id'])) {
                BankPartyAlias::firstOrCreate(
                    ['alias_name' => BankPartyAlias::normalize($party)],
                    ['canonical_name' => $party, 'company_id' => $data['company_id'], 'is_active' => true]
                );
            }

            if (! empty($data['bank_entry_category_id'])) {
                BankEntryRule::firstOrCreate(
                    ['match_field' => 'party', 'match_type' => 'equals', 'match_value' => $party, 'direction' => 'any'],
                    [
                        'name' => "Remembered: {$party}",
                        'company_id' => $data['company_id'] ?? null,
                        'bank_entry_category_id' => $data['bank_entry_category_id'],
                        'priority' => 100,
                        'is_active' => true,
                        'created_by' => $user->id,
                    ]
                );
            }
        }
    }

    /**
     * @param  array<string, array{count: int, amount: float}>  $summary
     * @return array<string, mixed>
     */
    private function tiles(array $summary): array
    {
        $amount = fn (string ...$statuses) => array_sum(array_map(fn (string $s) => $summary[$s]['amount'] ?? 0, $statuses));
        $count = fn (string ...$statuses) => array_sum(array_map(fn (string $s) => $summary[$s]['count'] ?? 0, $statuses));

        return [
            'unclassified' => ['count' => $count('unclassified', 'rejected'), 'amount' => $amount('unclassified', 'rejected')],
            'pending' => ['count' => $count('pending_approval'), 'amount' => $amount('pending_approval')],
            'unreconciled' => ['count' => $count('posted'), 'amount' => $amount('posted')],
            'reconciled' => ['count' => $count('reconciled'), 'amount' => $amount('reconciled')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(BankStatementEntry $entry, BankApprovalRules $rules): array
    {
        $entry->load([
            'bankAccount', 'company', 'category', 'alias', 'journalEntry', 'attachments.uploader',
            'approvals.user', 'auditLogs.user', 'submitter', 'approver', 'rejecter', 'poster', 'reconciliation',
        ]);

        return [
            'entry' => $entry,
            'requiredLevel' => $rules->requiredLevel($entry),
            'approvedCount' => $rules->approvedLevels($entry)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(bool $forPosting = false): array
    {
        return [
            // the register and its filters list every account that has entries; a new or edited entry only the active ones
            'bankAccounts' => BankAccount::query()
                ->when($forPosting, fn ($q) => $q->where('is_active', true))
                ->unless($forPosting, fn ($q) => $q->where(fn ($w) => $w->where('is_active', true)->orWhereIn('id', BankStatementEntry::withTrashed()->select('bank_account_id'))))
                ->orderBy('account_name')->get(['id', 'account_name', 'account_number']),
            'companies' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'categories' => BankEntryCategory::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'type']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => BankStatementEntry::STATUSES,
        ];
    }
}

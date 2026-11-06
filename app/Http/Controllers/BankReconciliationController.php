<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankReconciliationRequest;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\BankStatementEntry;
use App\Models\JournalEntryDetail;
use App\Services\BankRegister\BankReconciliationService;
use App\Services\BankRegister\BankRegisterException;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class BankReconciliationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-reconciliation-list', only: ['index', 'show']),
            new Middleware('can:bank-reconciliation-create', only: ['create', 'store']),
            new Middleware('can:bank-reconciliation-edit', only: ['autoMatch', 'match', 'unmatch']),
            new Middleware('can:bank-reconciliation-complete', only: ['complete']),
            new Middleware('can:bank-reconciliation-cancel', only: ['cancel']),
        ];
    }

    public function index(): View
    {
        $reconciliations = BankReconciliation::query()->with(['bankAccount', 'creator'])->latest('period_to')->paginate(25);

        return view('bank-reconciliations.index', ['reconciliations' => $reconciliations]);
    }

    public function create(): View
    {
        return view('bank-reconciliations.create', [
            'bankAccounts' => BankAccount::active()->orderBy('account_name')->get(['id', 'account_name', 'account_number']),
        ]);
    }

    public function store(BankReconciliationRequest $request, BankReconciliationService $service): RedirectResponse
    {
        $data = $request->validated();

        try {
            $reconciliation = $service->start(
                BankAccount::findOrFail($data['bank_account_id']),
                Carbon::parse($data['period_from']),
                Carbon::parse($data['period_to']),
                (float) $data['statement_balance'],
                (float) $data['opening_balance'],
                $data['notes'] ?? null,
                $request->user(),
            );
        } catch (BankRegisterException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-reconciliations.show', $reconciliation)->with('success', 'Reconciliation started. Posted lines were matched automatically.');
    }

    public function show(BankReconciliation $bankReconciliation, BankReconciliationService $service): View
    {
        $bankReconciliation->load(['bankAccount', 'creator', 'completer', 'items.entry', 'items.journalEntryDetail.journalEntry']);
        $totals = $service->refreshTotals($bankReconciliation);

        return view('bank-reconciliations.show', [
            'reconciliation' => $bankReconciliation->refresh()->load(['bankAccount', 'items.entry', 'items.journalEntryDetail.journalEntry']),
            'totals' => $totals,
            'suggestions' => $bankReconciliation->status === BankReconciliation::STATUS_IN_PROGRESS
                ? $totals['unmatched_entries']->mapWithKeys(fn (BankStatementEntry $e) => [$e->id => $service->suggestions($bankReconciliation, $e)])
                : collect(),
        ]);
    }

    public function autoMatch(Request $request, BankReconciliation $bankReconciliation, BankReconciliationService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->autoMatch($bankReconciliation, $request->user()), 'Auto-match finished.');
    }

    public function match(Request $request, BankReconciliation $bankReconciliation, BankReconciliationService $service): RedirectResponse
    {
        $data = $request->validate([
            'bank_statement_entry_id' => ['required', 'exists:bank_statement_entries,id'],
            'journal_entry_detail_id' => ['required', 'exists:journal_entry_details,id'],
        ]);

        return $this->attempt(fn () => $service->match(
            $bankReconciliation,
            BankStatementEntry::findOrFail($data['bank_statement_entry_id']),
            JournalEntryDetail::findOrFail($data['journal_entry_detail_id']),
            $request->user(),
        ), 'Lines matched.');
    }

    public function unmatch(Request $request, BankReconciliation $bankReconciliation, BankReconciliationItem $item, BankReconciliationService $service): RedirectResponse
    {
        abort_unless($item->bank_reconciliation_id === $bankReconciliation->id, 404);

        return $this->attempt(fn () => $service->unmatch($bankReconciliation, $item, $request->user()), 'Match removed.');
    }

    public function complete(Request $request, BankReconciliation $bankReconciliation, BankReconciliationService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->complete($bankReconciliation, $request->user()), 'Reconciliation completed. The period is now locked.');
    }

    public function cancel(Request $request, BankReconciliation $bankReconciliation, BankReconciliationService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->cancel($bankReconciliation, $request->user()), 'Reconciliation cancelled.');
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (BankRegisterException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}

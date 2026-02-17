<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdateJournalEntryRequest;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class JournalEntryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:journal-entry-list', only: ['index', 'show']),
            new Middleware('permission:journal-entry-create', only: ['create', 'store']),
            new Middleware('permission:journal-entry-edit', only: ['edit', 'update']),
            new Middleware('permission:journal-entry-delete', only: ['destroy']),
            new Middleware('permission:journal-entry-post', only: ['post', 'recordCashReceipt', 'recordCashPayment', 'recordOpeningBalance']),
            new Middleware('permission:journal-entry-reverse', only: ['reverse']),
        ];
    }

    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = JournalEntry::query()
            ->with(['currency'])
            ->withSum('details as total_debit', 'debit')
            ->withSum('details as total_credit', 'credit')
            ->latest('entry_date');

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        if ($reference = $request->input('filter.reference')) {
            $query->where('reference', 'like', '%'.$reference.'%');
        }

        if ($dateFrom = $request->input('filter.date_from')) {
            $query->whereDate('entry_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('filter.date_to')) {
            $query->whereDate('entry_date', '<=', $dateTo);
        }

        $entries = $query->paginate(20)->withQueryString();

        $statusOptions = [
            'draft' => 'Draft',
            'posted' => 'Posted',
            'void' => 'Void',
        ];

        return view('journal-entries.index', compact('entries', 'statusOptions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $accounts = ChartOfAccount::query()
            ->where('is_group', false)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);

        $currencies = Currency::query()
            ->where('is_active', true)
            ->orderBy('currency_code')
            ->get(['id', 'currency_code', 'currency_name', 'is_base_currency']);

        $costCenters = CostCenter::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $defaultCurrencyId = $currencies->firstWhere('is_base_currency', true)->id
            ?? $currencies->first()->id
            ?? null;

        return view('journal-entries.create', compact('accounts', 'currencies', 'costCenters', 'defaultCurrencyId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJournalEntryRequest $request)
    {
        $result = $this->accountingService->createJournalEntry($request->validated());

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return back()
            ->withInput()
            ->with('error', $result['message']);
    }

    /**
     * Display the specified resource.
     */
    public function show(JournalEntry $journalEntry)
    {
        $journalEntry->load([
            'details' => fn ($query) => $query->orderBy('line_no'),
            'details.account',
            'details.costCenter',
            'currency',
            'accountingPeriod',
            'attachments',
        ]);

        return view('journal-entries.show', compact('journalEntry'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JournalEntry $journalEntry)
    {
        if ($journalEntry->status === 'posted') {
            return redirect()
                ->route('journal-entries.show', $journalEntry->id)
                ->with('error', 'Posted entries cannot be edited. Create a reversing entry instead.');
        }

        $journalEntry->load([
            'details' => fn ($query) => $query->orderBy('line_no'),
            'details.account',
            'details.costCenter',
        ]);

        $accounts = ChartOfAccount::query()
            ->where('is_group', false)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);

        $currencies = Currency::query()
            ->where('is_active', true)
            ->orderBy('currency_code')
            ->get(['id', 'currency_code', 'currency_name', 'is_base_currency']);

        $costCenters = CostCenter::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('journal-entries.edit', compact('journalEntry', 'accounts', 'currencies', 'costCenters'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry)
    {
        if ($journalEntry->status === 'posted') {
            return redirect()
                ->route('journal-entries.show', $journalEntry->id)
                ->with('error', 'Posted entries cannot be edited.');
        }

        $result = $this->accountingService->updateJournalEntry($journalEntry, $request->validated());

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return back()
            ->withInput()
            ->with('error', $result['message']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JournalEntry $journalEntry)
    {
        if ($journalEntry->status === 'posted') {
            return redirect()
                ->route('journal-entries.index')
                ->with('error', 'Posted entries cannot be deleted. Create a reversing entry instead.');
        }

        $journalEntry->delete();

        return redirect()
            ->route('journal-entries.index')
            ->with('success', 'Draft journal entry deleted successfully.');
    }

    /**
     * Post a draft journal entry.
     */
    public function post(JournalEntry $journalEntry)
    {
        $result = $this->accountingService->postJournalEntry($journalEntry->id);

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Create a reversing entry.
     */
    public function reverse(Request $request, JournalEntry $journalEntry)
    {
        $result = $this->accountingService->reverseJournalEntry(
            $journalEntry->id,
            $request->input('description')
        );

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Quick transaction helpers
     */
    public function recordCashReceipt(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'revenue_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'description' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:191'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'auto_post' => ['sometimes', 'boolean'],
        ]);

        $result = $this->accountingService->recordCashReceipt(
            (float) $validated['amount'],
            (int) $validated['revenue_account_id'],
            $validated['description'],
            $validated
        );

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return back()->withInput()->with('error', $result['message']);
    }

    public function recordCashPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'expense_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'description' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:191'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'auto_post' => ['sometimes', 'boolean'],
        ]);

        $result = $this->accountingService->recordCashPayment(
            (float) $validated['amount'],
            (int) $validated['expense_account_id'],
            $validated['description'],
            $validated
        );

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return back()->withInput()->with('error', $result['message']);
    }

    public function recordOpeningBalance(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'entry_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:191'],
            'auto_post' => ['sometimes', 'boolean'],
        ]);

        $result = $this->accountingService->recordOpeningBalance(
            (float) $validated['amount'],
            $validated['description'] ?? 'Opening balance - Owner capital',
            $validated
        );

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return back()->withInput()->with('error', $result['message']);
    }
}

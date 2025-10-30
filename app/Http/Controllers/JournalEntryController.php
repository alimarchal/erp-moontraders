<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdateJournalEntryRequest;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\CostCenter;
use App\Services\AccountingService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $entries = JournalEntry::with(['details.account', 'currency', 'costCenter', 'accountingPeriod'])
            ->latest('entry_date')
            ->paginate(20);

        return view('journal-entries.index', compact('entries'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $accounts = ChartOfAccount::where('is_leaf', true)
            ->orderBy('code')
            ->get();

        $currencies = Currency::where('is_active', true)->get();
        $costCenters = CostCenter::where('is_active', true)->get();

        return view('journal-entries.create', compact('accounts', 'currencies', 'costCenters'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJournalEntryRequest $request)
    {
        $result = $this->accountingService->createJournalEntry($request->all());

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $result['message']);
    }

    /**
     * Display the specified resource.
     */
    public function show(JournalEntry $journalEntry)
    {
        $journalEntry->load(['details.account', 'currency', 'costCenter', 'accountingPeriod']);

        return view('journal-entries.show', compact('journalEntry'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JournalEntry $journalEntry)
    {
        // Only allow editing draft entries
        if ($journalEntry->status === 'posted') {
            return redirect()
                ->route('journal-entries.show', $journalEntry)
                ->with('error', 'Posted entries cannot be edited. Create a reversing entry instead.');
        }

        $journalEntry->load('details');

        $accounts = ChartOfAccount::where('is_leaf', true)
            ->orderBy('code')
            ->get();

        $currencies = Currency::where('is_active', true)->get();
        $costCenters = CostCenter::where('is_active', true)->get();

        return view('journal-entries.edit', compact('journalEntry', 'accounts', 'currencies', 'costCenters'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry)
    {
        // Only allow updating draft entries
        if ($journalEntry->status === 'posted') {
            return redirect()
                ->route('journal-entries.show', $journalEntry)
                ->with('error', 'Posted entries cannot be edited.');
        }

        // Delete existing details and recreate (easier than updating)
        $journalEntry->details()->delete();

        $result = $this->accountingService->createJournalEntry(
            array_merge($request->all(), ['entry_id' => $journalEntry->id])
        );

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $journalEntry)
                ->with('success', 'Journal entry updated successfully');
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $result['message']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JournalEntry $journalEntry)
    {
        // Only allow deleting draft entries
        if ($journalEntry->status === 'posted') {
            return redirect()
                ->route('journal-entries.index')
                ->with('error', 'Posted entries cannot be deleted. Create a reversing entry instead.');
        }

        $journalEntry->details()->delete();
        $journalEntry->delete();

        return redirect()
            ->route('journal-entries.index')
            ->with('success', 'Draft journal entry deleted successfully');
    }

    /**
     * Post a draft journal entry
     */
    public function post(JournalEntry $journalEntry)
    {
        $result = $this->accountingService->postJournalEntry($journalEntry->id);

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $journalEntry)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->with('error', $result['message']);
    }

    /**
     * Create a reversing entry
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

        return redirect()
            ->back()
            ->with('error', $result['message']);
    }

    /**
     * Quick transaction helpers
     */
    public function recordCashReceipt(Request $request)
    {
        $result = $this->accountingService->recordCashReceipt(
            $request->input('amount'),
            $request->input('revenue_account_id'),
            $request->input('description'),
            $request->only(['reference', 'cost_center_id', 'auto_post'])
        );

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $result['message']);
    }

    public function recordCashPayment(Request $request)
    {
        $result = $this->accountingService->recordCashPayment(
            $request->input('amount'),
            $request->input('expense_account_id'),
            $request->input('description'),
            $request->only(['reference', 'cost_center_id', 'auto_post'])
        );

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $result['message']);
    }

    public function recordOpeningBalance(Request $request)
    {
        $result = $this->accountingService->recordOpeningBalance(
            $request->input('amount'),
            $request->input('description', 'Opening balance - Owner capital'),
            $request->only(['entry_date', 'reference', 'auto_post'])
        );

        if ($result['success']) {
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $result['message']);
    }
}

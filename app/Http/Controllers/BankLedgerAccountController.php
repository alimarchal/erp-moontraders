<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\JournalEntryDetail;
use App\Services\BankRegister\BankRegisterLedger;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class BankLedgerAccountController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:bank-ledger-account-list')];
    }

    /**
     * Read-only view of the Bank Register's own accounts (code 6000 onwards) with their balances, and which
     * live ledger each bank account is kept apart from.
     */
    public function index(BankRegisterLedger $ledger): View
    {
        $ledger->ensureStructure();
        $accounts = $ledger->accounts();

        $totals = JournalEntryDetail::query()
            ->whereIn('chart_of_account_id', $accounts->pluck('id'))
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
            ->selectRaw('chart_of_account_id, SUM(debit) AS total_debit, SUM(credit) AS total_credit, COUNT(*) AS line_count')
            ->groupBy('chart_of_account_id')
            ->get()
            ->keyBy('chart_of_account_id');

        return view('bank-ledger-accounts.index', [
            'accounts' => $accounts,
            'totals' => $totals,
            'bankAccounts' => BankAccount::with(['chartOfAccount', 'registerLedger'])->orderBy('account_name')->get(),
        ]);
    }
}

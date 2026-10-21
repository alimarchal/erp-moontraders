<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankChequeRequest;
use App\Models\BankAccount;
use App\Models\BankCheque;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BankChequeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-cheque-list', only: ['index']),
            new Middleware('can:bank-cheque-create', only: ['create', 'store']),
            new Middleware('can:bank-cheque-edit', only: ['edit', 'update']),
            new Middleware('can:bank-cheque-delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $cheques = QueryBuilder::for(BankCheque::query()->with(['bankAccount', 'company', 'statementEntry']))
            ->allowedFilters([
                AllowedFilter::partial('cheque_no'),
                AllowedFilter::partial('payee_name'),
                AllowedFilter::exact('bank_account_id'),
                AllowedFilter::exact('direction'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('company_id'),
            ])
            ->orderByDesc('cheque_date')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return view('bank-cheques.index', ['cheques' => $cheques] + $this->options());
    }

    public function create(): View
    {
        return view('bank-cheques.create', $this->options());
    }

    public function store(BankChequeRequest $request): RedirectResponse
    {
        $cheque = BankCheque::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('bank-cheques.index')->with('success', "Cheque {$cheque->cheque_no} saved.");
    }

    public function edit(BankCheque $bankCheque): View
    {
        return view('bank-cheques.edit', ['cheque' => $bankCheque] + $this->options());
    }

    public function update(BankChequeRequest $request, BankCheque $bankCheque): RedirectResponse
    {
        $bankCheque->update($request->validated());

        return redirect()->route('bank-cheques.index')->with('success', "Cheque {$bankCheque->cheque_no} updated.");
    }

    public function destroy(BankCheque $bankCheque): RedirectResponse
    {
        if ($bankCheque->bank_statement_entry_id) {
            return back()->with('error', 'This cheque is matched to a bank statement line and cannot be deleted.');
        }

        $bankCheque->delete();

        return redirect()->route('bank-cheques.index')->with('success', 'Cheque deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'bankAccounts' => BankAccount::active()->orderBy('account_name')->get(['id', 'account_name', 'account_number']),
            'companies' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
        ];
    }
}

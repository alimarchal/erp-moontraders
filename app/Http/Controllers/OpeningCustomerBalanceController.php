<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualOpeningCustomerBalanceRequest;
use App\Http\Requests\UpdateOpeningCustomerBalanceRequest;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Services\AccountingService;
use App\Services\LedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class OpeningCustomerBalanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:opening-customer-balance-list', only: ['index', 'show']),
            new Middleware('permission:opening-customer-balance-create', only: ['create', 'storeManual']),
            new Middleware('permission:opening-customer-balance-edit', only: ['edit', 'update']),
            new Middleware('permission:opening-customer-balance-delete', only: ['destroy']),
            new Middleware('permission:opening-customer-balance-post', only: ['post']),
        ];
    }

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100, 250];

    private const DEFAULT_PER_PAGE = 40;

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $transactions = $this->buildFilteredQuery()
            ->paginate($perPage)
            ->withQueryString();

        return view('opening-customer-balances.index', [
            'transactions' => $transactions,
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'employee_code', 'name', 'supplier_id']),
            'customers' => Customer::where('is_active', true)->orderBy('customer_name')->get(['id', 'customer_code', 'customer_name', 'address', 'city']),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function create()
    {
        return view('opening-customer-balances.create', [
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'employee_code', 'name', 'supplier_id']),
            'customers' => Customer::where('is_active', true)->orderBy('customer_name')->get(['id', 'customer_code', 'customer_name', 'address', 'city']),
        ]);
    }

    public function storeManual(StoreManualOpeningCustomerBalanceRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $employee = Employee::findOrFail($validated['employee_id']);
            $customer = Customer::findOrFail($validated['customer_id']);

            $existing = CustomerEmployeeAccountTransaction::whereHas('account', function ($q) use ($validated) {
                $q->where('customer_id', $validated['customer_id'])
                    ->where('employee_id', $validated['employee_id']);
            })->where('transaction_type', 'opening_balance')->exists();

            if ($existing) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('error', "Opening balance already exists for {$customer->customer_name} with {$employee->name}.");
            }

            $ledgerService = app(LedgerService::class);
            $result = $ledgerService->recordCustomerEmployeeTransaction([
                'customer_id' => $validated['customer_id'],
                'employee_id' => $validated['employee_id'],
                'transaction_date' => $validated['balance_date'],
                'transaction_type' => 'opening_balance',
                'reference_number' => 'OCB-M-'.now()->format('ymd-His'),
                'description' => $validated['description'] ?? "Opening balance for {$customer->customer_name} with {$employee->name}",
                'debit' => $validated['opening_balance'],
                'credit' => 0,
                'notes' => 'Manually created opening customer balance',
            ]);

            if (! $result['success']) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('error', "Failed to record balance: {$result['message']}");
            }

            DB::commit();

            return redirect()
                ->route('opening-customer-balances.index')
                ->with('success', 'Opening balance of '.number_format($validated['opening_balance'], 2)." created for {$customer->customer_name} with {$employee->name}.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating manual opening customer balance', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create opening balance. '.$e->getMessage());
        }
    }

    public function show(CustomerEmployeeAccountTransaction $opening_customer_balance)
    {
        $transaction = $opening_customer_balance;
        $transaction->load(['account.customer', 'account.employee.supplier', 'createdBy', 'postedBy']);

        return view('opening-customer-balances.show', [
            'transaction' => $transaction,
        ]);
    }

    public function edit(CustomerEmployeeAccountTransaction $opening_customer_balance)
    {
        $transaction = $opening_customer_balance;
        $transaction->load(['account.customer', 'account.employee']);

        if ($transaction->isPosted()) {
            return redirect()
                ->route('opening-customer-balances.show', $transaction)
                ->with('error', 'Posted transactions cannot be edited.');
        }

        if ($transaction->transaction_type !== 'opening_balance') {
            return redirect()
                ->route('opening-customer-balances.index')
                ->with('error', 'Only opening balance transactions can be edited here.');
        }

        return view('opening-customer-balances.edit', [
            'transaction' => $transaction,
        ]);
    }

    public function update(UpdateOpeningCustomerBalanceRequest $request, CustomerEmployeeAccountTransaction $opening_customer_balance)
    {
        $transaction = $opening_customer_balance;

        if ($transaction->isPosted()) {
            return redirect()
                ->route('opening-customer-balances.show', $transaction)
                ->with('error', 'Posted transactions cannot be updated.');
        }

        if ($transaction->transaction_type !== 'opening_balance') {
            return redirect()
                ->route('opening-customer-balances.index')
                ->with('error', 'Only opening balance transactions can be edited here.');
        }

        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $transaction->update([
                'transaction_date' => $validated['balance_date'],
                'debit' => $validated['opening_balance'],
                'description' => $validated['description'] ?? $transaction->description,
            ]);

            DB::commit();

            $transaction->load(['account.customer', 'account.employee']);

            return redirect()
                ->route('opening-customer-balances.index')
                ->with('success', 'Opening balance updated to '.number_format($validated['opening_balance'], 2)." for {$transaction->account->customer->customer_name}.");

        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating opening customer balance', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update opening balance. Please try again.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating opening customer balance', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update opening balance. Please try again.');
        }
    }

    public function destroy(CustomerEmployeeAccountTransaction $opening_customer_balance)
    {
        $transaction = $opening_customer_balance;

        if ($transaction->isPosted()) {
            return redirect()
                ->route('opening-customer-balances.show', $transaction)
                ->with('error', 'Posted transactions cannot be deleted.');
        }

        if ($transaction->transaction_type !== 'opening_balance') {
            return redirect()
                ->route('opening-customer-balances.index')
                ->with('error', 'Only opening balance transactions can be deleted here.');
        }

        try {
            $transaction->load(['account.customer', 'account.employee']);
            $customerName = $transaction->account->customer->customer_name;

            $transaction->delete();

            return redirect()
                ->route('opening-customer-balances.index')
                ->with('success', "Opening balance for '{$customerName}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting opening customer balance', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete opening balance. Please try again.');
        }
    }

    public function post(CustomerEmployeeAccountTransaction $opening_customer_balance)
    {
        $transaction = $opening_customer_balance;

        if ($transaction->isPosted()) {
            return back()->with('error', 'This opening balance has already been posted to GL.');
        }

        if ($transaction->transaction_type !== 'opening_balance') {
            return back()->with('error', 'Only opening balance transactions can be posted.');
        }

        DB::beginTransaction();

        try {
            $transaction->load(['account.customer', 'account.employee.supplier']);

            $debtorsAccount = ChartOfAccount::where('account_name', 'Debtors')->first();
            $openingBalanceEquityAccount = ChartOfAccount::where('account_name', 'Opening Balance Equity')->first();

            if (! $debtorsAccount || ! $openingBalanceEquityAccount) {
                DB::rollBack();

                return back()->with('error', 'GL accounts not found. Ensure "Debtors" and "Opening Balance Equity" accounts exist in Chart of Accounts.');
            }

            $amount = (float) $transaction->debit;

            if ($amount <= 0) {
                DB::rollBack();

                return back()->with('error', 'Cannot post a zero or negative balance.');
            }

            $customerName = $transaction->account->customer->customer_name ?? 'Unknown';
            $employeeName = $transaction->account->employee->name ?? 'Unknown';
            $reference = $transaction->reference_number ?? 'OCB-'.$transaction->id;

            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry([
                'entry_date' => $transaction->transaction_date->toDateString(),
                'reference' => $reference,
                'description' => "Opening Customer Balance — {$customerName} (Salesman: {$employeeName})",
                'lines' => [
                    [
                        'line_no' => 1,
                        'account_id' => $debtorsAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Opening balance receivable — {$customerName}",
                        'cost_center_id' => null,
                    ],
                    [
                        'line_no' => 2,
                        'account_id' => $openingBalanceEquityAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => "Opening balance equity — {$customerName}",
                        'cost_center_id' => null,
                    ],
                ],
                'auto_post' => true,
            ]);

            if (! $result['success']) {
                DB::rollBack();

                return back()->with('error', 'Failed to create journal entry: '.$result['message']);
            }

            $transaction->update([
                'journal_entry_id' => $result['data']->id,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('opening-customer-balances.show', $transaction)
                ->with('success', 'Opening balance of '.number_format($amount, 2)." for {$customerName} posted to GL (JE #{$result['data']->id}).");

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error posting opening customer balance to GL', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to post to GL. '.$e->getMessage());
        }
    }

    private function buildFilteredQuery(): QueryBuilder
    {
        return QueryBuilder::for(
            CustomerEmployeeAccountTransaction::query()
                ->where('transaction_type', 'opening_balance')
                ->with(['account.customer', 'account.employee', 'account.employee.supplier', 'createdBy'])
        )
            ->allowedFilters([
                AllowedFilter::callback('supplier_id', function ($query, $value) {
                    $query->whereHas('account.employee', fn ($q) => $q->where('supplier_id', $value));
                }),
                AllowedFilter::callback('employee_id', function ($query, $value) {
                    $query->whereHas('account', fn ($q) => $q->where('employee_id', $value));
                }),
                AllowedFilter::callback('customer_id', function ($query, $value) {
                    $query->whereHas('account', fn ($q) => $q->where('customer_id', $value));
                }),
                AllowedFilter::partial('reference_number'),
            ])
            ->allowedSorts([
                AllowedSort::field('transaction_date'),
                AllowedSort::field('debit'),
                AllowedSort::field('created_at'),
            ])
            ->defaultSort('-created_at');
    }
}

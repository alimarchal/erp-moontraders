<?php

namespace App\Http\Controllers\Reports;

use App\Exports\OpeningCustomerBalanceExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualOpeningCustomerBalanceRequest;
use App\Http\Requests\UpdateOpeningCustomerBalanceRequest;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Services\AccountingService;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class OpeningCustomerBalanceReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-opening-customer-balance', only: ['index']),
            new Middleware('can:opening-customer-balance-create', only: ['store']),
            new Middleware('can:opening-customer-balance-edit', only: ['update']),
            new Middleware('can:opening-customer-balance-delete', only: ['destroy']),
            new Middleware('can:opening-customer-balance-post', only: ['post']),
            new Middleware('can:report-audit-opening-customer-balance', only: ['exportExcel']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = \in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 50;

        $supplierId = $request->input('supplier_id');
        $employeeId = $request->input('employee_id');
        $customerId = $request->input('customer_id');
        $referenceNumber = $request->input('reference_number');
        $postedStatus = $request->input('posted_status');

        $hasFilters = $supplierId || $employeeId || $customerId || $referenceNumber || $postedStatus;

        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name', 'short_name']);
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'employee_code', 'name', 'supplier_id']);
        $customers = Customer::where('is_active', true)->orderBy('customer_name')->get(['id', 'customer_code', 'customer_name', 'address', 'city', 'phone']);

        if (! $hasFilters) {
            $transactions = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage === 'all' ? 1 : (int) $perPage, 1, ['path' => $request->url(), 'query' => $request->query()]);

            return view('reports.opening-customer-balance.index', compact(
                'transactions',
                'suppliers',
                'employees',
                'customers',
                'supplierId',
                'employeeId',
                'customerId',
                'referenceNumber',
                'postedStatus',
                'hasFilters',
                'perPage'
            ) + ['selectedSupplier' => null, 'totalDebit' => 0]);
        }

        $query = CustomerEmployeeAccountTransaction::with(['account.customer', 'account.employee.supplier'])
            ->where('transaction_type', 'opening_balance')
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($supplierId) {
            $query->whereHas('account.employee', fn ($q) => $q->where('supplier_id', $supplierId));
        }

        if ($employeeId) {
            $query->whereHas('account', fn ($q) => $q->where('employee_id', $employeeId));
        }

        if ($customerId) {
            $query->whereHas('account', fn ($q) => $q->where('customer_id', $customerId));
        }

        if ($referenceNumber) {
            $query->where('reference_number', 'like', '%'.$referenceNumber.'%');
        }

        if ($postedStatus === 'posted') {
            $query->whereNotNull('posted_at');
        } elseif ($postedStatus === 'unposted') {
            $query->whereNull('posted_at');
        }

        if ($perPage === 'all') {
            $transactions = $query->get();
            $transactions = new \Illuminate\Pagination\LengthAwarePaginator(
                $transactions,
                $transactions->count(),
                $transactions->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $transactions = $query->paginate((int) $perPage)->withQueryString();
        }

        $totalDebit = (clone $query)->sum('debit');

        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;

        return view('reports.opening-customer-balance.index', compact(
            'transactions',
            'suppliers',
            'employees',
            'customers',
            'supplierId',
            'employeeId',
            'customerId',
            'referenceNumber',
            'postedStatus',
            'selectedSupplier',
            'totalDebit',
            'perPage',
            'hasFilters'
        ));
    }

    public function store(StoreManualOpeningCustomerBalanceRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $employee = Employee::findOrFail($validated['employee_id']);
            $customer = Customer::findOrFail($validated['customer_id']);
            $supplier = $employee->supplier;

            $existing = CustomerEmployeeAccountTransaction::whereHas('account', function ($q) use ($validated) {
                $q->where('customer_id', $validated['customer_id'])
                    ->where('employee_id', $validated['employee_id']);
            })->where('transaction_type', 'opening_balance')->exists();

            if ($existing) {
                DB::rollBack();
                $supplierName = $supplier ? $supplier->supplier_name : 'N/A';

                return back()->withInput()->with('error', "Opening balance already exists for {$customer->customer_name} with {$employee->name} (Supplier: {$supplierName}).");
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

                return back()->withInput()->with('error', "Failed to record balance: {$result['message']}");
            }

            DB::commit();

            return redirect()->back()->with('success', 'Opening balance of '.number_format($validated['opening_balance'], 2)." created for {$customer->customer_name}.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating opening customer balance via report', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->withInput()->with('error', 'Failed to create opening balance. '.$e->getMessage());
        }
    }

    public function update(UpdateOpeningCustomerBalanceRequest $request, CustomerEmployeeAccountTransaction $openingCustomerBalance)
    {
        if ($openingCustomerBalance->isPosted()) {
            return redirect()->back()->with('error', 'Posted transactions cannot be edited.');
        }

        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $openingCustomerBalance->update([
                'transaction_date' => $validated['balance_date'],
                'debit' => $validated['opening_balance'],
                'description' => $validated['description'] ?? $openingCustomerBalance->description,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Opening balance updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating opening customer balance via report', [
                'transaction_id' => $openingCustomerBalance->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->withInput()->with('error', 'Failed to update opening balance. Please try again.');
        }
    }

    public function destroy(CustomerEmployeeAccountTransaction $openingCustomerBalance)
    {
        if ($openingCustomerBalance->isPosted()) {
            return redirect()->back()->with('error', 'Posted transactions cannot be deleted.');
        }

        try {
            $openingCustomerBalance->load(['account.customer']);
            $customerName = $openingCustomerBalance->account->customer->customer_name;

            $openingCustomerBalance->delete();

            return redirect()->back()->with('success', "Opening balance for '{$customerName}' deleted successfully.");
        } catch (\Exception $e) {
            Log::error('Error deleting opening customer balance via report', [
                'transaction_id' => $openingCustomerBalance->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete opening balance. Please try again.');
        }
    }

    public function post(CustomerEmployeeAccountTransaction $openingCustomerBalance): \Illuminate\Http\RedirectResponse
    {
        if ($openingCustomerBalance->isPosted()) {
            return back()->with('error', 'This opening balance has already been posted to GL.');
        }

        if ($openingCustomerBalance->transaction_type !== 'opening_balance') {
            return back()->with('error', 'Only opening balance transactions can be posted.');
        }

        DB::beginTransaction();

        try {
            $openingCustomerBalance->load(['account.customer', 'account.employee.supplier']);

            $debtorsAccount = ChartOfAccount::where('account_name', 'Debtors')->first();
            $openingBalanceEquityAccount = ChartOfAccount::where('account_name', 'Opening Balance Equity')->first();

            if (! $debtorsAccount || ! $openingBalanceEquityAccount) {
                DB::rollBack();

                return back()->with('error', 'GL accounts not found. Ensure "Debtors" and "Opening Balance Equity" accounts exist in Chart of Accounts.');
            }

            $amount = (float) $openingCustomerBalance->debit;

            if ($amount <= 0) {
                DB::rollBack();

                return back()->with('error', 'Cannot post a zero or negative balance.');
            }

            $customerName = $openingCustomerBalance->account->customer->customer_name ?? 'Unknown';
            $employeeName = $openingCustomerBalance->account->employee->name ?? 'Unknown';
            $reference = $openingCustomerBalance->reference_number ?? 'OCB-'.$openingCustomerBalance->id;

            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry([
                'entry_date' => $openingCustomerBalance->transaction_date->toDateString(),
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

            $openingCustomerBalance->update([
                'journal_entry_id' => $result['data']->id,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'Opening balance of '.number_format($amount, 2)." for {$customerName} posted to GL (JE #{$result['data']->id}).");

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error posting opening customer balance to GL via report', [
                'transaction_id' => $openingCustomerBalance->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to post to GL. '.$e->getMessage());
        }
    }

    public function exportExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $query = CustomerEmployeeAccountTransaction::with(['account.customer', 'account.employee.supplier'])
            ->where('transaction_type', 'opening_balance')
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($request->input('supplier_id')) {
            $query->whereHas('account.employee', fn ($q) => $q->where('supplier_id', $request->input('supplier_id')));
        }

        if ($request->input('employee_id')) {
            $query->whereHas('account', fn ($q) => $q->where('employee_id', $request->input('employee_id')));
        }

        if ($request->input('customer_id')) {
            $query->whereHas('account', fn ($q) => $q->where('customer_id', $request->input('customer_id')));
        }

        if ($request->input('reference_number')) {
            $query->where('reference_number', 'like', '%'.$request->input('reference_number').'%');
        }

        if ($request->input('posted_status') === 'posted') {
            $query->whereNotNull('posted_at');
        } elseif ($request->input('posted_status') === 'unposted') {
            $query->whereNull('posted_at');
        }

        return Excel::download(new OpeningCustomerBalanceExport($query), 'opening-customer-balances.xlsx');
    }
}

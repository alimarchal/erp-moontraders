<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CustomerAccountStatementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-customer-account-statement'),
        ];
    }

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 50);
        $canViewAllSuppliers = $this->canViewAllSuppliers();
        $userSupplierId = $this->getUserSupplierScope();
        $requestedSupplierId = $request->input('filter.supplier_id');

        if ($requestedSupplierId && ! $canViewAllSuppliers && (int) $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        $supplierIdFilter = $userSupplierId ?? ($requestedSupplierId ? (int) $requestedSupplierId : null);
        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');
        $transactionType = $request->input('filter.transaction_type');

        $applyTransactionFilters = function ($query) use ($dateFrom, $dateTo, $transactionType): void {
            if ($dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            }

            if ($transactionType) {
                $query->where('transaction_type', $transactionType);
            }
        };

        [$balanceSubquery, $balanceBindings] = $this->balanceSubquery($dateFrom, $dateTo, $transactionType);

        $accountsQuery = CustomerEmployeeAccount::query()
            ->with(['customer', 'employee.supplier'])
            ->withCount(['transactions as transactions_count' => $applyTransactionFilters])
            ->withSum(['transactions as total_debits' => $applyTransactionFilters], 'debit')
            ->withSum(['transactions as total_credits' => $applyTransactionFilters], 'credit');

        if ($supplierIdFilter) {
            $accountsQuery->whereHas('employee', fn ($query) => $query->where('supplier_id', $supplierIdFilter));
        } elseif (! $canViewAllSuppliers) {
            $accountsQuery->whereRaw('1 = 0');
        }

        if ($request->filled('filter.search')) {
            $search = $request->input('filter.search');

            $accountsQuery->where(function ($query) use ($search) {
                $query->where('account_number', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('customer_name', 'like', '%'.$search.'%')
                            ->orWhere('customer_code', 'like', '%'.$search.'%')
                            ->orWhere('business_name', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($request->filled('filter.customer_id')) {
            $accountsQuery->where('customer_id', $request->input('filter.customer_id'));
        }

        if ($request->filled('filter.employee_id')) {
            $accountsQuery->where('employee_id', $request->input('filter.employee_id'));
        }

        if ($request->filled('filter.status')) {
            $accountsQuery->where('status', $request->input('filter.status'));
        }

        if ($dateFrom || $dateTo || $transactionType) {
            $accountsQuery->whereHas('transactions', $applyTransactionFilters);
        }

        if ($request->filled('filter.balance_min')) {
            $accountsQuery->whereRaw("$balanceSubquery >= ?", [...$balanceBindings, $request->input('filter.balance_min')]);
        }

        if ($request->filled('filter.balance_max')) {
            $accountsQuery->whereRaw("$balanceSubquery <= ?", [...$balanceBindings, $request->input('filter.balance_max')]);
        }

        $accountsQuery->orderByRaw("$balanceSubquery DESC", $balanceBindings)
            ->orderByDesc('customer_employee_accounts.id');

        $accounts = $this->paginate($accountsQuery, $perPage, $request);

        $suppliers = Supplier::query()
            ->whereHas('employees.customerAccounts')
            ->when($supplierIdFilter, fn ($query) => $query->where('id', $supplierIdFilter))
            ->when(! $canViewAllSuppliers && ! $supplierIdFilter, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);

        $employees = Employee::query()
            ->whereHas('customerAccounts')
            ->when($supplierIdFilter, fn ($query) => $query->where('supplier_id', $supplierIdFilter))
            ->when(! $canViewAllSuppliers && ! $supplierIdFilter, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name', 'supplier_id']);

        $customers = Customer::query()
            ->whereHas('employeeAccounts', function ($query) use ($supplierIdFilter, $canViewAllSuppliers) {
                if ($supplierIdFilter) {
                    $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('supplier_id', $supplierIdFilter));
                } elseif (! $canViewAllSuppliers) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy('customer_name')
            ->get(['id', 'customer_name', 'customer_code']);

        $transactionTypes = CustomerEmployeeAccountTransaction::query()
            ->distinct()
            ->whereNull('deleted_at')
            ->orderBy('transaction_type')
            ->pluck('transaction_type');

        return view('reports.customer-account-statement.index', [
            'accounts' => $accounts,
            'suppliers' => $suppliers,
            'employees' => $employees,
            'customers' => $customers,
            'transactionTypes' => $transactionTypes,
            'supplierIdFilter' => $supplierIdFilter,
            'canViewAllSuppliers' => $canViewAllSuppliers,
        ]);
    }

    public function show(Request $request, CustomerEmployeeAccount $customerEmployeeAccount): View
    {
        $this->authorizeAccountAccess($customerEmployeeAccount);

        $perPage = $this->resolvePerPage($request, 100);
        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');

        $customerEmployeeAccount->load(['customer', 'employee.supplier']);

        $applyFilters = function ($query) use ($request, $dateFrom, $dateTo): void {
            if ($dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            }

            if ($request->filled('filter.transaction_type')) {
                $query->where('transaction_type', $request->input('filter.transaction_type'));
            }

            if ($request->filled('filter.reference_number')) {
                $query->where('reference_number', 'like', '%'.$request->input('filter.reference_number').'%');
            }

            if ($request->filled('filter.invoice_number')) {
                $query->where('invoice_number', 'like', '%'.$request->input('filter.invoice_number').'%');
            }

            if ($request->filled('filter.description')) {
                $query->where('description', 'like', '%'.$request->input('filter.description').'%');
            }

            if ($request->filled('filter.payment_method')) {
                $query->where('payment_method', $request->input('filter.payment_method'));
            }

            if ($request->filled('filter.amount_min')) {
                $query->where(function ($amountQuery) use ($request) {
                    $amountQuery->where('debit', '>=', $request->input('filter.amount_min'))
                        ->orWhere('credit', '>=', $request->input('filter.amount_min'));
                });
            }

            if ($request->filled('filter.amount_max')) {
                $query->where(function ($amountQuery) use ($request) {
                    $amountQuery->where(function ($debitQuery) use ($request) {
                        $debitQuery->where('debit', '>', 0)
                            ->where('debit', '<=', $request->input('filter.amount_max'));
                    })->orWhere(function ($creditQuery) use ($request) {
                        $creditQuery->where('credit', '>', 0)
                            ->where('credit', '<=', $request->input('filter.amount_max'));
                    });
                });
            }
        };

        $entriesQuery = $customerEmployeeAccount->transactions()
            ->with(['salesSettlement', 'bankAccount'])
            ->where($applyFilters)
            ->orderBy('transaction_date')
            ->orderBy('id');

        $entries = $this->paginate($entriesQuery, $perPage, $request);
        $openingBalance = $this->openingBalance($customerEmployeeAccount, $dateFrom);
        $balanceBeforePage = $this->balanceBeforePage($customerEmployeeAccount, $request, $entries, $openingBalance);
        $runningBalance = $balanceBeforePage;

        $entries->getCollection()->transform(function (CustomerEmployeeAccountTransaction $entry) use (&$runningBalance) {
            $entry->row_opening_balance = $runningBalance;
            $runningBalance += (float) $entry->debit - (float) $entry->credit;
            $entry->running_balance = $runningBalance;

            return $entry;
        });

        $totalsQuery = $customerEmployeeAccount->transactions()
            ->where($applyFilters)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debits, COALESCE(SUM(credit), 0) as total_credits')
            ->first();

        $summary = [
            'opening_balance' => $openingBalance,
            'total_debits' => (float) ($totalsQuery->total_debits ?? 0),
            'total_credits' => (float) ($totalsQuery->total_credits ?? 0),
            'closing_balance' => $openingBalance + (float) ($totalsQuery->total_debits ?? 0) - (float) ($totalsQuery->total_credits ?? 0),
        ];

        $transactionTypes = CustomerEmployeeAccountTransaction::query()
            ->where('customer_employee_account_id', $customerEmployeeAccount->id)
            ->distinct()
            ->whereNull('deleted_at')
            ->orderBy('transaction_type')
            ->pluck('transaction_type');

        $paymentMethods = CustomerEmployeeAccountTransaction::query()
            ->where('customer_employee_account_id', $customerEmployeeAccount->id)
            ->whereNotNull('payment_method')
            ->where('payment_method', '!=', '')
            ->distinct()
            ->whereNull('deleted_at')
            ->orderBy('payment_method')
            ->pluck('payment_method');

        return view('reports.customer-account-statement.show', [
            'account' => $customerEmployeeAccount,
            'entries' => $entries,
            'summary' => $summary,
            'transactionTypes' => $transactionTypes,
            'paymentMethods' => $paymentMethods,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    private function resolvePerPage(Request $request, int $default): int|string
    {
        $perPage = $request->input('per_page', $default);

        return in_array($perPage, [10, 25, 50, 100, 250, 'all'], true) ? $perPage : $default;
    }

    private function paginate($query, int|string $perPage, Request $request): LengthAwarePaginator
    {
        if ($perPage === 'all') {
            $items = $query->get();

            return new LengthAwarePaginator(
                $items,
                $items->count(),
                $items->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return $query->paginate((int) $perPage)->withQueryString();
    }

    private function balanceSubquery(?string $dateFrom, ?string $dateTo, ?string $transactionType): array
    {
        $conditions = ['ceat_b.customer_employee_account_id = customer_employee_accounts.id', 'ceat_b.deleted_at IS NULL'];
        $bindings = [];

        if ($dateFrom) {
            $conditions[] = 'ceat_b.transaction_date >= ?';
            $bindings[] = $dateFrom;
        }

        if ($dateTo) {
            $conditions[] = 'ceat_b.transaction_date <= ?';
            $bindings[] = $dateTo;
        }

        if ($transactionType) {
            $conditions[] = 'ceat_b.transaction_type = ?';
            $bindings[] = $transactionType;
        }

        return [
            '(SELECT COALESCE(SUM(ceat_b.debit), 0) - COALESCE(SUM(ceat_b.credit), 0) FROM customer_employee_account_transactions ceat_b WHERE '.implode(' AND ', $conditions).')',
            $bindings,
        ];
    }

    private function openingBalance(CustomerEmployeeAccount $account, ?string $dateFrom): float
    {
        if (! $dateFrom) {
            return 0.0;
        }

        return (float) $account->transactions()
            ->whereDate('transaction_date', '<', $dateFrom)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance');
    }

    private function balanceBeforePage(CustomerEmployeeAccount $account, Request $request, LengthAwarePaginator $entries, float $openingBalance): float
    {
        if ($entries->currentPage() <= 1 || $entries->perPage() < 1) {
            return $openingBalance;
        }

        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');
        $entriesBeforePage = ($entries->currentPage() - 1) * $entries->perPage();

        $query = $account->transactions();

        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        foreach (['transaction_type', 'reference_number', 'invoice_number', 'description', 'payment_method'] as $filter) {
            if ($request->filled('filter.'.$filter)) {
                $value = $request->input('filter.'.$filter);
                $operator = in_array($filter, ['reference_number', 'invoice_number', 'description'], true) ? 'like' : '=';
                $query->where($filter, $operator, $operator === 'like' ? '%'.$value.'%' : $value);
            }
        }

        if ($request->filled('filter.amount_min')) {
            $query->where(function ($amountQuery) use ($request) {
                $amountQuery->where('debit', '>=', $request->input('filter.amount_min'))
                    ->orWhere('credit', '>=', $request->input('filter.amount_min'));
            });
        }

        if ($request->filled('filter.amount_max')) {
            $query->where(function ($amountQuery) use ($request) {
                $amountQuery->where(function ($debitQuery) use ($request) {
                    $debitQuery->where('debit', '>', 0)
                        ->where('debit', '<=', $request->input('filter.amount_max'));
                })->orWhere(function ($creditQuery) use ($request) {
                    $creditQuery->where('credit', '>', 0)
                        ->where('credit', '<=', $request->input('filter.amount_max'));
                });
            });
        }

        $balanceBeforePage = $query->orderBy('transaction_date')
            ->orderBy('id')
            ->limit($entriesBeforePage)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance');

        return $openingBalance + (float) $balanceBeforePage;
    }

    private function authorizeAccountAccess(CustomerEmployeeAccount $account): void
    {
        if ($this->canViewAllSuppliers()) {
            return;
        }

        $supplierId = auth()->user()->supplier_id ? (int) auth()->user()->supplier_id : null;

        if ($supplierId === null) {
            abort(403, 'You do not have permission to view this account statement.');
        }

        if ((int) $account->employee?->supplier_id !== $supplierId) {
            abort(403, 'You do not have permission to view this account statement.');
        }
    }

    private function getUserSupplierScope(): ?int
    {
        $user = auth()->user();

        if ($this->canViewAllSuppliers()) {
            return null;
        }

        return $user->supplier_id ? (int) $user->supplier_id : null;
    }

    private function canViewAllSuppliers(): bool
    {
        $user = auth()->user();

        return $user->is_super_admin === 'Yes'
            || $user->hasRole('super-admin')
            || $user->hasRole('admin');
    }
}

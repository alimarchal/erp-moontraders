<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeSalaryTransactionRequest;
use App\Http\Requests\UpdateEmployeeSalaryTransactionRequest;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\EmployeeSalaryTransaction;
use App\Models\Supplier;
use App\Services\SalaryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmployeeSalaryTransactionController extends Controller implements HasMiddleware
{
    public function __construct(private SalaryService $salaryService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:employee-salary-transaction-list', only: ['index', 'show']),
            new Middleware('can:employee-salary-transaction-create', only: ['create', 'store']),
            new Middleware('can:employee-salary-transaction-edit', only: ['edit', 'update']),
            new Middleware('can:employee-salary-transaction-delete', only: ['destroy']),
            new Middleware('can:employee-salary-transaction-post', only: ['post']),
        ];
    }

    public function index(Request $request)
    {
        $items = QueryBuilder::for(EmployeeSalaryTransaction::with(['employee', 'supplier']))
            ->allowedFilters([
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('transaction_type'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('payment_method'),
                AllowedFilter::partial('reference_number'),
                AllowedFilter::partial('salary_month'),
                AllowedFilter::callback('transaction_date_from', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('transaction_date', '>=', $value) : null),
                AllowedFilter::callback('transaction_date_to', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('transaction_date', '<=', $value) : null),
                AllowedFilter::callback('period_start', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('period_start', '>=', $value) : null),
                AllowedFilter::callback('period_end', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('period_end', '<=', $value) : null),
            ])
            ->orderByDesc('transaction_date')
            ->paginate(15)
            ->withQueryString();

        return view('employee-salary-transactions.index', [
            'items' => $items,
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'transactionTypeOptions' => EmployeeSalaryTransaction::transactionTypeOptions(),
            'statusOptions' => EmployeeSalaryTransaction::statusOptions(),
            'paymentMethodOptions' => EmployeeSalaryTransaction::paymentMethodOptions(),
        ]);
    }

    public function create()
    {
        return view('employee-salary-transactions.create', $this->formData());
    }

    public function store(StoreEmployeeSalaryTransactionRequest $request)
    {
        $data = $request->validated();

        // Auto-populate supplier_id from employee if not provided
        if (empty($data['supplier_id'])) {
            $employee = Employee::find($data['employee_id']);
            $data['supplier_id'] = $employee?->supplier_id;
        }

        $result = $this->salaryService->createTransaction($data);

        if ($result['success']) {
            return redirect()
                ->route('employee-salary-transactions.index')
                ->with('success', $result['message']);
        }

        return back()
            ->withInput()
            ->with('error', $result['message']);
    }

    public function show(EmployeeSalaryTransaction $employeeSalaryTransaction)
    {
        $employeeSalaryTransaction->load([
            'employee',
            'supplier',
            'debitAccount',
            'creditAccount',
            'bankAccount',
            'salesSettlement',
            'employeeSalary',
            'journalEntry',
        ]);

        return view('employee-salary-transactions.show', [
            'transaction' => $employeeSalaryTransaction,
        ]);
    }

    public function edit(EmployeeSalaryTransaction $employeeSalaryTransaction)
    {
        $employeeSalaryTransaction->load([
            'employee',
            'supplier',
            'debitAccount',
            'creditAccount',
            'bankAccount',
        ]);

        return view('employee-salary-transactions.edit', array_merge(
            ['transaction' => $employeeSalaryTransaction],
            $this->formData()
        ));
    }

    public function update(UpdateEmployeeSalaryTransactionRequest $request, EmployeeSalaryTransaction $employeeSalaryTransaction)
    {
        $result = $this->salaryService->updateTransaction($employeeSalaryTransaction, $request->validated());

        if ($result['success']) {
            return redirect()
                ->route('employee-salary-transactions.index')
                ->with('success', $result['message']);
        }

        return back()
            ->withInput()
            ->with('error', $result['message']);
    }

    public function destroy(EmployeeSalaryTransaction $employeeSalaryTransaction)
    {
        if ($employeeSalaryTransaction->status === 'Paid') {
            return back()->with('error', 'Paid transactions cannot be deleted.');
        }

        try {
            $employeeSalaryTransaction->delete();

            return redirect()
                ->route('employee-salary-transactions.index')
                ->with('success', "Transaction '{$employeeSalaryTransaction->reference_number}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting employee salary transaction', [
                'transaction_id' => $employeeSalaryTransaction->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete transaction. Please try again.');
        }
    }

    public function post(EmployeeSalaryTransaction $employeeSalaryTransaction)
    {
        $result = $this->salaryService->postTransaction($employeeSalaryTransaction);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Shared dropdown data for create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'transactionTypeOptions' => EmployeeSalaryTransaction::transactionTypeOptions(),
            'statusOptions' => EmployeeSalaryTransaction::statusOptions(),
            'paymentMethodOptions' => EmployeeSalaryTransaction::paymentMethodOptions(),
            'chartOfAccounts' => ChartOfAccount::where('is_group', false)
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name']),
            'bankAccounts' => BankAccount::where('is_active', true)
                ->orderBy('account_name')
                ->get(['id', 'account_name', 'bank_name']),
        ];
    }
}

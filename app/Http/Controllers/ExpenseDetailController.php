<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseDetailRequest;
use App\Http\Requests\UpdateExpenseDetailRequest;
use App\Models\Employee;
use App\Models\ExpenseDetail;
use App\Models\Vehicle;
use App\Services\ExpenseDetailService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ExpenseDetailController extends Controller implements HasMiddleware
{
    public function __construct(private ExpenseDetailService $expenseService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:expense-detail-list', only: ['index', 'show']),
            new Middleware('can:expense-detail-create', only: ['create', 'store']),
            new Middleware('can:expense-detail-edit', only: ['edit', 'update']),
            new Middleware('can:expense-detail-delete', only: ['destroy']),
            new Middleware('can:expense-detail-post', only: ['post']),
        ];
    }

    public function index(Request $request)
    {
        $query = ExpenseDetail::query();

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }

        // Calculate opening balance (records before date_from)
        $openingBalance = 0;
        $dateFrom = $request->input('date_from');

        if ($dateFrom) {
            $openingQuery = ExpenseDetail::query();

            if ($request->filled('category')) {
                $openingQuery->where('category', $request->input('category'));
            }

            $openingQuery->whereDate('transaction_date', '<', $dateFrom);
            $openingBalance = (float) $openingQuery->sum('amount');
        }

        $expenses = $query
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('expense-details.index', [
            'expenses' => $expenses,
            'openingBalance' => $openingBalance,
        ]);
    }

    public function create()
    {
        return view('expense-details.create', $this->formData());
    }

    public function store(StoreExpenseDetailRequest $request)
    {
        $data = $request->validated();
        $data = $this->prepareData($data);

        $result = $this->expenseService->createExpense($data);

        if ($result['success']) {
            return redirect()
                ->route('expense-details.index')
                ->with('success', $result['message']);
        }

        return back()
            ->withInput()
            ->with('error', $result['message']);
    }

    public function show(ExpenseDetail $expenseDetail)
    {
        $expenseDetail->load(['vehicle', 'driverEmployee', 'employee', 'debitAccount', 'creditAccount', 'journalEntry', 'postedByUser']);

        return view('expense-details.show', [
            'expense' => $expenseDetail,
        ]);
    }

    public function edit(ExpenseDetail $expenseDetail)
    {
        if ($expenseDetail->isPosted()) {
            return back()->with('error', 'Posted expenses cannot be edited.');
        }

        $expenseDetail->load(['vehicle', 'driverEmployee', 'employee']);

        return view('expense-details.edit', array_merge(
            ['expense' => $expenseDetail],
            $this->formData()
        ));
    }

    public function update(UpdateExpenseDetailRequest $request, ExpenseDetail $expenseDetail)
    {
        if ($expenseDetail->isPosted()) {
            return back()->with('error', 'Posted expenses cannot be edited.');
        }

        $data = $request->validated();
        $data = $this->prepareData($data);

        $result = $this->expenseService->updateExpense($expenseDetail, $data);

        if ($result['success']) {
            return redirect()
                ->route('expense-details.index')
                ->with('success', $result['message']);
        }

        return back()
            ->withInput()
            ->with('error', $result['message']);
    }

    public function destroy(ExpenseDetail $expenseDetail)
    {
        if ($expenseDetail->isPosted()) {
            return back()->with('error', 'Posted expenses cannot be deleted.');
        }

        try {
            $expenseDetail->delete();

            return redirect()
                ->route('expense-details.index')
                ->with('success', 'Expense deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Error deleting expense detail', [
                'expense_id' => $expenseDetail->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete expense. Please try again.');
        }
    }

    /**
     * Post the expense to GL (creates journal entry).
     */
    public function post(Request $request, ExpenseDetail $expenseDetail)
    {
        if ($expenseDetail->isPosted()) {
            return back()->with('error', 'Expense is already posted.');
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($request->password, auth()->user()->password)) {
            Log::warning("Failed expense posting attempt for expense #{$expenseDetail->id} - Invalid password by user: ".auth()->user()->name);

            return back()->with('error', 'Invalid password. Posting requires your password confirmation.');
        }

        Log::info("Expense posting password confirmed for expense #{$expenseDetail->id} by user: ".auth()->user()->name.' (ID: '.auth()->id().')');

        $result = $this->expenseService->postExpense($expenseDetail);

        if ($result['success']) {
            return redirect()
                ->route('expense-details.show', $expenseDetail)
                ->with('success', $result['message']);
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
            'vehicles' => Vehicle::orderBy('vehicle_number')->get(['id', 'vehicle_number', 'vehicle_type', 'employee_id']),
            'drivers' => Employee::where('designation', 'Driver')->orderBy('name')->get(['id', 'name', 'employee_code']),
            'employees' => Employee::orderBy('name')->get(['id', 'name', 'employee_code']),
        ];
    }

    /**
     * Auto-set GL accounts based on category, populate computed fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);
        $data['debit'] = $amount;
        $data['credit'] = 0;

        // Resolve GL accounts based on category
        $debitAccountId = $this->expenseService->resolveDebitAccount($data['category']);
        $creditAccountId = $this->expenseService->resolveCreditAccount();

        if ($debitAccountId) {
            $data['debit_account_id'] = $debitAccountId;
        }

        if ($creditAccountId) {
            $data['credit_account_id'] = $creditAccountId;
        }

        // Auto-populate vehicle-related fields for fuel
        if ($data['category'] === 'fuel' && ! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::with('employee')->find($data['vehicle_id']);
            if ($vehicle) {
                $data['vehicle_type'] = $vehicle->vehicle_type;
                $data['driver_employee_id'] = $vehicle->employee_id;
            }
        } else {
            $data['vehicle_id'] = null;
            $data['vehicle_type'] = null;
            $data['driver_employee_id'] = null;
            $data['liters'] = null;
        }

        // Auto-populate employee fields for salaries
        if ($data['category'] === 'salaries' && ! empty($data['employee_id'])) {
            $employee = Employee::find($data['employee_id']);
            if ($employee) {
                $data['employee_no'] = $employee->employee_code;
            }
        } else {
            $data['employee_id'] = null;
            $data['employee_no'] = null;
        }

        return $data;
    }
}

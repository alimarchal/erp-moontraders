<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseDetailRequest;
use App\Http\Requests\UpdateExpenseDetailRequest;
use App\Models\Employee;
use App\Models\ExpenseDetail;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Services\ExpenseDetailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;

class ExpenseDetailReportController extends Controller implements HasMiddleware
{
    public function __construct(private ExpenseDetailService $expenseService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-expense-detail', only: ['index']),
            new Middleware('can:expense-detail-create', only: ['store']),
            new Middleware('can:expense-detail-edit', only: ['update']),
            new Middleware('can:expense-detail-post', only: ['post']),
            new Middleware('can:expense-detail-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = \in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 50;

        // Default supplier: Nestlé
        $defaultSupplier = Supplier::where('short_name', 'Nestle')->first();
        $supplierId = $request->input('supplier_id', $defaultSupplier?->id);

        // Date range defaults: current month
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());
        $category = $request->input('category');
        $postedStatus = $request->input('posted_status');

        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get();
        $categoryOptions = ExpenseDetail::categoryOptions();

        // Opening balance (expenses before date range).
        // When viewing the current calendar month, start fresh from 0 (no carry-forward).
        // For any past period, include all expenses prior to dateFrom.
        $openingBalance = 0;
        $isCurrentMonth = $dateFrom && Carbon::parse($dateFrom)->isSameMonth(now());

        if ($dateFrom && ! $isCurrentMonth) {
            $openingQuery = ExpenseDetail::query();

            if ($supplierId) {
                $openingQuery->where('supplier_id', $supplierId);
            }

            if ($category) {
                $openingQuery->where('category', $category);
            }

            $openingQuery->whereDate('transaction_date', '<', $dateFrom);
            $openingBalance = (float) $openingQuery->sum('amount');
        }

        // Main query
        $query = ExpenseDetail::with('supplier')
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($postedStatus === 'posted') {
            $query->whereNotNull('posted_at');
        } elseif ($postedStatus === 'unposted') {
            $query->whereNull('posted_at');
        }

        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        if ($perPage === 'all') {
            $expenses = $query->get();
            $expenses = new LengthAwarePaginator(
                $expenses,
                $expenses->count(),
                $expenses->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $expenses = $query->paginate((int) $perPage)->withQueryString();
        }

        // Totals
        $totalAmount = (clone $query)->sum('amount');
        $closingBalance = $openingBalance + $totalAmount;

        // Category-wise totals for the filtered period
        $categoryTotals = [];
        foreach (array_keys($categoryOptions) as $cat) {
            $catQuery = ExpenseDetail::query();

            if ($supplierId) {
                $catQuery->where('supplier_id', $supplierId);
            }

            if ($dateFrom) {
                $catQuery->whereDate('transaction_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $catQuery->whereDate('transaction_date', '<=', $dateTo);
            }

            $categoryTotals[$cat] = (float) $catQuery->where('category', $cat)->sum('amount');
        }

        // Form data for inline create/edit
        $vehicles = Vehicle::orderBy('vehicle_number')->get(['id', 'vehicle_number', 'vehicle_type', 'employee_id']);
        $drivers = Employee::where('designation', 'Driver')->orderBy('name')->get(['id', 'name', 'employee_code']);
        $employees = Employee::orderBy('name')->get(['id', 'name', 'employee_code']);

        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;

        return view('reports.expense-detail.index', compact(
            'expenses',
            'suppliers',
            'supplierId',
            'selectedSupplier',
            'categoryOptions',
            'category',
            'postedStatus',
            'dateFrom',
            'dateTo',
            'openingBalance',
            'closingBalance',
            'totalAmount',
            'categoryTotals',
            'perPage',
            'vehicles',
            'drivers',
            'employees',
        ));
    }

    public function store(StoreExpenseDetailRequest $request)
    {
        $data = $request->validated();
        $data = $this->prepareData($data);

        $result = $this->expenseService->createExpense($data);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->withInput()->with('error', $result['message']);
    }

    public function update(UpdateExpenseDetailRequest $request, ExpenseDetail $expenseDetail)
    {
        if ($expenseDetail->isPosted()) {
            return redirect()->back()->with('error', 'Posted expenses cannot be edited.');
        }

        $data = $request->validated();
        $data = $this->prepareData($data);

        $result = $this->expenseService->updateExpense($expenseDetail, $data);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->withInput()->with('error', $result['message']);
    }

    public function destroy(ExpenseDetail $expenseDetail)
    {
        if ($expenseDetail->isPosted()) {
            return redirect()->back()->with('error', 'Posted expenses cannot be deleted.');
        }

        try {
            $expenseDetail->delete();

            return redirect()->back()->with('success', 'Expense deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('ExpenseDetail report destroy error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete expense. Please try again.');
        }
    }

    public function post(ExpenseDetail $expenseDetail)
    {
        if ($expenseDetail->isPosted()) {
            return redirect()->back()->with('error', 'Expense is already posted.');
        }

        $result = $this->expenseService->postExpense($expenseDetail);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
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

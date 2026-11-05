<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\GoodsReceiptNote;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:supplier-list', only: ['index', 'show', 'help']),
            new Middleware('can:supplier-create', only: ['create', 'store']),
            new Middleware('can:supplier-edit', only: ['edit', 'update', 'toggleActive']),
        ];
    }

    public const PER_PAGE = ['15', '25', '50', '100', 'all'];

    public const SORTS = [
        'supplier_name' => 'supplier_name',
        'supplier_group' => 'supplier_group',
        'country' => 'country',
        'created_at' => 'created_at',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');
        $sort = (string) $request->input('sort', 'supplier_name');
        $sortField = self::SORTS[ltrim($sort, '-')] ?? 'supplier_name';
        $sortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $perPage = in_array((string) $request->input('per_page', '25'), self::PER_PAGE, true) ? (string) $request->input('per_page', '25') : '25';

        $scoped = Supplier::query()
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q
                ->where('supplier_name', 'like', "%{$term}%")
                ->orWhere('short_name', 'like', "%{$term}%")
                ->orWhere('supplier_group', 'like', "%{$term}%")
                ->orWhere('supplier_type', 'like', "%{$term}%")
                ->orWhere('country', 'like', "%{$term}%")))
            ->when($filters['supplier_group'] ?? null, fn ($query, $group) => $query->where('supplier_group', $group))
            ->when($filters['qty_entry_mode'] ?? null, fn ($query, $mode) => $query->where('qty_entry_mode', $mode))
            ->when(isset($filters['is_transporter']), fn ($query) => $this->applyBooleanFilter($query, 'is_transporter', $filters['is_transporter']))
            ->when(isset($filters['is_internal_supplier']), fn ($query) => $this->applyBooleanFilter($query, 'is_internal_supplier', $filters['is_internal_supplier']));

        $statusCounts = [
            'active' => (clone $scoped)->where('disabled', false)->count(),
            'disabled' => (clone $scoped)->where('disabled', true)->count(),
        ];

        $query = (clone $scoped)
            ->with(['defaultCurrency', 'defaultBankAccount'])
            ->withCount('products')
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('disabled', false))
            ->when(($filters['status'] ?? null) === 'disabled', fn ($q) => $q->where('disabled', true))
            ->orderBy($sortField, $sortDirection)
            ->orderBy('id');

        $suppliers = $query->paginate($perPage === 'all' ? max((clone $query)->count(), 1) : (int) $perPage)->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'sort' => $sort,
            'perPage' => $perPage,
            'statusCounts' => $statusCounts,
            'groups' => Supplier::query()->whereNotNull('supplier_group')->distinct()->orderBy('supplier_group')->pluck('supplier_group'),
        ]);
    }

    /**
     * The guide, English and Urdu side by side.
     */
    public function help()
    {
        return view('suppliers.help');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('suppliers.create', [
            'currencyOptions' => Currency::orderBy('currency_code')->get(['id', 'currency_code', 'currency_name']),
            'accountOptions' => ChartOfAccount::orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request)
    {
        DB::beginTransaction();

        try {
            $supplier = Supplier::create($request->validated());

            DB::commit();

            return redirect()
                ->route('suppliers.index')
                ->with('success', "Supplier '{$supplier->supplier_name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating supplier', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create supplier. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'suppliers_supplier_name_unique')) {
                $message = 'A supplier with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating supplier', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create supplier. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        $supplier->load(['defaultCurrency', 'defaultBankAccount']);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'productCount' => $supplier->products()->count(),
            'grnCount' => GoodsReceiptNote::where('supplier_id', $supplier->id)->count(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Supplier $supplier)
    {
        $supplier->load(['defaultCurrency', 'defaultBankAccount']);

        return view('suppliers.edit', [
            'supplier' => $supplier,
            'currencyOptions' => Currency::orderBy('currency_code')->get(['id', 'currency_code', 'currency_name']),
            'accountOptions' => ChartOfAccount::orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        DB::beginTransaction();

        try {
            $updated = $supplier->update($request->validated());

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the supplier.');
            }

            DB::commit();

            return redirect()
                ->route('suppliers.index')
                ->with('success', "Supplier '{$supplier->supplier_name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating supplier', [
                'supplier_id' => $supplier->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update supplier. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'suppliers_supplier_name_unique')) {
                $message = 'A supplier with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating supplier', [
                'supplier_id' => $supplier->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update supplier. Please try again.');
        }
    }

    /**
     * Suppliers are never deleted: they are deactivated and can be activated again.
     * Products, GRNs and ledgers always stay.
     */
    public function toggleActive(Supplier $supplier)
    {
        $deactivate = ! $supplier->disabled;

        try {
            $supplier->update(['disabled' => $deactivate]);

            return back()->with('success', $deactivate
                ? "Supplier '{$supplier->supplier_name}' deactivated. Its history is kept."
                : "Supplier '{$supplier->supplier_name}' activated.");
        } catch (\Throwable $e) {
            Log::error('Error changing supplier status', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to change supplier status. Please try again.');
        }
    }

    /**
     * Apply a boolean filter to the query when applicable.
     */
    protected function applyBooleanFilter($query, string $column, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($flag === null) {
            return;
        }

        $query->where($column, $flag);
    }
}

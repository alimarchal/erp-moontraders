<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SupplierController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:supplier-list', only: ['index', 'show']),
            new Middleware('can:supplier-create', only: ['create', 'store']),
            new Middleware('can:supplier-edit', only: ['edit', 'update']),
            new Middleware('can:supplier-delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $suppliers = QueryBuilder::for(
            Supplier::query()->with(['defaultCurrency', 'defaultBankAccount'])
        )
            ->allowedFilters([
                AllowedFilter::partial('supplier_name'),
                AllowedFilter::partial('short_name'),
                AllowedFilter::partial('country'),
                AllowedFilter::partial('supplier_group'),
                AllowedFilter::partial('supplier_type'),
                AllowedFilter::callback('is_transporter', fn ($query, $value) => $this->applyBooleanFilter($query, 'is_transporter', $value)),
                AllowedFilter::callback('is_internal_supplier', fn ($query, $value) => $this->applyBooleanFilter($query, 'is_internal_supplier', $value)),
                AllowedFilter::callback('disabled', fn ($query, $value) => $this->applyBooleanFilter($query, 'disabled', $value)),
            ])
            ->orderBy('supplier_name')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
        ]);
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
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        try {
            $name = $supplier->supplier_name;
            $supplier->delete();

            return redirect()
                ->route('suppliers.index')
                ->with('success', "Supplier '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting supplier', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete supplier. Please try again.');
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

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\WarehouseType;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $warehouses = QueryBuilder::for(Warehouse::query())
            ->with(['company', 'warehouseType', 'parentWarehouse', 'defaultInTransitWarehouse', 'account'])
            ->allowedFilters([
                AllowedFilter::partial('warehouse_name'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::exact('warehouse_type_id'),
                AllowedFilter::exact('is_group'),
                AllowedFilter::exact('disabled'),
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $companyOptions = Company::orderBy('company_name')->get(['id', 'company_name']);
        $warehouseTypeOptions = WarehouseType::orderBy('name')->get(['id', 'name']);

        return view('warehouses.index', [
            'warehouses' => $warehouses,
            'companyOptions' => $companyOptions,
            'warehouseTypeOptions' => $warehouseTypeOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $chartOfAccounts = ChartOfAccount::where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $companies = Company::orderBy('company_name')->get();
        $warehouseTypes = WarehouseType::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::orderBy('warehouse_name')->get();

        return view('warehouses.create', compact('chartOfAccounts', 'companies', 'warehouseTypes', 'warehouses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWarehouseRequest $request)
    {
        DB::beginTransaction();

        try {
            $warehouse = Warehouse::create($request->validated());

            DB::commit();

            return redirect()
                ->route('warehouses.index')
                ->with('success', "Warehouse '{$warehouse->warehouse_name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating warehouse', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create warehouse. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'warehouses_warehouse_name_unique')) {
                $message = 'A warehouse with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating warehouse', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create warehouse. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Warehouse $warehouse)
    {
        $warehouse->load(['company', 'warehouseType', 'parentWarehouse', 'defaultInTransitWarehouse', 'account']);

        return view('warehouses.show', compact('warehouse'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Warehouse $warehouse)
    {
        $chartOfAccounts = ChartOfAccount::where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $companies = Company::orderBy('company_name')->get();
        $warehouseTypes = WarehouseType::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('id', '!=', $warehouse->id)->orderBy('warehouse_name')->get();

        return view('warehouses.edit', compact('warehouse', 'chartOfAccounts', 'companies', 'warehouseTypes', 'warehouses'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        DB::beginTransaction();

        try {
            $updated = $warehouse->update($request->validated());

            if (!$updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the warehouse.');
            }

            DB::commit();

            return redirect()
                ->route('warehouses.index')
                ->with('success', "Warehouse '{$warehouse->warehouse_name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating warehouse', [
                'warehouse_id' => $warehouse->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update warehouse. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'warehouses_warehouse_name_unique')) {
                $message = 'A warehouse with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating warehouse', [
                'warehouse_id' => $warehouse->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update warehouse. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse)
    {
        try {
            $warehouse->delete();

            return redirect()
                ->route('warehouses.index')
                ->with('success', "Warehouse '{$warehouse->warehouse_name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting warehouse', [
                'warehouse_id' => $warehouse->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete warehouse. Please try again.');
        }
    }
}

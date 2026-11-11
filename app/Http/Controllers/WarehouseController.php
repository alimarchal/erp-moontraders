<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WarehouseController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:warehouse-list', only: ['index', 'show']),
            new Middleware('can:warehouse-create', only: ['create', 'store']),
            new Middleware('can:warehouse-edit', only: ['edit', 'update']),
            new Middleware('can:warehouse-delete', only: ['destroy']),
        ];
    }

    private const PER_PAGE_OPTIONS = [15, 25, 50, 100];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE_OPTIONS, true) ? (int) $request->input('per_page') : 15;

        $warehouses = QueryBuilder::for(Warehouse::query())
            ->with(['company', 'warehouseType', 'parentWarehouse', 'account'])
            ->withSum('currentStocks as stock_on_hand', 'quantity_on_hand')
            ->allowedFilters($this->allowedFilters())
            ->allowedSorts(['warehouse_name', 'created_at'])
            ->defaultSort('warehouse_name')
            ->paginate($perPage)
            ->withQueryString();

        return view('warehouses.index', [
            'warehouses' => $warehouses,
            'companyOptions' => Company::orderBy('company_name')->get(['id', 'company_name']),
            'warehouseTypeOptions' => WarehouseType::orderBy('name')->get(['id', 'name']),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'perPage' => $perPage,
            'stats' => $this->listStats($request),
        ]);
    }

    /**
     * @return array<int, AllowedFilter>
     */
    private function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('search', fn ($query, $value) => $query->where(function ($q) use ($value) {
                $term = '%'.(is_array($value) ? implode(',', $value) : $value).'%';
                $q->where('warehouse_name', 'like', $term)->orWhere('city', 'like', $term);
            })),
            AllowedFilter::partial('warehouse_name'),
            AllowedFilter::exact('company_id'),
            AllowedFilter::exact('warehouse_type_id'),
            AllowedFilter::exact('is_group'),
            AllowedFilter::exact('disabled'),
        ];
    }

    /**
     * Headline numbers for the list; counts follow the filters except the status tab.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    private function listStats(Request $request): array
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($v) => $v !== null && $v !== '');
        unset($filters['disabled']);

        $byStatus = QueryBuilder::for(Warehouse::query(), new Request(['filter' => $filters]))
            ->allowedFilters($this->allowedFilters())
            ->getEloquentBuilder()
            ->reorder()
            ->selectRaw('disabled, COUNT(*) as aggregate')
            ->groupBy('disabled')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->disabled => (int) $row->aggregate]);

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[0] ?? 0),
            'inactive' => (int) ($byStatus[1] ?? 0),
        ];
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

            if (! $updated) {
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
     * "Delete" only deactivates the warehouse; records are never removed. Refused while it still holds stock.
     */
    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->hasStock()) {
            return back()->with('error', $warehouse->stockBlocksDeactivationMessage());
        }

        try {
            $warehouse->update(['disabled' => true]);

            return redirect()
                ->route('warehouses.index')
                ->with('success', "Warehouse '{$warehouse->warehouse_name}' deactivated. Its history is kept and it can be re-activated from Edit.");
        } catch (\Throwable $e) {
            Log::error('Error deactivating warehouse', [
                'warehouse_id' => $warehouse->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to deactivate warehouse. Please try again.');
        }
    }
}

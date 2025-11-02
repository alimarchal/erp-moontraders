<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseTypeRequest;
use App\Http\Requests\UpdateWarehouseTypeRequest;
use App\Models\WarehouseType;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WarehouseTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $warehouseTypes = QueryBuilder::for(WarehouseType::query())
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('warehouse-types.index', [
            'warehouseTypes' => $warehouseTypes,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('warehouse-types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWarehouseTypeRequest $request)
    {
        DB::beginTransaction();

        try {
            $warehouseType = WarehouseType::create($request->validated());

            DB::commit();

            return redirect()
                ->route('warehouse-types.index')
                ->with('success', "Warehouse type '{$warehouseType->name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating warehouse type', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create warehouse type. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'warehouse_types_name_unique')) {
                $message = 'A warehouse type with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating warehouse type', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create warehouse type. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(WarehouseType $warehouseType)
    {
        return view('warehouse-types.show', [
            'warehouseType' => $warehouseType,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(WarehouseType $warehouseType)
    {
        return view('warehouse-types.edit', [
            'warehouseType' => $warehouseType,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWarehouseTypeRequest $request, WarehouseType $warehouseType)
    {
        DB::beginTransaction();

        try {
            $updated = $warehouseType->update($request->validated());

            if (!$updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the warehouse type.');
            }

            DB::commit();

            return redirect()
                ->route('warehouse-types.index')
                ->with('success', "Warehouse type '{$warehouseType->name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating warehouse type', [
                'warehouse_type_id' => $warehouseType->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update warehouse type. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'warehouse_types_name_unique')) {
                $message = 'A warehouse type with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating warehouse type', [
                'warehouse_type_id' => $warehouseType->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update warehouse type. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WarehouseType $warehouseType)
    {
        try {
            $warehouseType->delete();

            return redirect()
                ->route('warehouse-types.index')
                ->with('success', "Warehouse type '{$warehouseType->name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting warehouse type', [
                'warehouse_type_id' => $warehouseType->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete warehouse type. Please try again.');
        }
    }
}

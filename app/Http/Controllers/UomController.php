<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUomRequest;
use App\Http\Requests\UpdateUomRequest;
use App\Models\Uom;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $statusOptions = ['1' => 'Enabled', '0' => 'Disabled'];
        $quantityOptions = ['1' => 'Whole Numbers', '0' => 'Any Quantity'];

        $uoms = QueryBuilder::for(Uom::query())
            ->allowedFilters([
                AllowedFilter::partial('uom_name'),
                AllowedFilter::partial('symbol'),
                AllowedFilter::partial('description'),
                AllowedFilter::exact('enabled'),
                AllowedFilter::exact('must_be_whole_number'),
            ])
            ->orderBy('uom_name')
            ->paginate(15)
            ->withQueryString();

        return view('uoms.index', [
            'uoms' => $uoms,
            'statusOptions' => $statusOptions,
            'quantityOptions' => $quantityOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('uoms.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUomRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $validated['must_be_whole_number'] = $request->boolean('must_be_whole_number');
            $validated['enabled'] = $request->has('enabled')
                ? $request->boolean('enabled')
                : true;

            $uom = Uom::create($validated);

            DB::commit();

            return redirect()
                ->route('uoms.index')
                ->with('success', "Unit '{$uom->uom_name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating UOM', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create unit. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uoms_uom_name_unique')) {
                $message = 'The unit name must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating UOM', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create unit. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Uom $uom)
    {
        return view('uoms.show', compact('uom'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Uom $uom)
    {
        return view('uoms.edit', compact('uom'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUomRequest $request, Uom $uom)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $validated['must_be_whole_number'] = $request->boolean('must_be_whole_number');
            $validated['enabled'] = $request->has('enabled')
                ? $request->boolean('enabled')
                : $uom->enabled;

            $updated = $uom->update($validated);

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the unit.');
            }

            DB::commit();

            return redirect()
                ->route('uoms.index')
                ->with('success', "Unit '{$uom->uom_name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating UOM', [
                'uom_id' => $uom->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update unit. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uoms_uom_name_unique')) {
                $message = 'The unit name must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating UOM', [
                'uom_id' => $uom->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update unit. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Uom $uom)
    {
        try {
            $uom->delete();

            return redirect()
                ->route('uoms.index')
                ->with('success', "Unit '{$uom->uom_name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting UOM', [
                'uom_id' => $uom->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete unit. Please try again.');
        }
    }
}

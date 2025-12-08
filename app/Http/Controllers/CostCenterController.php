<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCostCenterRequest;
use App\Http\Requests\UpdateCostCenterRequest;
use App\Models\CostCenter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CostCenterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $typeOptions = CostCenter::typeOptions();

        $costCenters = QueryBuilder::for(
            CostCenter::query()->withCount('children')->with('parent')
        )
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('type'),
                AllowedFilter::callback('is_active', function ($query, $value) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                    if ($flag === null) {
                        return;
                    }

                    $query->where('is_active', $flag);
                }),
                AllowedFilter::callback('start_date_from', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('start_date', '>=', $value) : null),
                AllowedFilter::callback('start_date_to', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('start_date', '<=', $value) : null),
                AllowedFilter::callback('end_date_from', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('end_date', '>=', $value) : null),
                AllowedFilter::callback('end_date_to', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('end_date', '<=', $value) : null),
            ])
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('accounting.cost-centers.index', [
            'costCenters' => $costCenters,
            'typeOptions' => $typeOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('accounting.cost-centers.create', [
            'typeOptions' => CostCenter::typeOptions(),
            'parentOptions' => CostCenter::orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCostCenterRequest $request)
    {
        DB::beginTransaction();

        try {
            $costCenter = CostCenter::create($request->validated());

            DB::commit();

            return redirect()
                ->route('cost-centers.index')
                ->with('success', "Cost center '{$costCenter->code}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating cost center', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create cost center. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'cost_centers_code_unique')) {
                $message = 'A cost center with this code already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating cost center', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create cost center. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(CostCenter $costCenter)
    {
        $costCenter->load(['parent', 'children']);

        return view('accounting.cost-centers.show', [
            'costCenter' => $costCenter,
            'typeOptions' => CostCenter::typeOptions(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CostCenter $costCenter)
    {
        $costCenter->load('children');

        return view('accounting.cost-centers.edit', [
            'costCenter' => $costCenter,
            'typeOptions' => CostCenter::typeOptions(),
            'parentOptions' => CostCenter::whereKeyNot($costCenter->id)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCostCenterRequest $request, CostCenter $costCenter)
    {
        $validated = $request->validated();
        $newParentId = $validated['parent_id'] ?? null;

        if ($newParentId && in_array($newParentId, $this->descendantIds($costCenter), true)) {
            return back()
                ->withInput()
                ->with('error', 'A cost center cannot be assigned to one of its descendants.');
        }

        DB::beginTransaction();

        try {
            $updated = $costCenter->update($validated);

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the cost center.');
            }

            DB::commit();

            return redirect()
                ->route('cost-centers.index')
                ->with('success', "Cost center '{$costCenter->code}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating cost center', [
                'cost_center_id' => $costCenter->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update cost center. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'cost_centers_code_unique')) {
                $message = 'A cost center with this code already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating cost center', [
                'cost_center_id' => $costCenter->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update cost center. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CostCenter $costCenter)
    {
        if ($costCenter->children()->exists()) {
            return back()->with('error', 'Unable to delete cost center while child cost centers exist. Reassign or delete child records first.');
        }

        if ($costCenter->journalEntryDetails()->exists()) {
            return back()->with('error', 'Unable to delete cost center while journal entries reference it.');
        }

        try {
            $code = $costCenter->code;
            $costCenter->delete();

            return redirect()
                ->route('cost-centers.index')
                ->with('success', "Cost center '{$code}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting cost center', [
                'cost_center_id' => $costCenter->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete cost center. Please try again.');
        }
    }

    /**
     * Recursively gather all descendant IDs for the provided cost center.
     *
     * @return array<int, int>
     */
    protected function descendantIds(CostCenter $costCenter): array
    {
        $ids = [];

        $costCenter->loadMissing('children');

        foreach ($costCenter->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->descendantIds($child));
        }

        return $ids;
    }
}

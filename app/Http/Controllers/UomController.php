<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUomRequest;
use App\Http\Requests\UpdateUomRequest;
use App\Models\Product;
use App\Models\Uom;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UomController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:uom-list', only: ['index', 'show']),
            new Middleware('can:uom-create', only: ['create', 'store']),
            new Middleware('can:uom-edit', only: ['edit', 'update']),
            new Middleware('can:uom-delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $uoms = QueryBuilder::for(Uom::query())
            ->allowedFilters($this->allowedFilters())
            ->allowedSorts(['uom_name'])
            ->defaultSort('uom_name')
            ->paginate($perPage)
            ->withQueryString();

        return view('uoms.index', [
            'uoms' => $uoms,
            'stats' => $this->listStats($request),
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
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
        return view('uoms.show', [
            'uom' => $uom,
            'baseCount' => Product::where('uom_id', $uom->id)->count(),
            'salesCount' => Product::where('sales_uom_id', $uom->id)->count(),
        ]);
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
     * "Delete" only disables the unit; records are never removed.
     */
    public function destroy(Uom $uom)
    {
        try {
            $uom->update(['enabled' => false]);

            return redirect()
                ->route('uoms.index')
                ->with('success', "Unit '{$uom->uom_name}' disabled. Products using it are unchanged.");
        } catch (\Throwable $e) {
            Log::error('Error disabling UOM', [
                'uom_id' => $uom->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to disable unit. Please try again.');
        }
    }

    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * @return array<int, AllowedFilter>
     */
    private function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('search', fn ($query, $value) => $query->where(function ($q) use ($value) {
                $term = '%'.trim((string) $value).'%';
                $q->where('uom_name', 'like', $term)
                    ->orWhere('symbol', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })),
            AllowedFilter::partial('uom_name'),
            AllowedFilter::partial('symbol'),
            AllowedFilter::exact('must_be_whole_number'),
            AllowedFilter::exact('enabled'),
        ];
    }

    /**
     * Headline numbers for the list; counts follow the search but not the status tab.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    private function listStats(Request $request): array
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($v) => $v !== null && $v !== '');
        unset($filters['enabled']);

        $byStatus = QueryBuilder::for(Uom::class, new Request(['filter' => $filters]))
            ->allowedFilters($this->allowedFilters())
            ->getEloquentBuilder()
            ->reorder()
            ->selectRaw('enabled, COUNT(*) as aggregate')
            ->groupBy('enabled')
            ->pluck('aggregate', 'enabled');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[1] ?? 0),
            'inactive' => (int) ($byStatus[0] ?? 0),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;
use App\Models\TaxCode;
use App\Models\TaxRate;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaxRateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:tax-list', only: ['index', 'show']),
            new Middleware('permission:tax-create', only: ['create', 'store']),
            new Middleware('permission:tax-edit', only: ['edit', 'update']),
            new Middleware('permission:tax-delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $taxRates = QueryBuilder::for(TaxRate::query())
            ->with(['taxCode'])
            ->allowedFilters([
                AllowedFilter::exact('tax_code_id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::partial('region'),
            ])
            ->orderBy('effective_from', 'desc')
            ->paginate(10)
            ->withQueryString();

        $taxCodes = TaxCode::where('is_active', true)->orderBy('tax_code')->get();

        return view('settings.tax-rates.index', [
            'taxRates' => $taxRates,
            'taxCodes' => $taxCodes,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $taxCodes = TaxCode::where('is_active', true)->orderBy('tax_code')->get();

        return view('settings.tax-rates.create', [
            'taxCodes' => $taxCodes,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaxRateRequest $request)
    {
        DB::beginTransaction();

        try {
            $taxRate = TaxRate::create($request->validated());

            DB::commit();

            return redirect()
                ->route('tax-rates.index')
                ->with('success', 'Tax rate created successfully.');
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating tax rate', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to create tax rate. Please review your input and try again.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating tax rate', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create tax rate. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxRate $taxRate)
    {
        $taxRate->load(['taxCode']);

        return view('settings.tax-rates.show', [
            'taxRate' => $taxRate,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaxRate $taxRate)
    {
        $taxCodes = TaxCode::where('is_active', true)->orderBy('tax_code')->get();

        return view('settings.tax-rates.edit', [
            'taxRate' => $taxRate,
            'taxCodes' => $taxCodes,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaxRateRequest $request, TaxRate $taxRate)
    {
        DB::beginTransaction();

        try {
            $updated = $taxRate->update($request->validated());

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the tax rate.');
            }

            DB::commit();

            return redirect()
                ->route('tax-rates.index')
                ->with('success', 'Tax rate updated successfully.');
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating tax rate', [
                'tax_rate_id' => $taxRate->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update tax rate. Please review your input and try again.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating tax rate', [
                'tax_rate_id' => $taxRate->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update tax rate. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxRate $taxRate)
    {
        try {
            $taxRate->delete();

            return redirect()
                ->route('tax-rates.index')
                ->with('success', 'Tax rate deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Error deleting tax rate', [
                'tax_rate_id' => $taxRate->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete tax rate. Please try again.');
        }
    }
}

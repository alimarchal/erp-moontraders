<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxCodeRequest;
use App\Http\Requests\UpdateTaxCodeRequest;
use App\Models\ChartOfAccount;
use App\Models\TaxCode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaxCodeController extends Controller implements HasMiddleware
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
        $taxTypeOptions = TaxCode::taxTypeOptions();
        $calculationMethodOptions = TaxCode::calculationMethodOptions();

        $taxCodes = QueryBuilder::for(TaxCode::query())
            ->with(['taxPayableAccount', 'taxReceivableAccount', 'taxRates'])
            ->allowedFilters([
                AllowedFilter::partial('tax_code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('tax_type'),
                AllowedFilter::exact('calculation_method'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_compound'),
            ])
            ->orderBy('tax_code')
            ->paginate(10)
            ->withQueryString();

        return view('settings.tax-codes.index', [
            'taxCodes' => $taxCodes,
            'taxTypeOptions' => $taxTypeOptions,
            'calculationMethodOptions' => $calculationMethodOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $accounts = ChartOfAccount::where('is_active', true)
            ->where('is_group', false)
            ->orderBy('account_code')
            ->get();

        return view('settings.tax-codes.create', [
            'taxTypeOptions' => TaxCode::taxTypeOptions(),
            'calculationMethodOptions' => TaxCode::calculationMethodOptions(),
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaxCodeRequest $request)
    {
        DB::beginTransaction();

        try {
            $taxCode = TaxCode::create($request->validated());

            DB::commit();

            return redirect()
                ->route('tax-codes.index')
                ->with('success', "Tax code '{$taxCode->name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating tax code', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create tax code. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'tax_codes_tax_code_unique')) {
                $message = 'A tax code with this code already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating tax code', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create tax code. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxCode $taxCode)
    {
        $taxCode->load(['taxPayableAccount', 'taxReceivableAccount', 'taxRates', 'productTaxMappings']);

        return view('settings.tax-codes.show', [
            'taxCode' => $taxCode,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaxCode $taxCode)
    {
        $accounts = ChartOfAccount::where('is_active', true)
            ->where('is_group', false)
            ->orderBy('account_code')
            ->get();

        return view('settings.tax-codes.edit', [
            'taxCode' => $taxCode,
            'taxTypeOptions' => TaxCode::taxTypeOptions(),
            'calculationMethodOptions' => TaxCode::calculationMethodOptions(),
            'accounts' => $accounts,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaxCodeRequest $request, TaxCode $taxCode)
    {
        DB::beginTransaction();

        try {
            $updated = $taxCode->update($request->validated());

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the tax code.');
            }

            DB::commit();

            return redirect()
                ->route('tax-codes.index')
                ->with('success', "Tax code '{$taxCode->name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating tax code', [
                'tax_code_id' => $taxCode->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update tax code. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'tax_codes_tax_code_unique')) {
                $message = 'A tax code with this code already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating tax code', [
                'tax_code_id' => $taxCode->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update tax code. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxCode $taxCode)
    {
        try {
            $taxCode->delete();

            return redirect()
                ->route('tax-codes.index')
                ->with('success', "Tax code '{$taxCode->name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting tax code', [
                'tax_code_id' => $taxCode->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete tax code. Please try again.');
        }
    }
}

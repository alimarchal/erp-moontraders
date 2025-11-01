<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $baseOptions = ['1' => 'Base Currency', '0' => 'Secondary'];
        $statusOptions = ['1' => 'Active', '0' => 'Inactive'];

        $currencies = QueryBuilder::for(Currency::query())
            ->allowedFilters([
                AllowedFilter::partial('currency_code'),
                AllowedFilter::partial('currency_name'),
                AllowedFilter::exact('is_base_currency'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('is_base_currency', 'desc')
            ->orderBy('currency_code')
            ->paginate(10)
            ->withQueryString();

        return view('accounting.currencies.index', [
            'currencies' => $currencies,
            'baseOptions' => $baseOptions,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('accounting.currencies.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCurrencyRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $validated['is_base_currency'] = $request->has('is_base_currency')
                ? $request->boolean('is_base_currency')
                : false;
            $validated['is_active'] = $request->has('is_active')
                ? $request->boolean('is_active')
                : true;

            if ($validated['is_base_currency']) {
                Currency::where('is_base_currency', true)->update(['is_base_currency' => false]);
                $validated['exchange_rate'] = 1.000000;
            }

            $currency = Currency::create($validated);

            DB::commit();

            return redirect()
                ->route('currencies.index')
                ->with('success', "Currency '{$currency->currency_code}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating currency', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create currency. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'currency_code')) {
                $message = 'The currency code must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating currency', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create currency. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Currency $currency)
    {
        return view('accounting.currencies.show', compact('currency'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Currency $currency)
    {
        return view('accounting.currencies.edit', compact('currency'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCurrencyRequest $request, Currency $currency)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $validated['is_base_currency'] = $request->has('is_base_currency')
                ? $request->boolean('is_base_currency')
                : $currency->is_base_currency;
            $validated['is_active'] = $request->has('is_active')
                ? $request->boolean('is_active')
                : $currency->is_active;

            if ($validated['is_base_currency']) {
                Currency::where('id', '!=', $currency->id)
                    ->where('is_base_currency', true)
                    ->update(['is_base_currency' => false]);
                $validated['exchange_rate'] = 1.000000;
            }

            $updated = $currency->update($validated);

            if (!$updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the currency.');
            }

            DB::commit();

            return redirect()
                ->route('currencies.index')
                ->with('success', "Currency '{$currency->currency_code}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating currency', [
                'currency_id' => $currency->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update currency. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'currency_code')) {
                $message = 'The currency code must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating currency', [
                'currency_id' => $currency->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update currency. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Currency $currency)
    {
        if ($currency->is_base_currency) {
            return back()->with('error', 'The base currency cannot be deleted.');
        }

        try {
            $currency->delete();

            return redirect()
                ->route('currencies.index')
                ->with('success', "Currency '{$currency->currency_code}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting currency', [
                'currency_id' => $currency->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete currency. Please try again.');
        }
    }
}

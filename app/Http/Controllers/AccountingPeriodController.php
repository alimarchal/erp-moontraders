<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountingPeriodRequest;
use App\Http\Requests\UpdateAccountingPeriodRequest;
use App\Models\AccountingPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountingPeriodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $statusOptions = AccountingPeriod::statusOptions();

        $periods = QueryBuilder::for(AccountingPeriod::query())
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('start_date_from', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('start_date', '>=', $value) : null),
                AllowedFilter::callback('start_date_to', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('start_date', '<=', $value) : null),
                AllowedFilter::callback('end_date_from', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('end_date', '>=', $value) : null),
                AllowedFilter::callback('end_date_to', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('end_date', '<=', $value) : null),
            ])
            ->orderByDesc('start_date')
            ->paginate(10)
            ->withQueryString();

        return view('accounting.accounting-periods.index', [
            'periods' => $periods,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('accounting.accounting-periods.create', [
            'statusOptions' => AccountingPeriod::statusOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAccountingPeriodRequest $request)
    {
        DB::beginTransaction();

        try {
            $period = AccountingPeriod::create($request->validated());

            DB::commit();

            return redirect()
                ->route('accounting-periods.index')
                ->with('success', "Accounting period '{$period->name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating accounting period', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create accounting period. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'accounting_periods_name_unique')) {
                $message = 'An accounting period with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating accounting period', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create accounting period. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(AccountingPeriod $accountingPeriod)
    {
        return view('accounting.accounting-periods.show', [
            'accountingPeriod' => $accountingPeriod,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccountingPeriod $accountingPeriod)
    {
        return view('accounting.accounting-periods.edit', [
            'accountingPeriod' => $accountingPeriod,
            'statusOptions' => AccountingPeriod::statusOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAccountingPeriodRequest $request, AccountingPeriod $accountingPeriod)
    {
        DB::beginTransaction();

        try {
            $updated = $accountingPeriod->update($request->validated());

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the accounting period.');
            }

            DB::commit();

            return redirect()
                ->route('accounting-periods.index')
                ->with('success', "Accounting period '{$accountingPeriod->name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating accounting period', [
                'accounting_period_id' => $accountingPeriod->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update accounting period. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'accounting_periods_name_unique')) {
                $message = 'An accounting period with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating accounting period', [
                'accounting_period_id' => $accountingPeriod->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update accounting period. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->status === AccountingPeriod::STATUS_OPEN && $accountingPeriod->isCurrent()) {
            return back()->with('error', 'Current open periods cannot be deleted.');
        }

        try {
            $accountingPeriod->delete();

            return redirect()
                ->route('accounting-periods.index')
                ->with('success', "Accounting period '{$accountingPeriod->name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting accounting period', [
                'accounting_period_id' => $accountingPeriod->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete accounting period. Please try again.');
        }
    }
}

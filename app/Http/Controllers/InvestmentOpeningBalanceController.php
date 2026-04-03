<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentOpeningBalanceRequest;
use App\Http\Requests\UpdateInvestmentOpeningBalanceRequest;
use App\Models\InvestmentOpeningBalance;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InvestmentOpeningBalanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:investment-opening-balance-list', only: ['index', 'show']),
            new Middleware('can:investment-opening-balance-create', only: ['create', 'store']),
            new Middleware('can:investment-opening-balance-edit', only: ['edit', 'update']),
            new Middleware('can:investment-opening-balance-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        $balances = QueryBuilder::for(InvestmentOpeningBalance::query()->with('supplier'))
            ->allowedFilters([
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::partial('description'),
                AllowedFilter::callback('date_from', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('date', '>=', $value) : null),
                AllowedFilter::callback('date_to', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('date', '<=', $value) : null),
            ])
            ->orderByDesc('date')
            ->orderBy('supplier_id')
            ->paginate(20)
            ->withQueryString();

        return view('investment-opening-balances.index', [
            'balances' => $balances,
            'suppliers' => $suppliers,
        ]);
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        return view('investment-opening-balances.create', [
            'suppliers' => $suppliers,
        ]);
    }

    public function store(StoreInvestmentOpeningBalanceRequest $request)
    {
        DB::beginTransaction();

        try {
            $balance = InvestmentOpeningBalance::create($request->validated());

            DB::commit();

            return redirect()
                ->route('investment-opening-balances.index')
                ->with('success', "Investment opening balance '{$balance->description}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating investment opening balance', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', [
                    'message' => 'Unable to create investment opening balance. Please review your input and try again.',
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating investment opening balance', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create investment opening balance. Please try again.');
        }
    }

    public function show(InvestmentOpeningBalance $investmentOpeningBalance)
    {
        $investmentOpeningBalance->load('supplier');

        return view('investment-opening-balances.show', [
            'balance' => $investmentOpeningBalance,
        ]);
    }

    public function edit(InvestmentOpeningBalance $investmentOpeningBalance)
    {
        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        return view('investment-opening-balances.edit', [
            'balance' => $investmentOpeningBalance,
            'suppliers' => $suppliers,
        ]);
    }

    public function update(UpdateInvestmentOpeningBalanceRequest $request, InvestmentOpeningBalance $investmentOpeningBalance)
    {
        DB::beginTransaction();

        try {
            $updated = $investmentOpeningBalance->update($request->validated());

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made.');
            }

            DB::commit();

            return redirect()
                ->route('investment-opening-balances.index')
                ->with('success', "Investment opening balance '{$investmentOpeningBalance->description}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating investment opening balance', [
                'id' => $investmentOpeningBalance->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', [
                    'message' => 'Unable to update investment opening balance. Please review your input and try again.',
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating investment opening balance', [
                'id' => $investmentOpeningBalance->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update investment opening balance. Please try again.');
        }
    }

    public function destroy(InvestmentOpeningBalance $investmentOpeningBalance)
    {
        try {
            $investmentOpeningBalance->delete();

            return redirect()
                ->route('investment-opening-balances.index')
                ->with('success', "Investment opening balance '{$investmentOpeningBalance->description}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting investment opening balance', [
                'id' => $investmentOpeningBalance->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete investment opening balance. Please try again.');
        }
    }
}

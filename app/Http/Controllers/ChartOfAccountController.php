<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChartOfAccountRequest;
use App\Http\Requests\UpdateChartOfAccountRequest;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChartOfAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $accountTypes = AccountType::orderBy('type_name')->pluck('type_name', 'id');
        $currencies = Currency::orderBy('currency_code')->pluck('currency_code', 'id');
        $normalBalances = ['debit' => 'Debit', 'credit' => 'Credit'];
        $groupOptions = ['1' => 'Group', '0' => 'Posting'];
        $statusOptions = ['1' => 'Active', '0' => 'Inactive'];

        $chartOfAccounts = QueryBuilder::for(
            ChartOfAccount::query()->with(['accountType', 'currency', 'parent'])
        )->allowedFilters([
            AllowedFilter::exact('account_type_id'),
            AllowedFilter::exact('currency_id'),
            AllowedFilter::exact('normal_balance'),
            AllowedFilter::exact('is_group'),
            AllowedFilter::exact('is_active'),
            AllowedFilter::partial('account_code'),
            AllowedFilter::partial('account_name'),
        ])
            ->orderBy('account_code')
            ->paginate(10)
            ->withQueryString();

        return view('accounting.chart-of-accounts.index', [
            'chartOfAccounts' => $chartOfAccounts,
            'accountTypes' => $accountTypes,
            'currencies' => $currencies,
            'normalBalances' => $normalBalances,
            'groupOptions' => $groupOptions,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('accounting.chart-of-accounts.create', $this->formOptions());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChartOfAccountRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $validated['parent_id'] = $validated['parent_id'] ?? null;
            $validated['is_group'] = (bool) ($validated['is_group'] ?? false);
            $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

            $chartOfAccount = ChartOfAccount::create($validated);

            DB::commit();

            return redirect()
                ->route('chart-of-accounts.index')
                ->with('success', "Account '{$chartOfAccount->account_code}' created successfully.");
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating chart of account', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create account. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'account_code')) {
                $message = 'The account code must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating chart of account', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create account. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ChartOfAccount $chartOfAccount)
    {
        $chartOfAccount->load(['accountType', 'currency', 'parent']);

        return view('accounting.chart-of-accounts.show', compact('chartOfAccount'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ChartOfAccount $chartOfAccount)
    {
        $chartOfAccount->load(['accountType', 'currency', 'parent']);

        return view(
            'accounting.chart-of-accounts.edit',
            array_merge(['chartOfAccount' => $chartOfAccount], $this->formOptions($chartOfAccount))
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartOfAccount)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $validated['parent_id'] = $validated['parent_id'] ?? null;
            $validated['is_group'] = (bool) ($validated['is_group'] ?? false);
            $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

            $updated = $chartOfAccount->update($validated);

            if (!$updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the account.');
            }

            DB::commit();

            return redirect()
                ->route('chart-of-accounts.index')
                ->with('success', "Account '{$chartOfAccount->account_code}' updated successfully.");
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating chart of account', [
                'chart_of_account_id' => $chartOfAccount->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update account. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'account_code')) {
                $message = 'The account code must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating chart of account', [
                'chart_of_account_id' => $chartOfAccount->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update account. Please try again.');
        }
    }

    /**
     * Prepare select options for forms.
     */
    protected function formOptions(?ChartOfAccount $chartOfAccount = null): array
    {
        $accountTypes = AccountType::orderBy('type_name')->pluck('type_name', 'id');
        $currencies = Currency::orderBy('currency_code')->pluck('currency_code', 'id');
        $parentAccounts = ChartOfAccount::orderBy('account_code')
            ->when($chartOfAccount, fn ($query) => $query->where('id', '!=', $chartOfAccount->id))
            ->get(['id', 'account_code', 'account_name', 'is_group']);
        $normalBalances = ['debit' => 'Debit', 'credit' => 'Credit'];

        return [
            'accountTypes' => $accountTypes,
            'currencies' => $currencies,
            'parentAccounts' => $parentAccounts,
            'normalBalances' => $normalBalances,
        ];
    }
}

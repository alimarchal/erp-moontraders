<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $companies = QueryBuilder::for(
            Company::query()->with(['parentCompany', 'defaultCurrency'])
        )
            ->allowedFilters([
                AllowedFilter::partial('company_name'),
                AllowedFilter::partial('abbr'),
                AllowedFilter::partial('country'),
                AllowedFilter::exact('is_group'),
                AllowedFilter::exact('default_currency_id'),
                AllowedFilter::callback('date_of_establishment_from', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('date_of_establishment', '>=', $value) : null),
                AllowedFilter::callback('date_of_establishment_to', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('date_of_establishment', '<=', $value) : null),
            ])
            ->defaultSort('company_name')
            ->paginate(10)
            ->withQueryString();

        return view('accounting.companies.index', [
            'companies' => $companies,
            'currencyOptions' => Currency::orderBy('currency_code')
                ->get(['id', 'currency_code', 'currency_name']),
            'parentOptions' => Company::orderBy('company_name')
                ->get(['id', 'company_name']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('accounting.companies.create', $this->formDependencies());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            $this->applyHierarchyDefaults($data);

            $company = Company::create($data);

            DB::commit();

            return redirect()
                ->route('companies.index')
                ->with('success', "Company '{$company->company_name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error while creating company', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create company. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'companies_company_name_unique')) {
                $message = 'A company with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error while creating company', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create company. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        $company->load([
            'parentCompany',
            'defaultCurrency',
            'costCenter',
            'childCompanies',
            'defaultBankAccount',
            'defaultCashAccount',
            'defaultReceivableAccount',
            'defaultPayableAccount',
            'defaultExpenseAccount',
            'defaultIncomeAccount',
            'writeOffAccount',
            'roundOffAccount',
            'defaultInventoryAccount',
            'stockAdjustmentAccount',
        ]);

        return view('accounting.companies.show', compact('company'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        $dependencies = $this->formDependencies($company);

        return view('accounting.companies.edit', array_merge($dependencies, [
            'company' => $company,
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $validated = $request->validated();
        $newParentId = $validated['parent_company_id'] ?? null;

        if ($newParentId && in_array($newParentId, $this->descendantIds($company), true)) {
            return back()
                ->withInput()
                ->with('error', 'A company cannot be assigned to one of its subsidiaries as parent.');
        }

        DB::beginTransaction();

        try {
            $data = $validated;

            $this->applyHierarchyDefaults($data, $company);

            $updated = $company->update($data);

            if (!$updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the company.');
            }

            DB::commit();

            return redirect()
                ->route('companies.index')
                ->with('success', "Company '{$company->company_name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error while updating company', [
                'company_id' => $company->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update company. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'companies_company_name_unique')) {
                $message = 'A company with this name already exists.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error while updating company', [
                'company_id' => $company->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update company. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        if ($company->childCompanies()->exists()) {
            return back()->with('error', 'Unable to delete company while subsidiaries exist. Reassign or delete child companies first.');
        }

        if ($company->warehouses()->exists()) {
            return back()->with('error', 'Unable to delete company while warehouses are linked to it.');
        }

        try {
            $name = $company->company_name;
            $company->delete();

            return redirect()
                ->route('companies.index')
                ->with('success', "Company '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting company', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete company. Please try again.');
        }
    }

    /**
     * Gather supporting data for company forms.
     *
     * @return array<string, mixed>
     */
    protected function formDependencies(?Company $company = null): array
    {
        return [
            'currencyOptions' => Currency::orderBy('currency_code')
                ->get(['id', 'currency_code', 'currency_name']),
            'costCenterOptions' => CostCenter::orderBy('code')
                ->get(['id', 'code', 'name']),
            'accountOptions' => ChartOfAccount::orderBy('account_code')
                ->get(['id', 'account_code', 'account_name']),
            'parentOptions' => Company::when($company, fn ($query) => $query->whereKeyNot($company->id))
                ->orderBy('company_name')
                ->get(['id', 'company_name']),
        ];
    }

    /**
     * Ensure nested set defaults exist for the payload.
     *
     * @param  array<string, mixed>  $data
     */
    protected function applyHierarchyDefaults(array &$data, ?Company $existing = null): void
    {
        $lft = $data['lft'] ?? $existing?->lft;
        $rgt = $data['rgt'] ?? $existing?->rgt;

        if ($lft === null || $rgt === null) {
            $next = (Company::max('rgt') ?? 0) + 1;
            $lft = $lft ?? $next;
            $rgt = $rgt ?? ($next + 1);
        }

        if ($rgt <= $lft) {
            $rgt = $lft + 1;
        }

        $data['lft'] = $lft;
        $data['rgt'] = $rgt;
    }

    /**
     * Recursively gather descendant IDs to prevent cycles.
     *
     * @return array<int, int>
     */
    protected function descendantIds(Company $company): array
    {
        $ids = [];

        $company->loadMissing('childCompanies');

        foreach ($company->childCompanies as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->descendantIds($child));
        }

        return $ids;
    }
}

<?php

namespace App\Http\Controllers;

use App\Exports\CustomerExport;
use App\Exports\CustomerImportTemplateExport;
use App\Http\Requests\ImportCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Imports\CustomerImport;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:customer-list', only: ['index', 'show', 'exportExcel']),
            new Middleware('can:customer-create', only: ['create', 'store', 'importForm', 'import', 'importTemplate']),
            new Middleware('can:customer-edit', only: ['edit', 'update']),
            new Middleware('can:customer-delete', only: ['destroy']),
        ];
    }

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100, 250];

    private const DEFAULT_PER_PAGE = 25;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $customers = $this->buildFilteredQuery()
            ->paginate($perPage)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'channelTypes' => Customer::CHANNEL_TYPES,
            'customerCategories' => Customer::CUSTOMER_CATEGORIES,
            'statusOptions' => ['1' => 'Active', '0' => 'Inactive'],
            'salesRepOptions' => $this->salesRepOptions(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'perPage' => $perPage,
            'stats' => $this->listStats($request),
        ]);
    }

    /**
     * Headline numbers for the list; counts follow the filters except the status tab.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    private function listStats(Request $request): array
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($v) => $v !== null && $v !== '');
        unset($filters['is_active']);

        $byStatus = QueryBuilder::for(Customer::class, new Request(['filter' => $filters]))
            ->allowedFilters($this->allowedFilters())
            ->getEloquentBuilder()
            ->reorder()
            ->selectRaw('is_active, COUNT(*) as aggregate')
            ->groupBy('is_active')
            ->pluck('aggregate', 'is_active');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[1] ?? 0),
            'inactive' => (int) ($byStatus[0] ?? 0),
        ];
    }

    /**
     * Excel import screen.
     */
    public function importForm()
    {
        return view('customers.import', [
            'channelTypes' => Customer::CHANNEL_TYPES,
            'customerCategories' => Customer::CUSTOMER_CATEGORIES,
        ]);
    }

    /**
     * Download the blank import template.
     */
    public function importTemplate()
    {
        return Excel::download(new CustomerImportTemplateExport, 'customers_import_template.xlsx');
    }

    /**
     * Import customers (shops) from Excel, skipping or updating duplicates.
     */
    public function import(ImportCustomerRequest $request)
    {
        $dryRun = $request->boolean('dry_run');
        $duplicateMode = $request->boolean('update_existing') || $request->validated('duplicate_mode') === CustomerImport::MODE_UPDATE
            ? CustomerImport::MODE_UPDATE
            : CustomerImport::MODE_SKIP;
        $import = new CustomerImport($duplicateMode, $dryRun);

        DB::beginTransaction();

        try {
            Excel::import($import, $request->file('import_file'));
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error importing customers', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return back()->withErrors(['import_file' => 'The file could not be read. Please use the template and try again.']);
        }

        return redirect()
            ->route('customers.import.form')
            ->with('import_result', [
                'dry_run' => $dryRun,
                'counts' => $import->counts(),
                'report' => $import->report(),
                'file' => $request->file('import_file')->getClientOriginalName(),
                'duplicate_mode' => $duplicateMode,
                'ignored_columns' => $import->ignoredColumns(),
            ]);
    }

    /**
     * Export filtered customers to Excel.
     */
    public function exportExcel(Request $request)
    {
        $query = $this->buildFilteredQuery()->getEloquentBuilder();

        return Excel::download(new CustomerExport($query), 'customers.xlsx');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('customers.create', [
            'channelTypes' => Customer::CHANNEL_TYPES,
            'customerCategories' => Customer::CUSTOMER_CATEGORIES,
            'accountOptions' => $this->accountOptions(),
            'salesRepOptions' => $this->salesRepOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $this->withoutBlankRequiredColumns($request->validated());
            $validated['is_active'] = array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : true;

            $customer = Customer::create($validated);

            DB::commit();

            return redirect()
                ->route('customers.index')
                ->with('success', "Customer '{$customer->customer_name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating customer', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create customer. Please review the input and try again.';
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'customers_customer_code_unique')) {
                    $message = 'The customer code must be unique.';
                } elseif (str_contains($e->getMessage(), 'customers_email_unique')) {
                    $message = 'The email address is already linked to another customer.';
                }
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating customer', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create customer. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $customer->load(['salesRep', 'receivableAccount', 'payableAccount']);

        return view('customers.show', [
            'customer' => $customer,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        $customer->load(['salesRep', 'receivableAccount', 'payableAccount']);

        return view('customers.edit', [
            'customer' => $customer,
            'channelTypes' => Customer::CHANNEL_TYPES,
            'customerCategories' => Customer::CUSTOMER_CATEGORIES,
            'accountOptions' => $this->accountOptions(),
            'salesRepOptions' => $this->salesRepOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        DB::beginTransaction();

        try {
            $validated = $this->withoutBlankRequiredColumns($request->validated());
            $validated['is_active'] = array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : $customer->is_active;

            $updated = $customer->update($validated);

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the customer.');
            }

            DB::commit();

            return redirect()
                ->route('customers.index')
                ->with('success', "Customer '{$customer->customer_name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating customer', [
                'customer_id' => $customer->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update customer. Please review the input and try again.';
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'customers_customer_code_unique')) {
                    $message = 'The customer code must be unique.';
                } elseif (str_contains($e->getMessage(), 'customers_email_unique')) {
                    $message = 'The email address is already linked to another customer.';
                }
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating customer', [
                'customer_id' => $customer->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update customer. Please try again.');
        }
    }

    /**
     * "Delete" only deactivates the customer; records are never removed. Super admins only.
     */
    public function destroy(Customer $customer)
    {
        $user = auth()->user();
        abort_unless($user->is_super_admin === 'Yes' || $user->hasRole('super-admin'), 403);

        try {
            $customer->update(['is_active' => false]);

            return redirect()
                ->route('customers.index')
                ->with('success', "Customer '{$customer->customer_name}' deactivated. Its history is kept.");
        } catch (\Throwable $e) {
            Log::error('Error deactivating customer', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to deactivate customer. Please try again.');
        }
    }

    /**
     * Build the filtered query shared by index and export.
     */
    private function buildFilteredQuery(): QueryBuilder
    {
        return QueryBuilder::for(
            Customer::query()->with('salesRep')
        )
            ->allowedFilters($this->allowedFilters())
            ->allowedSorts([
                AllowedSort::field('customer_name'),
                AllowedSort::field('customer_code'),
                AllowedSort::field('credit_limit'),
                AllowedSort::field('credit_used'),
                AllowedSort::field('city'),
                AllowedSort::field('channel_type'),
            ])
            ->defaultSort('customer_name');
    }

    /**
     * Blank inputs must keep the column default instead of writing NULL into NOT NULL columns.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withoutBlankRequiredColumns(array $validated): array
    {
        foreach (['credit_limit', 'credit_used', 'payment_terms', 'country'] as $column) {
            if (array_key_exists($column, $validated) && $validated[$column] === null) {
                unset($validated[$column]);
            }
        }

        return $validated;
    }

    /**
     * @return array<int, AllowedFilter>
     */
    private function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('search', fn ($query, $value) => $query->where(function ($q) use ($value) {
                $term = '%'.trim((string) $value).'%';
                $q->where('customer_name', 'like', $term)
                    ->orWhere('customer_code', 'like', $term)
                    ->orWhere('business_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('sub_locality', 'like', $term);
            })),
            AllowedFilter::partial('customer_name'),
            AllowedFilter::partial('customer_code'),
            AllowedFilter::partial('business_name'),
            AllowedFilter::partial('phone'),
            AllowedFilter::partial('email'),
            AllowedFilter::partial('city'),
            AllowedFilter::partial('sub_locality'),
            AllowedFilter::partial('address'),
            AllowedFilter::partial('ntn'),
            AllowedFilter::partial('state'),
            AllowedFilter::partial('country'),
            AllowedFilter::exact('channel_type'),
            AllowedFilter::exact('customer_category'),
            AllowedFilter::exact('sales_rep_id'),
            AllowedFilter::exact('is_active'),
        ];
    }

    /**
     * Fetch chart of account options for forms.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function accountOptions()
    {
        return ChartOfAccount::orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);
    }

    /**
     * Fetch available sales reps (users) in a deterministic order.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function salesRepOptions()
    {
        return User::orderBy('name')
            ->get(['id', 'name']);
    }
}

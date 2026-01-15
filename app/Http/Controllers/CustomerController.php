<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:customer-list', only: ['index', 'show']),
            new Middleware('can:customer-create', only: ['create', 'store']),
            new Middleware('can:customer-edit', only: ['edit', 'update']),
            new Middleware('can:customer-delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $customers = QueryBuilder::for(
            Customer::query()->with(['salesRep', 'receivableAccount', 'payableAccount'])
        )
            ->allowedFilters([
                AllowedFilter::partial('customer_name'),
                AllowedFilter::partial('customer_code'),
                AllowedFilter::partial('business_name'),
                AllowedFilter::partial('phone'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('city'),
                AllowedFilter::partial('sub_locality'),
                AllowedFilter::exact('channel_type'),
                AllowedFilter::exact('customer_category'),
                AllowedFilter::exact('sales_rep_id'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('customer_name')
            ->paginate(40)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'channelTypes' => Customer::CHANNEL_TYPES,
            'customerCategories' => Customer::CUSTOMER_CATEGORIES,
            'statusOptions' => ['1' => 'Active', '0' => 'Inactive'],
            'salesRepOptions' => $this->salesRepOptions(),
        ]);
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
            $validated = $request->validated();
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
            $validated = $request->validated();
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
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        try {
            $name = $customer->customer_name;
            $customer->delete();

            return redirect()
                ->route('customers.index')
                ->with('success', "Customer '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting customer', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete customer. Please try again.');
        }
    }

    /**
     * Fetch chart of account options for forms.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function accountOptions()
    {
        return ChartOfAccount::orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);
    }

    /**
     * Fetch available sales reps (users) in a deterministic order.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function salesRepOptions()
    {
        return User::orderBy('name')
            ->get(['id', 'name']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountTypeRequest;
use App\Http\Requests\UpdateAccountTypeRequest;
use App\Models\AccountType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * AccountTypeController handles CRUD operations for account types
 * Manages account type categorization for the chart of accounts
 */
class AccountTypeController extends Controller
{
    /**
     * Display paginated list of account types with filtering capabilities
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get unique type names for filter dropdown
        $typeNames = AccountType::whereNotNull('type_name')
            ->distinct()
            ->orderBy('type_name')
            ->pluck('type_name');

        // Get unique report groups for filter dropdown
        $reportGroups = AccountType::whereNotNull('report_group')
            ->distinct()
            ->orderBy('report_group')
            ->pluck('report_group');

        // Build query with filters using Spatie QueryBuilder
        $accountTypes = QueryBuilder::for(AccountType::class)
            ->allowedFilters([
                AllowedFilter::exact('type_name'),           // Filter by exact type name
                AllowedFilter::exact('report_group'),        // Filter by exact report group
            ])
            ->latest()                                       // Order by newest first
            ->paginate(5);                                  // Paginate results

        return view('accounting.account-types.index', compact('accountTypes', 'reportGroups', 'typeNames'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('accounting.account-types.create');
    }

    /**
     * Store new account type
     * Uses transaction for data consistency
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreAccountTypeRequest $request)
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            // Get validated data from form request
            $validated = $request->validated();

            // Create account type record in database
            $accountType = AccountType::create($validated);

            // Commit transaction if everything successful
            DB::commit();

            return redirect()
                ->route('account-types.index')
                ->with('success', "Account Type '{$accountType->type_name}' created successfully.");

        } catch (\Illuminate\Database\QueryException $e) {
            // Rollback transaction on database error
            DB::rollBack();

            // Handle duplicate type name error
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'type_name')) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'An account type with this name already exists. Please choose a unique name for your account type.');
            }

            // Show friendly error and actual DB error
            return redirect()->back()
                ->withInput()
                ->with('error', [
                    'message' => 'Something went wrong. Please try again.',
                    'db' => $e->getMessage(),
                ]);

        } catch (\Exception $e) {
            // Rollback transaction on any other error
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create account type. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(AccountType $accountType)
    {
        return view('accounting.account-types.show', compact('accountType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccountType $accountType)
    {
        return view('accounting.account-types.edit', compact('accountType'));
    }

    /**
     * Update existing account type
     * Uses transaction for data consistency
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateAccountTypeRequest $request, AccountType $accountType)
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            // Get validated data from form request
            $validated = $request->validated();

            // Update account type record
            $isUpdated = $accountType->update($validated);

            // Check if any changes were actually made
            if (! $isUpdated) {
                DB::rollBack();

                return redirect()->back()
                    ->with('info', 'No changes were made to the account type.');
            }

            // Commit transaction if update successful
            DB::commit();

            return redirect()
                ->route('account-types.index')
                ->with('success', "Account Type '{$accountType->type_name}' updated successfully.");

        } catch (\Illuminate\Database\QueryException $e) {
            // Rollback transaction on database error
            DB::rollBack();

            // Handle duplicate type name error
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'type_name')) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'An account type with this name already exists. Please choose a unique name for your account type.');
            }

            // Log database errors for debugging
            Log::error('Database error updating account type', [
                'account_type_id' => $accountType->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', [
                    'message' => 'Something went wrong. Please try again.',
                    'db' => $e->getMessage(),
                ]);

        } catch (\Exception $e) {
            // Rollback transaction on any other error
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update account type. Please try again.');
        }
    }
}

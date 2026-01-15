<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BankAccountController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-account-list', only: ['index', 'show']),
            new Middleware('can:bank-account-create', only: ['create', 'store']),
            new Middleware('can:bank-account-edit', only: ['edit', 'update']),
            new Middleware('can:bank-account-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $accounts = QueryBuilder::for(
            BankAccount::query()->with('chartOfAccount')
        )
            ->allowedFilters([
                AllowedFilter::partial('account_name'),
                AllowedFilter::partial('account_number'),
                AllowedFilter::partial('bank_name'),
                AllowedFilter::partial('iban'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('id')
            ->paginate(40)
            ->withQueryString();

        return view('bank-accounts.index', [
            'accounts' => $accounts,
            'statusOptions' => ['1' => 'Active', '0' => 'Inactive'],
        ]);
    }

    public function create()
    {
        return view('bank-accounts.create', [
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    public function store(StoreBankAccountRequest $request)
    {
        DB::beginTransaction();

        try {
            $payload = $request->validated();
            $payload['is_active'] = array_key_exists('is_active', $payload)
                ? (bool) $payload['is_active']
                : true;

            $account = BankAccount::create($payload);

            DB::commit();

            return redirect()
                ->route('bank-accounts.index')
                ->with('success', "Bank account '{$account->account_name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating bank account', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create bank account. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'bank_accounts_account_number_unique')) {
                $message = 'The account number must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating bank account', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create bank account. Please try again.');
        }
    }

    public function show(BankAccount $bankAccount)
    {
        $bankAccount->load('chartOfAccount');

        return view('bank-accounts.show', [
            'account' => $bankAccount,
        ]);
    }

    public function edit(BankAccount $bankAccount)
    {
        $bankAccount->load('chartOfAccount');

        return view('bank-accounts.edit', [
            'account' => $bankAccount,
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount)
    {
        DB::beginTransaction();

        try {
            $payload = $request->validated();
            $payload['is_active'] = array_key_exists('is_active', $payload)
                ? (bool) $payload['is_active']
                : $bankAccount->is_active;

            $updated = $bankAccount->update($payload);

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the bank account.');
            }

            DB::commit();

            return redirect()
                ->route('bank-accounts.index')
                ->with('success', "Bank account '{$bankAccount->account_name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating bank account', [
                'account_id' => $bankAccount->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update bank account. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'bank_accounts_account_number_unique')) {
                $message = 'The account number must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating bank account', [
                'account_id' => $bankAccount->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update bank account. Please try again.');
        }
    }

    public function destroy(BankAccount $bankAccount)
    {
        try {
            $name = $bankAccount->account_name;
            $bankAccount->delete();

            return redirect()
                ->route('bank-accounts.index')
                ->with('success', "Bank account '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting bank account', [
                'account_id' => $bankAccount->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete bank account. Please try again.');
        }
    }

    protected function accountOptions()
    {
        return ChartOfAccount::orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);
    }
}

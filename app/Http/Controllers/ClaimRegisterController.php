<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClaimRegisterRequest;
use App\Http\Requests\UpdateClaimRegisterRequest;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ClaimRegister;
use App\Models\Supplier;
use App\Services\ClaimRegisterService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClaimRegisterController extends Controller implements HasMiddleware
{
    public function __construct(private ClaimRegisterService $claimService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:claim-register-list', only: ['index', 'show']),
            new Middleware('can:claim-register-create', only: ['create', 'store']),
            new Middleware('can:claim-register-edit', only: ['edit', 'update']),
            new Middleware('can:claim-register-delete', only: ['destroy']),
            new Middleware('can:claim-register-post', only: ['post']),
        ];
    }

    public function index(Request $request)
    {
        $claims = QueryBuilder::for(ClaimRegister::with('supplier'))
            ->allowedFilters([
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('transaction_type'),
                AllowedFilter::partial('reference_number'),
                AllowedFilter::partial('claim_month'),
                AllowedFilter::callback('transaction_date_from', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('transaction_date', '>=', $value) : null),
                AllowedFilter::callback('transaction_date_to', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('transaction_date', '<=', $value) : null),
            ])
            ->orderByDesc('transaction_date')
            ->paginate(15)
            ->withQueryString();

        return view('claim-registers.index', [
            'claims' => $claims,
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
        ]);
    }

    public function create()
    {
        return view('claim-registers.create', $this->formData());
    }

    public function store(StoreClaimRegisterRequest $request)
    {
        $data = $request->validated();
        $data = $this->setDefaultAccounts($data);

        $result = $this->claimService->createClaim($data);

        if ($result['success']) {
            return redirect()
                ->route('claim-registers.index')
                ->with('success', $result['message']);
        }

        return back()
            ->withInput()
            ->with('error', $result['message']);
    }

    public function show(ClaimRegister $claimRegister)
    {
        $claimRegister->load(['supplier', 'debitAccount', 'creditAccount', 'bankAccount', 'journalEntry', 'postedByUser']);

        return view('claim-registers.show', [
            'claimRegister' => $claimRegister,
        ]);
    }

    public function edit(ClaimRegister $claimRegister)
    {
        if ($claimRegister->isPosted()) {
            return back()->with('error', 'Posted claims cannot be edited.');
        }

        $claimRegister->load(['supplier', 'debitAccount', 'creditAccount', 'bankAccount']);

        return view('claim-registers.edit', array_merge(
            ['claimRegister' => $claimRegister],
            $this->formData()
        ));
    }

    public function update(UpdateClaimRegisterRequest $request, ClaimRegister $claimRegister)
    {
        if ($claimRegister->isPosted()) {
            return back()->with('error', 'Posted claims cannot be edited.');
        }

        $data = $request->validated();
        $data = $this->setDefaultAccounts($data);

        $result = $this->claimService->updateClaim($claimRegister, $data);

        if ($result['success']) {
            return redirect()
                ->route('claim-registers.index')
                ->with('success', $result['message']);
        }

        return back()
            ->withInput()
            ->with('error', $result['message']);
    }

    public function destroy(ClaimRegister $claimRegister)
    {
        if ($claimRegister->isPosted()) {
            return back()->with('error', 'Posted claims cannot be deleted.');
        }

        if ($claimRegister->status === 'Adjusted') {
            return back()->with('error', 'Fully adjusted claims cannot be deleted.');
        }

        try {
            $claimRegister->delete();

            return redirect()
                ->route('claim-registers.index')
                ->with('success', "Claim '{$claimRegister->reference_number}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting claim register', [
                'claim_register_id' => $claimRegister->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete claim. Please try again.');
        }
    }

    /**
     * Post the claim to GL (creates journal entry).
     */
    public function post(Request $request, ClaimRegister $claimRegister)
    {
        if ($claimRegister->isPosted()) {
            return back()->with('error', 'Claim is already posted.');
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($request->password, auth()->user()->password)) {
            Log::warning("Failed claim posting attempt for {$claimRegister->reference_number} - Invalid password by user: ".auth()->user()->name);

            return back()->with('error', 'Invalid password. Posting requires your password confirmation.');
        }

        Log::info("Claim posting password confirmed for {$claimRegister->reference_number} by user: ".auth()->user()->name.' (ID: '.auth()->id().')');

        $result = $this->claimService->postClaim($claimRegister);

        if ($result['success']) {
            return redirect()
                ->route('claim-registers.show', $claimRegister)
                ->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Shared dropdown data for create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
        ];
    }

    /**
     * Auto-set GL accounts, convert amount to debit/credit, and set bank info.
     *
     * Converts UI input (transaction_type + amount) to proper double-entry (debit/credit):
     * - Claim: debit = amount, credit = 0
     * - Recovery: debit = 0, credit = amount
     *
     * Also sets:
     * - debit_account = 1112 Pending Claims Debtors
     * - credit_account = HBL Main Bank's COA
     * - payment_method = bank_transfer
     */
    private function setDefaultAccounts(array $data): array
    {
        // Convert amount input to debit/credit based on transaction_type
        if (isset($data['amount'])) {
            $amount = (float) $data['amount'];
            $transactionType = $data['transaction_type'] ?? 'claim';

            if ($transactionType === 'claim') {
                $data['debit'] = $amount;
                $data['credit'] = 0;
            } else {
                // recovery
                $data['debit'] = 0;
                $data['credit'] = $amount;
            }

            // Remove amount field as it's not stored in DB
            unset($data['amount']);
        }

        // Set debit account to 1112 (Pending Claims Debtors)
        $debtorsAccount = ChartOfAccount::where('account_code', '1112')->first();
        if ($debtorsAccount) {
            $data['debit_account_id'] = $debtorsAccount->id;
        }

        // Set credit account to HBL Main Bank's linked COA
        $hblBank = BankAccount::where('account_name', 'LIKE', '%HBL%')
            ->orWhere('account_name', 'LIKE', '%Main%')
            ->first();

        if ($hblBank && $hblBank->chart_of_account_id) {
            $data['credit_account_id'] = $hblBank->chart_of_account_id;
            $data['bank_account_id'] = $hblBank->id;
        }

        // Default payment method
        $data['payment_method'] = 'bank_transfer';

        return $data;
    }
}

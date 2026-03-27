<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClaimRegisterRequest;
use App\Http\Requests\UpdateClaimRegisterRequest;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ClaimRegister;
use App\Models\Supplier;
use App\Services\ClaimRegisterService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;

class ClaimRegisterReportController extends Controller implements HasMiddleware
{
    public function __construct(private ClaimRegisterService $claimService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-claim-register', only: ['index']),
            new Middleware('can:claim-register-create', only: ['store']),
            new Middleware('can:claim-register-edit', only: ['update']),
            new Middleware('can:claim-register-post', only: ['post']),
            new Middleware('can:claim-register-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = \in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 50;

        // Default supplier: Nestlé
        $defaultSupplier = Supplier::where('short_name', 'Nestle')->first();
        $supplierId = $request->input('supplier_id', $defaultSupplier?->id);

        // Date range defaults: current month
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());
        $claimMonth = $request->input('claim_month');
        $transactionType = $request->input('transaction_type');
        $referenceNumber = $request->input('reference_number');
        $description = $request->input('description');
        $postedStatus = $request->input('posted_status');

        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get();
        $transactionTypeOptions = ClaimRegister::transactionTypeOptions();

        $openingBalances = [];
        $openingBalance = 0;
        if ($dateFrom) {
            $openingQuery = ClaimRegister::query();

            if ($supplierId) {
                $openingQuery->where('supplier_id', $supplierId);
            }

            $openingQuery->whereDate('transaction_date', '<', $dateFrom);

            $openingRecords = $openingQuery->get();
            foreach ($openingRecords as $record) {
                if (! isset($openingBalances[$record->supplier_id])) {
                    $openingBalances[$record->supplier_id] = 0;
                }
                $openingBalances[$record->supplier_id] += (float) $record->debit - (float) $record->credit;
                $openingBalance += (float) $record->debit - (float) $record->credit;
            }
        }

        $query = ClaimRegister::with('supplier')
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($claimMonth) {
            $query->where('claim_month', 'like', "%{$claimMonth}%");
        }

        if ($transactionType) {
            $query->where('transaction_type', $transactionType);
        }

        if ($referenceNumber) {
            $query->where('reference_number', 'like', '%'.$referenceNumber.'%');
        }

        if ($description) {
            $query->where('description', 'like', '%'.$description.'%');
        }

        if ($postedStatus === 'posted') {
            $query->whereNotNull('posted_at');
        } elseif ($postedStatus === 'unposted') {
            $query->whereNull('posted_at');
        }

        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        if ($perPage === 'all') {
            $claims = $query->get();
            $claims = new LengthAwarePaginator(
                $claims,
                $claims->count(),
                $claims->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $claims = $query->paginate((int) $perPage)->withQueryString();
        }

        $totals = [
            'debit' => (clone $query)->sum('debit'),
            'credit' => (clone $query)->sum('credit'),
        ];
        $totals['net_balance'] = $totals['debit'] - $totals['credit'];
        $closingBalance = $openingBalance + $totals['net_balance'];

        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;

        return view('reports.claim-register.index', compact(
            'claims',
            'suppliers',
            'transactionTypeOptions',
            'supplierId',
            'selectedSupplier',
            'claimMonth',
            'transactionType',
            'referenceNumber',
            'description',
            'postedStatus',
            'dateFrom',
            'dateTo',
            'openingBalance',
            'openingBalances',
            'closingBalance',
            'totals',
            'perPage'
        ));
    }

    public function store(StoreClaimRegisterRequest $request)
    {
        $data = $request->validated();
        $data = $this->setDefaultAccounts($data);

        $result = $this->claimService->createClaim($data);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->withInput()->with('error', $result['message']);
    }

    public function update(UpdateClaimRegisterRequest $request, ClaimRegister $claimRegister)
    {
        if ($claimRegister->isPosted()) {
            return redirect()->back()->with('error', 'Posted claims cannot be edited.');
        }

        $data = $request->validated();
        $data = $this->setDefaultAccounts($data);

        $result = $this->claimService->updateClaim($claimRegister, $data);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->withInput()->with('error', $result['message']);
    }

    public function destroy(ClaimRegister $claimRegister)
    {
        if ($claimRegister->isPosted()) {
            return redirect()->back()->with('error', 'Posted claims cannot be deleted.');
        }

        if ($claimRegister->status === 'Adjusted') {
            return redirect()->back()->with('error', 'Fully adjusted claims cannot be deleted.');
        }

        try {
            $claimRegister->delete();

            return redirect()->back()->with('success', "Claim '{$claimRegister->reference_number}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('ClaimRegister report destroy error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete claim. Please try again.');
        }
    }

    public function post(ClaimRegister $claimRegister)
    {
        if ($claimRegister->isPosted()) {
            return redirect()->back()->with('error', 'Claim is already posted.');
        }

        $result = $this->claimService->postClaim($claimRegister);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Auto-set GL accounts, convert amount to debit/credit, and set bank info.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function setDefaultAccounts(array $data): array
    {
        if (isset($data['amount'])) {
            $amount = (float) $data['amount'];
            $transactionType = $data['transaction_type'] ?? 'claim';

            if ($transactionType === 'claim') {
                $data['debit'] = $amount;
                $data['credit'] = 0;
            } else {
                $data['debit'] = 0;
                $data['credit'] = $amount;
            }

            unset($data['amount']);
        }

        $debtorsAccount = ChartOfAccount::where('account_code', '1112')->first();
        if ($debtorsAccount) {
            $data['debit_account_id'] = $debtorsAccount->id;
        }

        $bankAccount = ChartOfAccount::where('account_code', '1171')->first();
        if ($bankAccount) {
            $data['credit_account_id'] = $bankAccount->id;

            $hblBank = BankAccount::where('chart_of_account_id', $bankAccount->id)->first();
            if ($hblBank) {
                $data['bank_account_id'] = $hblBank->id;
            }
        }

        $data['payment_method'] = 'bank_transfer';

        return $data;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVendorPayment;
use App\Services\ExpenseVendorPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Month-end payments to shops for their credit vouchers (maker and checker).
 */
class ExpenseVendorPaymentController extends Controller implements HasMiddleware
{
    public function __construct(private ExpenseVendorPaymentService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:expense-vendor-payment-list', only: ['index', 'show']),
            new Middleware('can:expense-vendor-payment-create', only: ['create', 'store', 'vouchers']),
            new Middleware('can:expense-vendor-payment-approve', only: ['approve', 'reject']),
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');

        $payments = ExpenseVendorPayment::query()
            ->visibleTo($user)
            ->with(['vendor:id,name,city', 'supplier:id,supplier_name,short_name', 'company:id,company_name,abbr', 'creator:id,name'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['vendor_id'] ?? null, fn ($query, $id) => $query->where('expense_vendor_id', $id))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('payment_date', '<=', $date))
            ->latest('payment_date')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('expense-vendor-payments.index', [
            'payments' => $payments,
            'filters' => $filters,
            'statuses' => ExpenseVendorPayment::statusLabels(),
            'vendorFilter' => isset($filters['vendor_id']) ? ExpenseVendor::withTrashed()->find($filters['vendor_id']) : null,
        ]);
    }

    public function create(Request $request)
    {
        return view('expense-vendor-payments.create', [
            'vendor' => $request->filled('vendor') ? ExpenseVendor::find($request->query('vendor')) : null,
            'bankAccounts' => $this->bankAccounts(),
        ]);
    }

    /**
     * The credit vouchers a shop is still owed for, as the payment form loads them.
     */
    public function vouchers(Request $request, ExpenseVendor $expenseVendor): JsonResponse
    {
        $vouchers = $this->service->outstandingVouchers($expenseVendor, $request->user());

        return response()->json(['vouchers' => $vouchers->map(fn ($voucher) => [
            'id' => $voucher->id,
            'uuid' => $voucher->uuid,
            'number' => $voucher->voucher_number,
            'date' => $voucher->voucher_date->format('d M Y'),
            'bill' => $voucher->vendor_bill_no,
            'owner' => (string) ($voucher->supplier?->short_name ?? $voucher->supplier?->supplier_name ?? $voucher->company?->company_name ?? 'Moon Traders'),
            'owner_key' => $voucher->supplier_id ? 's'.$voucher->supplier_id : 'c'.$voucher->company_id,
            'amount' => (float) $voucher->amount,
            'outstanding' => $voucher->outstanding(),
            'narration' => $voucher->narration,
        ])->values()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'expense_vendor_id' => ['required', 'exists:expense_vendors,id'],
            'method' => ['required', Rule::in(['cash', 'bank'])],
            'bank_account_id' => ['required_if:method,bank', 'nullable', 'exists:bank_accounts,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'narration' => ['nullable', 'string', 'max:1000'],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $amounts = $data['amounts'];
        unset($data['amounts']);
        $data['bank_account_id'] = $data['method'] === 'bank' ? $data['bank_account_id'] : null;

        $visible = $this->service->outstandingVouchers(ExpenseVendor::findOrFail($data['expense_vendor_id']), $request->user())->pluck('id')->all();
        abort_if(array_diff(array_keys(array_filter($amounts, fn ($amount) => (float) $amount > 0)), $visible) !== [], 403, 'You can only pay vouchers you are allowed to see.');

        try {
            $payment = $this->service->create($data, $amounts, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('expense-vendor-payments.show', $payment)->with('success', 'Payment '.$payment->payment_number.' sent to the checker.');
    }

    public function show(Request $request, ExpenseVendorPayment $expenseVendorPayment)
    {
        $this->authorizeVisible($request, $expenseVendorPayment);

        return view('expense-vendor-payments.show', [
            'payment' => $expenseVendorPayment->load(['vendor', 'supplier', 'company', 'bankAccount', 'creator', 'checker', 'allocations.voucher', 'journalEntry.details.account']),
        ]);
    }

    public function approve(Request $request, ExpenseVendorPayment $expenseVendorPayment): RedirectResponse
    {
        $this->authorizeVisible($request, $expenseVendorPayment);

        return $this->attempt(fn () => $this->service->approve($expenseVendorPayment, $request->user()), $expenseVendorPayment, 'Payment approved and posted to the ledger.');
    }

    public function reject(Request $request, ExpenseVendorPayment $expenseVendorPayment): RedirectResponse
    {
        $this->authorizeVisible($request, $expenseVendorPayment);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->reject($expenseVendorPayment, $request->user(), $validated['rejection_reason']), $expenseVendorPayment, 'Payment rejected.');
    }

    private function authorizeVisible(Request $request, ExpenseVendorPayment $payment): void
    {
        abort_unless($payment->canBeSeenBy($request->user()), 404);
    }

    private function attempt(callable $action, ExpenseVendorPayment $payment, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return redirect()->route('expense-vendor-payments.show', $payment)->with('error', $e->getMessage());
        }

        return redirect()->route('expense-vendor-payments.show', $payment)->with('success', $message);
    }

    private function bankAccounts()
    {
        return BankAccount::query()->where('is_active', true)->whereNotNull('chart_of_account_id')->orderBy('bank_name')->get(['id', 'bank_name', 'account_name', 'account_number']);
    }
}

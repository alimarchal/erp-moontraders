<?php

namespace App\Http\Controllers;

use App\Models\SupplierPayment;
use App\Models\Supplier;
use App\Models\BankAccount;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class SupplierPaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Display a listing of supplier payments
     */
    public function index(Request $request)
    {
        $payments = QueryBuilder::for(
            SupplierPayment::query()->with(['supplier', 'bankAccount', 'createdBy'])
        )
            ->allowedFilters([
                AllowedFilter::partial('payment_number'),
                AllowedFilter::partial('reference_number'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('payment_method'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('payment_date_from'),
                AllowedFilter::scope('payment_date_to'),
            ])
            ->defaultSort('-payment_date')
            ->paginate(20)
            ->withQueryString();

        return view('supplier-payments.index', [
            'payments' => $payments,
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
        ]);
    }

    /**
     * Show the form for creating a new payment
     */
    public function create(Request $request)
    {
        $suppliers = Supplier::orderBy('supplier_name')->get();
        $bankAccounts = BankAccount::orderBy('account_name')->get();

        $selectedSupplier = null;
        $unpaidGrns = [];
        $supplierBalance = 0;

        if ($request->filled('supplier_id')) {
            $selectedSupplier = Supplier::find($request->supplier_id);
            if ($selectedSupplier) {
                $unpaidGrns = $this->paymentService->getUnpaidGrns($selectedSupplier->id);
                $supplierBalance = $this->paymentService->getSupplierBalance($selectedSupplier->id);
            }
        }

        return view('supplier-payments.create', compact(
            'suppliers',
            'bankAccounts',
            'selectedSupplier',
            'unpaidGrns',
            'supplierBalance'
        ));
    }

    /**
     * Store a newly created payment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:bank_transfer,cash,cheque,online',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'reference_number' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'grn_allocations' => 'nullable|array',
            'grn_allocations.*.grn_id' => 'required|exists:goods_receipt_notes,id',
            'grn_allocations.*.amount' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Generate payment number
            $lastPayment = SupplierPayment::whereYear('created_at', now()->year)
                ->orderBy('id', 'desc')
                ->first();
            $nextNumber = $lastPayment ? ((int) substr($lastPayment->payment_number, -6)) + 1 : 1;
            $paymentNumber = sprintf('PAY-%d-%06d', now()->year, $nextNumber);

            // Create payment
            $payment = SupplierPayment::create([
                'payment_number' => $paymentNumber,
                'supplier_id' => $validated['supplier_id'],
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            // Create GRN allocations if provided
            if (!empty($validated['grn_allocations'])) {
                foreach ($validated['grn_allocations'] as $allocation) {
                    if ($allocation['amount'] > 0) {
                        $payment->grnAllocations()->create([
                            'grn_id' => $allocation['grn_id'],
                            'allocated_amount' => $allocation['amount'],
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('supplier-payments.show', $payment)
                ->with('success', "Payment {$payment->payment_number} created successfully");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to create payment: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified payment
     */
    public function show(SupplierPayment $supplierPayment)
    {
        $supplierPayment->load(['supplier', 'bankAccount', 'journalEntry.details.account', 'grnAllocations.grn', 'createdBy', 'postedBy']);

        return view('supplier-payments.show', compact('supplierPayment'));
    }

    /**
     * Post the payment
     */
    public function post(SupplierPayment $supplierPayment)
    {
        if ($supplierPayment->status !== 'draft') {
            return back()->with('error', 'Only draft payments can be posted');
        }

        $result = $this->paymentService->postSupplierPayment($supplierPayment);

        if ($result['success']) {
            return redirect()
                ->route('supplier-payments.show', $supplierPayment)
                ->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Get unpaid GRNs for a supplier (AJAX)
     */
    public function getUnpaidGrns(Supplier $supplier)
    {
        $unpaidGrns = $this->paymentService->getUnpaidGrns($supplier->id);
        $balance = $this->paymentService->getSupplierBalance($supplier->id);

        return response()->json([
            'unpaid_grns' => $unpaidGrns,
            'supplier_balance' => $balance,
        ]);
    }

    /**
     * Remove the specified payment (soft delete)
     */
    public function destroy(SupplierPayment $supplierPayment)
    {
        if ($supplierPayment->status === 'posted') {
            return back()->with('error', 'Posted payments cannot be deleted');
        }

        $supplierPayment->delete();

        return redirect()
            ->route('supplier-payments.index')
            ->with('success', 'Payment deleted successfully');
    }
}

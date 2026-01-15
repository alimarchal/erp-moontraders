<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SupplierPaymentController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:supplier-payment-list', only: ['index', 'show']),
            new Middleware('permission:supplier-payment-create', only: ['create', 'store', 'getUnpaidGrns']),
            new Middleware('permission:supplier-payment-edit', only: ['edit', 'update']),
            new Middleware('permission:supplier-payment-delete', only: ['destroy']),
            new Middleware('permission:supplier-payment-post', only: ['post']),
            new Middleware('permission:supplier-payment-reverse', only: ['reverse']),
        ];
    }

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
        // Filter out empty allocations before validation
        if ($request->has('grn_allocations')) {
            $allocations = array_filter($request->grn_allocations, function ($allocation) {
                return ! empty($allocation['grn_id']) && isset($allocation['amount']) && $allocation['amount'] > 0;
            });
            $request->merge(['grn_allocations' => array_values($allocations)]);
        }

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
            'grn_allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            // Generate payment number with lock to prevent duplicates
            $year = now()->year;
            $lastPayment = SupplierPayment::withTrashed()
                ->whereYear('created_at', $year)
                ->where('payment_number', 'LIKE', "PAY-{$year}-%")
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $sequence = $lastPayment ? ((int) substr($lastPayment->payment_number, -6)) + 1 : 1;
            $paymentNumber = sprintf('PAY-%d-%06d', $year, $sequence);

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
            if (! empty($validated['grn_allocations'])) {
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

            return back()->withInput()->with('error', 'Failed to create payment: '.$e->getMessage());
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
     * Show the form for editing the payment
     */
    public function edit(SupplierPayment $supplierPayment)
    {
        if ($supplierPayment->status !== 'draft') {
            return redirect()
                ->route('supplier-payments.show', $supplierPayment)
                ->with('error', 'Only draft payments can be edited');
        }

        $supplierPayment->load(['supplier', 'grnAllocations.grn']);

        // Get unpaid GRNs for this supplier
        $unpaidGrns = $this->paymentService->getUnpaidGrns($supplierPayment->supplier_id);

        return view('supplier-payments.edit', [
            'payment' => $supplierPayment,
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('account_name')->get(),
            'unpaidGrns' => $unpaidGrns,
        ]);
    }

    /**
     * Update the payment
     */
    public function update(Request $request, SupplierPayment $supplierPayment)
    {
        if ($supplierPayment->status !== 'draft') {
            return back()->with('error', 'Only draft payments can be updated');
        }

        // Filter out empty allocations
        $allocations = array_filter($request->grn_allocations ?? [], function ($allocation) {
            return ! empty($allocation['grn_id']) && isset($allocation['amount']) && $allocation['amount'] > 0;
        });

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'payment_method' => 'required|in:cash,cheque,bank_transfer,online',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'reference_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'grn_allocations' => 'required|array|min:1',
            'grn_allocations.*.grn_id' => 'required|exists:goods_receipt_notes,id',
            'grn_allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            // Calculate total amount
            $totalAmount = collect($allocations)->sum('amount');

            // Update payment
            $supplierPayment->update([
                'payment_date' => $validated['payment_date'],
                'supplier_id' => $validated['supplier_id'],
                'payment_method' => $validated['payment_method'],
                'bank_account_id' => $validated['bank_account_id'],
                'reference_number' => $validated['reference_number'],
                'description' => $validated['description'],
                'amount' => $totalAmount,
            ]);

            // Delete existing allocations and create new ones
            $supplierPayment->grnAllocations()->delete();

            foreach ($allocations as $allocation) {
                $supplierPayment->grnAllocations()->create([
                    'grn_id' => $allocation['grn_id'],
                    'allocated_amount' => $allocation['amount'],
                ]);
            }

            DB::commit();

            return redirect()
                ->route('supplier-payments.show', $supplierPayment)
                ->with('success', "Payment {$supplierPayment->payment_number} updated successfully");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Failed to update payment: '.$e->getMessage());
        }
    }

    /**
     * Post the payment
     */
    public function post(Request $request, SupplierPayment $supplierPayment)
    {
        if ($supplierPayment->status !== 'draft') {
            return back()->with('error', 'Only draft payments can be posted');
        }

        // Validate password
        $request->validate([
            'password' => 'required|string',
        ]);

        // Verify user's password
        if (! \Hash::check($request->password, auth()->user()->password)) {
            \Log::warning("Failed payment posting attempt for {$supplierPayment->payment_number} - Invalid password by user: ".auth()->user()->name);

            return back()->with('error', 'Invalid password. Payment posting requires your password confirmation.');
        }

        // Log password confirmation
        \Log::info("Payment posting password confirmed for {$supplierPayment->payment_number} by user: ".auth()->user()->name.' (ID: '.auth()->id().')');

        // Validate bank account is selected for non-cash payments
        if (in_array($supplierPayment->payment_method, ['bank_transfer', 'cheque', 'online']) && ! $supplierPayment->bank_account_id) {
            return back()->with('error', 'Bank account is required for '.str_replace('_', ' ', $supplierPayment->payment_method).' payments. Please edit the payment and select a bank account.');
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
     * Reverse a posted payment (with password confirmation)
     */
    public function reverse(Request $request, SupplierPayment $supplierPayment)
    {
        if ($supplierPayment->status !== 'posted') {
            return back()->with('error', 'Only posted payments can be reversed');
        }

        // Validate password
        $request->validate([
            'password' => 'required|string',
        ]);

        // Verify user password
        if (! \Hash::check($request->password, auth()->user()->password)) {
            return back()->with('error', 'Invalid password. Payment reversal cancelled.');
        }

        $result = $this->paymentService->reverseSupplierPayment($supplierPayment);

        if ($result['success']) {
            return redirect()
                ->route('supplier-payments.index')
                ->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Remove the specified payment (soft delete)
     */
    public function destroy(SupplierPayment $supplierPayment)
    {
        if ($supplierPayment->status === 'posted') {
            return back()->with('error', 'Posted payments cannot be deleted. Use reverse instead.');
        }

        $supplierPayment->delete();

        return redirect()
            ->route('supplier-payments.index')
            ->with('success', 'Payment deleted successfully');
    }
}

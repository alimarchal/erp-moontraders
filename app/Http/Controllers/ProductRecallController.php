<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ClaimRegister;
use App\Models\Product;
use App\Models\ProductRecall;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ProductRecallService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductRecallController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:product-recall-list', only: ['index', 'show']),
            new Middleware('permission:product-recall-create', only: ['create', 'store', 'getBatchesForSupplier']),
            new Middleware('permission:product-recall-edit', only: ['edit', 'update']),
            new Middleware('permission:product-recall-delete', only: ['destroy']),
            new Middleware('permission:product-recall-post', only: ['post']),
            new Middleware('permission:product-recall-cancel', only: ['cancel']),
        ];
    }

    public function index(Request $request)
    {
        $canViewAllSuppliers = $this->canViewAllSuppliers();
        $userSupplierId = $this->getUserSupplierScope();
        $requestedSupplierId = $request->input('filter.supplier_id');

        if ($requestedSupplierId && ! $canViewAllSuppliers && (int) $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        $supplierId = $userSupplierId ?? $requestedSupplierId;
        $baseQuery = ProductRecall::query()
            ->with(['supplier', 'warehouse', 'createdBy', 'postedBy'])
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'));

        $recalls = QueryBuilder::for(
            $baseQuery
        )
            ->allowedFilters([
                AllowedFilter::partial('recall_number'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('recall_type'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('recall_date_from'),
                AllowedFilter::scope('recall_date_to'),
            ])
            ->defaultSort('-recall_date')
            ->paginate(20)
            ->withQueryString();

        return view('product-recalls.index', [
            'recalls' => $recalls,
            'suppliers' => $this->supplierOptions(),
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'supplierId' => $supplierId,
            'canViewAllSuppliers' => $canViewAllSuppliers,
        ]);
    }

    public function create()
    {
        return view('product-recalls.create', [
            'suppliers' => $this->supplierOptions(),
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recall_date' => 'required|date|before_or_equal:today',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'grn_id' => 'nullable|exists:goods_receipt_notes,id',
            'recall_type' => 'required|in:supplier_initiated,quality_issue,expiry,other',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.stock_batch_id' => 'required|exists:stock_batches,id',
            'items.*.grn_item_id' => 'nullable|exists:goods_receipt_note_items,id',
            'items.*.quantity_recalled' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $this->authorizeRecallSupplier((int) $validated['supplier_id']);
        $this->authorizeRecallItems($validated);

        foreach ($validated['items'] as &$item) {
            $item['total_value'] = $item['quantity_recalled'] * $item['unit_cost'];
        }

        $service = app(ProductRecallService::class);
        $result = $service->createRecall($validated);

        if ($result['success']) {
            return redirect()->route('product-recalls.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message'])->withInput();
    }

    public function show(ProductRecall $productRecall)
    {
        $this->authorizeProductRecallAccess($productRecall);

        $productRecall->load([
            'supplier',
            'warehouse',
            'grn',
            'items.product',
            'items.stockBatch',
            'items.grnItem',
            'stockAdjustment.journalEntry',
            'claimRegister',
            'createdBy',
            'postedBy',
        ]);

        return view('product-recalls.show', compact('productRecall'));
    }

    public function edit(ProductRecall $productRecall)
    {
        $this->authorizeProductRecallAccess($productRecall);

        if ($productRecall->status !== 'draft') {
            return redirect()->route('product-recalls.show', $productRecall)
                ->with('error', 'Only draft recalls can be edited');
        }

        $productRecall->load('items.product', 'items.stockBatch');

        return view('product-recalls.edit', [
            'recall' => $productRecall,
            'suppliers' => $this->supplierOptions(),
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(),
        ]);
    }

    public function update(Request $request, ProductRecall $productRecall)
    {
        $this->authorizeProductRecallAccess($productRecall);

        if ($productRecall->status !== 'draft') {
            return redirect()->route('product-recalls.show', $productRecall)
                ->with('error', 'Only draft recalls can be updated');
        }

        $validated = $request->validate([
            'recall_date' => 'required|date|before_or_equal:today',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'grn_id' => 'nullable|exists:goods_receipt_notes,id',
            'recall_type' => 'required|in:supplier_initiated,quality_issue,expiry,other',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.stock_batch_id' => 'required|exists:stock_batches,id',
            'items.*.grn_item_id' => 'nullable|exists:goods_receipt_note_items,id',
            'items.*.quantity_recalled' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $this->authorizeRecallSupplier((int) $validated['supplier_id']);
        $this->authorizeRecallItems($validated);

        $totalQty = 0;
        $totalValue = 0;
        foreach ($validated['items'] as &$item) {
            $item['total_value'] = $item['quantity_recalled'] * $item['unit_cost'];
            $totalQty += $item['quantity_recalled'];
            $totalValue += $item['total_value'];
        }

        $validated['total_quantity_recalled'] = $totalQty;
        $validated['total_value'] = $totalValue;

        $productRecall->update($validated);
        $productRecall->items()->delete();

        foreach ($validated['items'] as $item) {
            $productRecall->items()->create($item);
        }

        return redirect()->route('product-recalls.show', $productRecall)
            ->with('success', 'Product recall updated successfully');
    }

    public function destroy(ProductRecall $productRecall)
    {
        $this->authorizeProductRecallAccess($productRecall);

        if ($productRecall->status !== 'draft') {
            return redirect()->route('product-recalls.index')
                ->with('error', 'Only draft recalls can be deleted');
        }

        $productRecall->delete();

        return redirect()->route('product-recalls.index')
            ->with('success', 'Product recall deleted successfully');
    }

    public function post(Request $request, ProductRecall $productRecall)
    {
        $this->authorizeProductRecallAccess($productRecall);

        $request->validate([
            'password' => 'required',
        ]);

        if (! Hash::check($request->password, auth()->user()->password)) {
            return redirect()->back()->with('error', 'Invalid password');
        }

        $service = app(ProductRecallService::class);
        $result = $service->postRecall($productRecall);

        if ($result['success']) {
            return redirect()->route('product-recalls.show', $productRecall)
                ->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function cancel(ProductRecall $productRecall)
    {
        $this->authorizeProductRecallAccess($productRecall);

        if ($productRecall->status !== 'draft') {
            return redirect()->route('product-recalls.show', $productRecall)
                ->with('error', 'Only draft recalls can be cancelled');
        }

        $productRecall->update(['status' => 'cancelled']);

        return redirect()->route('product-recalls.show', $productRecall)
            ->with('success', 'Product recall cancelled successfully');
    }

    public function createClaim(Request $request, ProductRecall $productRecall)
    {
        $this->authorizeProductRecallAccess($productRecall);

        if ($productRecall->status !== 'posted') {
            return redirect()->back()->with('error', 'Only posted recalls can generate claims');
        }

        if ($productRecall->claim_register_id) {
            return redirect()->back()->with('error', 'Claim already created for this recall');
        }

        try {
            $claimData = [
                'transaction_date' => now()->toDateString(),
                'supplier_id' => $productRecall->supplier_id,
                'transaction_type' => 'claim',
                'reference_number' => $productRecall->recall_number,
                'debit' => $productRecall->total_value,
                'credit' => 0,
                'description' => "Product recall - {$productRecall->recall_number}: {$productRecall->reason}",
                'payment_method' => 'bank_transfer',
            ];

            $debtorsAccount = ChartOfAccount::where('account_code', '1112')->first();
            if ($debtorsAccount) {
                $claimData['debit_account_id'] = $debtorsAccount->id;
            }

            $bankCoa = ChartOfAccount::where('account_code', '1171')->first();
            if ($bankCoa) {
                $claimData['credit_account_id'] = $bankCoa->id;
                $hblBank = BankAccount::where('chart_of_account_id', $bankCoa->id)->first();
                if ($hblBank) {
                    $claimData['bank_account_id'] = $hblBank->id;
                }
            }

            $claim = ClaimRegister::create($claimData);

            $productRecall->update(['claim_register_id' => $claim->id]);

            return redirect()->route('claim-registers.show', $claim)
                ->with('success', 'Claim created successfully from product recall');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create claim: '.$e->getMessage());
        }
    }

    public function getBatchesForSupplier(Request $request, $supplierId)
    {
        $this->authorizeRecallSupplier((int) $supplierId);

        $warehouseId = $request->input('warehouse_id');
        $filters = $request->only(['batch_code', 'expiry_from', 'expiry_to', 'mfg_date', 'product_id']);

        $service = app(ProductRecallService::class);
        $batches = $service->getAvailableBatches($supplierId, $warehouseId, $filters);

        return response()->json($batches);
    }

    private function supplierOptions()
    {
        $userSupplierId = $this->getUserSupplierScope();

        return Supplier::query()
            ->where('disabled', false)
            ->when($userSupplierId, fn ($query) => $query->where('id', $userSupplierId))
            ->when(! $this->canViewAllSuppliers() && ! $userSupplierId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get();
    }

    private function getUserSupplierScope(): ?int
    {
        $user = auth()->user();

        if ($this->canViewAllSuppliers()) {
            return null;
        }

        return $user->supplier_id ? (int) $user->supplier_id : null;
    }

    private function canViewAllSuppliers(): bool
    {
        $user = auth()->user();

        return $user->is_super_admin === 'Yes'
            || $user->hasRole('super-admin')
            || $user->hasRole('admin');
    }

    private function authorizeRecallSupplier(int $supplierId, string $message = 'You do not have permission to access this supplier.'): void
    {
        if ($this->canViewAllSuppliers()) {
            return;
        }

        $userSupplierId = $this->getUserSupplierScope();

        if (! $userSupplierId || $supplierId !== $userSupplierId) {
            abort(403, $message);
        }
    }

    private function authorizeProductRecallAccess(ProductRecall $productRecall): void
    {
        $this->authorizeRecallSupplier((int) $productRecall->supplier_id, 'You do not have permission to access this product recall.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function authorizeRecallItems(array $validated): void
    {
        $supplierId = (int) $validated['supplier_id'];
        $items = collect($validated['items']);

        $productCount = Product::query()
            ->whereIn('id', $items->pluck('product_id')->map(fn ($id) => (int) $id)->unique())
            ->where('supplier_id', $supplierId)
            ->count();

        $batchCount = StockBatch::query()
            ->whereIn('id', $items->pluck('stock_batch_id')->map(fn ($id) => (int) $id)->unique())
            ->where('supplier_id', $supplierId)
            ->count();

        if ($productCount !== $items->pluck('product_id')->unique()->count() || $batchCount !== $items->pluck('stock_batch_id')->unique()->count()) {
            abort(403, 'You do not have permission to recall products for this supplier.');
        }
    }
}

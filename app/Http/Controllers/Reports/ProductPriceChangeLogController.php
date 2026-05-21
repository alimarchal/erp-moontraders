<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductPriceChangeLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-product-price-change-log'),
        ];
    }

    public function index(Request $request)
    {
        $canViewAllSuppliers = $this->canViewAllSuppliers();
        $userSupplierId = $this->getUserSupplierScope();
        $requestedSupplierId = $request->input('supplier_id');

        if ($requestedSupplierId && ! $canViewAllSuppliers && (int) $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        $supplierId = $userSupplierId ?? ($requestedSupplierId ? (int) $requestedSupplierId : null);
        $this->authorizeProductFilter($request->input('product_id'), $supplierId, $canViewAllSuppliers);

        $query = ProductPriceChangeLog::query()
            ->with(['product.supplier', 'changedBy'])
            ->orderBy('changed_at', 'desc');

        if ($supplierId) {
            $query->whereHas('product', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            });
        } elseif (! $canViewAllSuppliers) {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('price_type')) {
            $query->where('price_type', $request->input('price_type'));
        }

        if ($request->filled('changed_by')) {
            $query->where('changed_by', $request->input('changed_by'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('changed_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('changed_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('reports.product-price-change-log.index', [
            'logs' => $logs,
            'supplierOptions' => Supplier::query()
                ->when($supplierId, fn ($query) => $query->where('id', $supplierId))
                ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('supplier_name')
                ->get(['id', 'supplier_name']),
            'productOptions' => Product::query()
                ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
                ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('product_name')
                ->get(['id', 'product_code', 'product_name']),
            'priceTypeOptions' => [
                'selling_price' => 'Selling Price',
                'expiry_price' => 'Expiry Price',
                'cost_price' => 'Cost Price',
            ],
            'userOptions' => User::orderBy('name')->get(['id', 'name']),
            'supplierId' => $supplierId,
            'canViewAllSuppliers' => $canViewAllSuppliers,
        ]);
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

    private function authorizeProductFilter(mixed $productId, ?int $supplierId, bool $canViewAllSuppliers): void
    {
        if (! $productId || $canViewAllSuppliers) {
            return;
        }

        if (! $supplierId) {
            abort(403, 'You do not have permission to filter by this product.');
        }

        $isAllowedProduct = Product::query()
            ->where('id', $productId)
            ->where('supplier_id', $supplierId)
            ->exists();

        if (! $isAllowedProduct) {
            abort(403, 'You do not have permission to filter by this product.');
        }
    }
}

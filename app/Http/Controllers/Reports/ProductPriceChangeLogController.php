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
        $query = ProductPriceChangeLog::query()
            ->with(['product.supplier', 'changedBy'])
            ->orderBy('changed_at', 'desc');

        if ($request->filled('supplier_id')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('supplier_id', $request->input('supplier_id'));
            });
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
            'supplierOptions' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'productOptions' => Product::orderBy('product_name')->get(['id', 'product_code', 'product_name']),
            'priceTypeOptions' => [
                'selling_price' => 'Selling Price',
                'expiry_price' => 'Expiry Price',
                'cost_price' => 'Cost Price',
            ],
            'userOptions' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}

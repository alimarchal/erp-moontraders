<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRevenueCategoryRequest;
use App\Http\Requests\UpdateRevenueCategoryRequest;
use App\Models\RevenueCategory;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryRevenueController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:category-revenue-list', only: ['index', 'show']),
            new Middleware('can:category-revenue-create', only: ['create', 'store']),
            new Middleware('can:category-revenue-edit', only: ['edit', 'update']),
            new Middleware('can:category-revenue-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $supplierId = $request->input('supplier_id');

        $categories = RevenueCategory::query()
            ->with('supplier')
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%'.$request->input('name').'%'))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('settings.category-revenue.index', array_merge($this->formData(), [
            'categories' => $categories,
            'supplierId' => $supplierId,
        ]));
    }

    public function create()
    {
        return view('settings.category-revenue.create', $this->formData());
    }

    public function store(StoreRevenueCategoryRequest $request)
    {
        RevenueCategory::create($request->validated());

        return redirect()->route('category-revenue.index')
            ->with('success', 'Revenue category created successfully.');
    }

    public function show(RevenueCategory $categoryRevenue)
    {
        return redirect()->route('category-revenue.edit', $categoryRevenue);
    }

    public function edit(RevenueCategory $categoryRevenue)
    {
        return view('settings.category-revenue.edit', array_merge($this->formData(), [
            'category' => $categoryRevenue,
        ]));
    }

    public function update(UpdateRevenueCategoryRequest $request, RevenueCategory $categoryRevenue)
    {
        $categoryRevenue->update($request->validated());

        return redirect()->route('category-revenue.index')
            ->with('success', 'Revenue category updated successfully.');
    }

    public function destroy(RevenueCategory $categoryRevenue)
    {
        if ($categoryRevenue->revenueDetails()->exists()) {
            return back()->with('error', 'Cannot delete revenue category because it has associated revenue entries.');
        }

        $categoryRevenue->delete();

        return redirect()->route('category-revenue.index')
            ->with('success', 'Revenue category deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
        ];
    }
}

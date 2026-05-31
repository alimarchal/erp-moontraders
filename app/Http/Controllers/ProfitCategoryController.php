<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfitCategoryRequest;
use App\Http\Requests\UpdateProfitCategoryRequest;
use App\Models\ProfitCategory;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProfitCategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:profit-category-list', only: ['index', 'show']),
            new Middleware('can:profit-category-create', only: ['create', 'store']),
            new Middleware('can:profit-category-edit', only: ['edit', 'update']),
            new Middleware('can:profit-category-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $supplierId = $request->input('supplier_id');

        $categories = ProfitCategory::query()
            ->with('supplier')
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%'.$request->input('name').'%'))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('settings.profit-categories.index', array_merge($this->formData(), [
            'categories' => $categories,
            'supplierId' => $supplierId,
        ]));
    }

    public function create()
    {
        return view('settings.profit-categories.create', $this->formData());
    }

    public function store(StoreProfitCategoryRequest $request)
    {
        ProfitCategory::create($request->validated());

        return redirect()->route('profit-categories.index')
            ->with('success', 'Profit category created successfully.');
    }

    public function show(ProfitCategory $profitCategory)
    {
        return redirect()->route('profit-categories.edit', $profitCategory);
    }

    public function edit(ProfitCategory $profitCategory)
    {
        return view('settings.profit-categories.edit', array_merge($this->formData(), [
            'category' => $profitCategory,
        ]));
    }

    public function update(UpdateProfitCategoryRequest $request, ProfitCategory $profitCategory)
    {
        $profitCategory->update($request->validated());

        return redirect()->route('profit-categories.index')
            ->with('success', 'Profit category updated successfully.');
    }

    public function destroy(ProfitCategory $profitCategory)
    {
        if ($profitCategory->profitCategoryDetails()->exists()) {
            return back()->with('error', 'Cannot delete profit category because it has associated entries.');
        }

        $profitCategory->delete();

        return redirect()->route('profit-categories.index')
            ->with('success', 'Profit category deleted successfully.');
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

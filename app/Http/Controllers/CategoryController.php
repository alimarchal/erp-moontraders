<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:category-list', only: ['index', 'show']),
            new Middleware('permission:category-create', only: ['create', 'store']),
            new Middleware('permission:category-edit', only: ['edit', 'update']),
            new Middleware('permission:category-delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $categories = QueryBuilder::for(Category::query()->withCount('products'))
            ->allowedFilters($this->allowedFilters())
            ->allowedSorts(['name'])
            ->defaultSort('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('settings.categories.index', [
            'categories' => $categories,
            'stats' => $this->listStats($request),
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('settings.categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        Category::create($request->validated());

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        $category->loadCount('products');

        return view('settings.categories.show', compact('category'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        return view('settings.categories.edit', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * "Delete" only deactivates the category; records are never removed.
     */
    public function destroy(Category $category)
    {
        $category->update(['is_active' => false]);

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' deactivated. Its products are kept.");
    }

    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * @return array<int, AllowedFilter>
     */
    private function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('search', fn ($query, $value) => $query->where(function ($q) use ($value) {
                $term = '%'.trim((string) $value).'%';
                $q->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term);
            })),
            AllowedFilter::partial('name'),
            AllowedFilter::exact('is_active'),
        ];
    }

    /**
     * Headline numbers for the list; counts follow the search but not the status tab.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    private function listStats(Request $request): array
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($v) => $v !== null && $v !== '');
        unset($filters['is_active']);

        $byStatus = QueryBuilder::for(Category::class, new Request(['filter' => $filters]))
            ->allowedFilters($this->allowedFilters())
            ->getEloquentBuilder()
            ->reorder()
            ->selectRaw('is_active, COUNT(*) as aggregate')
            ->groupBy('is_active')
            ->pluck('aggregate', 'is_active');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[1] ?? 0),
            'inactive' => (int) ($byStatus[0] ?? 0),
        ];
    }
}

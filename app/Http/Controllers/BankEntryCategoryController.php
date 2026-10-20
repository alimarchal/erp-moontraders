<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankEntryCategoryRequest;
use App\Models\BankEntryCategory;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BankEntryCategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-entry-category-list', only: ['index']),
            new Middleware('can:bank-entry-category-create', only: ['create', 'store']),
            new Middleware('can:bank-entry-category-edit', only: ['edit', 'update']),
            new Middleware('can:bank-entry-category-delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $categories = QueryBuilder::for(BankEntryCategory::query()->with('defaultAccount')->withCount('entries'))
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(40)
            ->withQueryString();

        return view('bank-entry-categories.index', ['categories' => $categories]);
    }

    public function create(): View
    {
        return view('bank-entry-categories.create', ['accountOptions' => $this->accountOptions()]);
    }

    public function store(BankEntryCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $category = BankEntryCategory::create($data + [
            'slug' => str($data['name'])->slug()->value(),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $data['sort_order'] ?? 100,
        ]);

        return redirect()->route('bank-entry-categories.index')->with('success', "Category '{$category->name}' created.");
    }

    public function edit(BankEntryCategory $bankEntryCategory): View
    {
        return view('bank-entry-categories.edit', [
            'category' => $bankEntryCategory,
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    public function update(BankEntryCategoryRequest $request, BankEntryCategory $bankEntryCategory): RedirectResponse
    {
        $bankEntryCategory->update($request->validated() + [
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $request->integer('sort_order', $bankEntryCategory->sort_order),
        ]);

        return redirect()->route('bank-entry-categories.index')->with('success', "Category '{$bankEntryCategory->name}' updated.");
    }

    public function destroy(BankEntryCategory $bankEntryCategory): RedirectResponse
    {
        if ($bankEntryCategory->entries()->exists()) {
            return back()->with('error', "'{$bankEntryCategory->name}' is used by bank entries. Make it inactive instead of deleting it.");
        }

        $bankEntryCategory->delete();

        return redirect()->route('bank-entry-categories.index')->with('success', 'Category deleted.');
    }

    private function accountOptions()
    {
        return ChartOfAccount::orderBy('account_code')->get(['id', 'account_code', 'account_name']);
    }
}

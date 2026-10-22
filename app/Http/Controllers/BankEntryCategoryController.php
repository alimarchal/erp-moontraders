<?php

namespace App\Http\Controllers;

use App\Exports\BankReportExport;
use App\Http\Requests\BankEntryCategoryRequest;
use App\Models\BankEntryCategory;
use App\Models\ChartOfAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BankEntryCategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-entry-category-list', only: ['index', 'export']),
            new Middleware('can:bank-entry-category-create', only: ['create', 'store']),
            new Middleware('can:bank-entry-category-edit', only: ['edit', 'update']),
            new Middleware('can:bank-entry-category-delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        return view('bank-entry-categories.index', ['categories' => $this->query()->paginate(40)->withQueryString()]);
    }

    /**
     * Download the filtered category list as Excel or PDF.
     */
    public function export(string $format)
    {
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);

        $rows = $this->query()->get()->map(fn (BankEntryCategory $category) => [
            $category->name,
            BankEntryCategory::TYPES[$category->type] ?? $category->type,
            $category->defaultAccount ? $category->defaultAccount->account_code.' - '.$category->defaultAccount->account_name : 'Company receivable / payable',
            $category->entries_count,
            $category->is_active ? 'Active' : 'Inactive',
        ])->all();

        $data = [
            'title' => 'Bank Entry Categories',
            'subtitle' => 'Moon Traders - printed by '.auth()->user()->name,
            'headings' => ['Category', 'Used for', 'Default ledger account', 'Entries', 'Status'],
            'numeric' => [3],
            'rows' => $rows,
            'totals' => null,
            'notes' => [],
        ];

        if ($format === 'pdf') {
            return Pdf::loadView('reports.bank.pdf', ['data' => $data, 'generatedAt' => now()])->setPaper('a4', 'portrait')->download('bank-entry-categories.pdf');
        }

        return Excel::download(new BankReportExport($data), 'bank-entry-categories.xlsx');
    }

    private function query(): QueryBuilder
    {
        return QueryBuilder::for(BankEntryCategory::query()->with('defaultAccount')->withCount('entries'))
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name');
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

    /**
     * Categories are never deleted: past and future entries may refer to them, so the row is made inactive.
     */
    public function destroy(BankEntryCategory $bankEntryCategory): RedirectResponse
    {
        $bankEntryCategory->update(['is_active' => false]);

        return redirect()->route('bank-entry-categories.index')->with('success', "Category '{$bankEntryCategory->name}' deactivated.");
    }

    private function accountOptions()
    {
        return ChartOfAccount::orderBy('account_code')->get(['id', 'account_code', 'account_name']);
    }
}

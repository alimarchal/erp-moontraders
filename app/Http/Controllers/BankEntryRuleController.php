<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankEntryRuleRequest;
use App\Models\BankEntryCategory;
use App\Models\BankEntryRule;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BankEntryRuleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-entry-rule-list', only: ['index']),
            new Middleware('can:bank-entry-rule-create', only: ['create', 'store']),
            new Middleware('can:bank-entry-rule-edit', only: ['edit', 'update']),
            new Middleware('can:bank-entry-rule-delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $rules = QueryBuilder::for(BankEntryRule::query()->with(['company', 'category']))
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::partial('match_value'),
                AllowedFilter::exact('bank_entry_category_id'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('priority')
            ->orderBy('id')
            ->paginate(40)
            ->withQueryString();

        return view('bank-entry-rules.index', ['rules' => $rules, 'categories' => $this->categories()]);
    }

    public function create(): View
    {
        return view('bank-entry-rules.create', ['categories' => $this->categories(), 'companies' => $this->companies()]);
    }

    public function store(BankEntryRuleRequest $request): RedirectResponse
    {
        $rule = BankEntryRule::create($request->validated() + [
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('bank-entry-rules.index')->with('success', "Rule '{$rule->name}' saved. Use 'Apply to unclassified' on the entries page to run it.");
    }

    public function edit(BankEntryRule $bankEntryRule): View
    {
        return view('bank-entry-rules.edit', ['rule' => $bankEntryRule, 'categories' => $this->categories(), 'companies' => $this->companies()]);
    }

    public function update(BankEntryRuleRequest $request, BankEntryRule $bankEntryRule): RedirectResponse
    {
        $bankEntryRule->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('bank-entry-rules.index')->with('success', "Rule '{$bankEntryRule->name}' updated.");
    }

    public function destroy(BankEntryRule $bankEntryRule): RedirectResponse
    {
        $bankEntryRule->delete();

        return redirect()->route('bank-entry-rules.index')->with('success', 'Rule deleted.');
    }

    private function categories()
    {
        return BankEntryCategory::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    private function companies()
    {
        return Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']);
    }
}

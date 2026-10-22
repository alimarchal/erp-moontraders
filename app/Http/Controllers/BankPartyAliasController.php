<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankPartyAliasRequest;
use App\Models\BankPartyAlias;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BankPartyAliasController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-party-alias-list', only: ['index']),
            new Middleware('can:bank-party-alias-create', only: ['create', 'store']),
            new Middleware('can:bank-party-alias-edit', only: ['edit', 'update']),
            new Middleware('can:bank-party-alias-delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $aliases = QueryBuilder::for(BankPartyAlias::query()->with('company'))
            ->allowedFilters([
                AllowedFilter::partial('alias_name'),
                AllowedFilter::partial('canonical_name'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('canonical_name')
            ->orderBy('alias_name')
            ->paginate(40)
            ->withQueryString();

        return view('bank-party-aliases.index', ['aliases' => $aliases, 'companies' => $this->companies()]);
    }

    public function create(): View
    {
        return view('bank-party-aliases.create', ['companies' => $this->companies()]);
    }

    public function store(BankPartyAliasRequest $request): RedirectResponse
    {
        $alias = BankPartyAlias::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('bank-party-aliases.index')->with('success', "Alias '{$alias->alias_name}' saved.");
    }

    public function edit(BankPartyAlias $bankPartyAlias): View
    {
        return view('bank-party-aliases.edit', ['alias' => $bankPartyAlias, 'companies' => $this->companies()]);
    }

    public function update(BankPartyAliasRequest $request, BankPartyAlias $bankPartyAlias): RedirectResponse
    {
        $bankPartyAlias->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('bank-party-aliases.index')->with('success', "Alias '{$bankPartyAlias->alias_name}' updated.");
    }

    public function destroy(BankPartyAlias $bankPartyAlias): RedirectResponse
    {
        $bankPartyAlias->delete();

        return redirect()->route('bank-party-aliases.index')->with('success', 'Alias deleted.');
    }

    private function companies()
    {
        return Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']);
    }
}

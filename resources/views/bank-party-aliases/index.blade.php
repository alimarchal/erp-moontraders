<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Party Aliases" :crumbs="['Party aliases' => null]">
            @can('bank-party-alias-create')
                <a href="{{ route('bank-party-aliases.create') }}" class="ak-btn ak-btn-primary" title="Add Alias" aria-label="Add Alias"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg></a>
            @endcan
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16" style="display:flex;flex-direction:column;gap:20px;padding-top:16px">
        <x-bank.subnav inside />
        <x-bank.print-title title="Party Aliases" />
        <x-status-message />

    <x-bank.filters :action="route('bank-party-aliases.index')">
                    <div class="ak-field">
                <label for="filter_alias_name">Statement name</label>
                <input id="filter_alias_name" name="filter[alias_name]" type="text" dir="auto" value="{{ request('filter.alias_name') }}">
            </div>
            <div class="ak-field">
                <label for="filter_canonical_name">Correct name</label>
                <input id="filter_canonical_name" name="filter[canonical_name]" type="text" dir="auto" value="{{ request('filter.canonical_name') }}">
            </div>
            <div class="ak-field">
                <label for="filter_company_id">Company</label>
                <select data-placeholder="All" id="filter_company_id" name="filter[company_id]" class="select2 bank-select">
                    <option value="">All</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) request('filter.company_id') === (string) $company->id)>{{ $company->supplier_name }}</option>
                    @endforeach
                </select>
            </div>
</x-bank.filters>

    <x-bank.table :items="$aliases" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Name on statement', 'align' => 'text-left'],
        ['label' => 'Correct name', 'align' => 'text-left'],
        ['label' => 'Company', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No aliases yet. Add one for every spelling of the same party." :emptyRoute="route('bank-party-aliases.create')" emptyLinkText="Add an alias">
        @foreach ($aliases as $index => $alias)
            <tr>
                <td class="ak-c">{{ $aliases->firstItem() + $index }}</td>
                <td dir="auto">{{ $alias->alias_name }}</td>
                <td dir="auto">{{ $alias->canonical_name }}</td>
                <td>{{ $alias->company?->supplier_name ?? '—' }}</td>
                <td class="ak-c">
                    <span class="ak-status {{ $alias->is_active ? 'ak-status-green' : 'ak-status-red' }}"><i aria-hidden="true"></i>{{ $alias->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="ak-c">
                    <div class="bk-row" style="justify-content:center">
                        @can('bank-party-alias-edit')
                            <a href="{{ route('bank-party-aliases.edit', $alias) }}" class="ak-btn ak-btn-outline ak-btn-sm">Edit</a>
                        @endcan
                        @can('bank-party-alias-delete')
                            <form method="POST" action="{{ route('bank-party-aliases.destroy', $alias) }}" onsubmit="return confirm('Delete this alias?')">
                                @csrf @method('DELETE')
                                <button class="ak-btn ak-btn-danger-outline ak-btn-sm">Delete</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-bank.table>
    </div>
</x-app-layout>

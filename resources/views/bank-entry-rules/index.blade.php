<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Auto-classify Rules" :crumbs="['Auto-classify rules' => null]">
            @can('bank-entry-rule-create')
                <a href="{{ route('bank-entry-rules.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add Rule</a>
            @endcan
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />
    <div class="ak-page">
        <x-bank.subnav inside />
        <x-bank.print-title title="Auto-classify Rules" />
        <x-status-message />

    <x-bank.filters :action="route('bank-entry-rules.index')">
                    <div class="ak-field">
                <label for="filter_name">Rule name</label>
                <input id="filter_name" name="filter[name]" type="text" value="{{ request('filter.name') }}">
            </div>
            <div class="ak-field">
                <label for="filter_match_value">Text</label>
                <input id="filter_match_value" name="filter[match_value]" type="text" dir="auto" value="{{ request('filter.match_value') }}">
            </div>
            <div class="ak-field">
                <label for="filter_bank_entry_category_id">Category</label>
                <select data-placeholder="All" id="filter_bank_entry_category_id" name="filter[bank_entry_category_id]" class="select2 bank-select">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('filter.bank_entry_category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
</x-bank.filters>

    <x-bank.table :items="$rules" :headers="[
        ['label' => 'Priority', 'align' => 'text-center'],
        ['label' => 'Rule', 'align' => 'text-left'],
        ['label' => 'When', 'align' => 'text-left'],
        ['label' => 'Then', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No rules yet." :emptyRoute="route('bank-entry-rules.create')" emptyLinkText="Add a rule">
        @foreach ($rules as $index => $rule)
            <tr>
                <td class="py-2 px-2 text-center tabular-nums">{{ $rule->priority }}</td>
                <td>{{ $rule->name }}</td>
                <td>
                    {{ \App\Models\BankEntryRule::FIELDS[$rule->match_field] }}
                    {{ strtolower(\App\Models\BankEntryRule::MATCH_TYPES[$rule->match_type]) }}
                    <span class="font-semibold" dir="auto">“{{ $rule->match_value }}”</span>
                    <span class="text-xs text-gray-500">({{ strtolower(\App\Models\BankEntryRule::DIRECTIONS[$rule->direction]) }})</span>
                </td>
                <td>
                    {{ $rule->category?->name }}@if ($rule->company) <span class="text-gray-500">· {{ $rule->company->supplier_name }}</span>@endif
                </td>
                <td class="ak-c">
                    <span class="ak-status {{ $rule->is_active ? 'ak-status-green' : 'ak-status-red' }}"><i aria-hidden="true"></i>{{ $rule->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="ak-c">
                    <div class="bk-row" style="justify-content:center">
                        @can('bank-entry-rule-edit')
                            <a href="{{ route('bank-entry-rules.edit', $rule) }}" class="ak-btn ak-btn-outline ak-btn-sm">Edit</a>
                        @endcan
                        @can('bank-entry-rule-delete')
                            <form method="POST" action="{{ route('bank-entry-rules.destroy', $rule) }}" onsubmit="return confirm('Delete this rule?')">
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

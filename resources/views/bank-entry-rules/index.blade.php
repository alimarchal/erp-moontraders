<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Auto-classify Rules" :createRoute="route('bank-entry-rules.create')" createLabel="Add Rule"
            createPermission="bank-entry-rule-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-bank.print-styles />
    <x-bank.subnav />
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4"><x-bank.print-title title="Auto-classify Rules" /></div>

    <x-filter-section :action="route('bank-entry-rules.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_name" value="Rule name" />
                <x-input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full" :value="request('filter.name')" />
            </div>
            <div>
                <x-label for="filter_match_value" value="Text" />
                <x-input id="filter_match_value" name="filter[match_value]" type="text" dir="auto" class="mt-1 block w-full" :value="request('filter.match_value')" />
            </div>
            <div>
                <x-label for="filter_bank_entry_category_id" value="Category" />
                <select id="filter_bank_entry_category_id" name="filter[bank_entry_category_id]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('filter.bank_entry_category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$rules" :headers="[
        ['label' => 'Priority', 'align' => 'text-center'],
        ['label' => 'Rule', 'align' => 'text-left'],
        ['label' => 'When', 'align' => 'text-left'],
        ['label' => 'Then', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No rules yet." :emptyRoute="route('bank-entry-rules.create')" emptyLinkText="Add a rule">
        @foreach ($rules as $index => $rule)
            <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="py-2 px-2 text-center tabular-nums">{{ $rule->priority }}</td>
                <td class="py-2 px-2">{{ $rule->name }}</td>
                <td class="py-2 px-2 font-normal">
                    {{ \App\Models\BankEntryRule::FIELDS[$rule->match_field] }}
                    {{ strtolower(\App\Models\BankEntryRule::MATCH_TYPES[$rule->match_type]) }}
                    <span class="font-semibold" dir="auto">“{{ $rule->match_value }}”</span>
                    <span class="text-xs text-gray-500">({{ strtolower(\App\Models\BankEntryRule::DIRECTIONS[$rule->direction]) }})</span>
                </td>
                <td class="py-2 px-2 font-normal">
                    {{ $rule->category?->name }}@if ($rule->company) <span class="text-gray-500">· {{ $rule->company->supplier_name }}</span>@endif
                </td>
                <td class="py-2 px-2 text-center">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $rule->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="py-2 px-2 text-center">
                    <div class="flex justify-center gap-3 font-normal">
                        @can('bank-entry-rule-edit')
                            <a href="{{ route('bank-entry-rules.edit', $rule) }}" class="text-green-700 hover:underline">Edit</a>
                        @endcan
                        @can('bank-entry-rule-delete')
                            <form method="POST" action="{{ route('bank-entry-rules.destroy', $rule) }}" onsubmit="return confirm('Delete this rule?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>

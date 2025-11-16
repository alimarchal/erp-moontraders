<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tax Codes" :createRoute="route('tax-codes.create')"
            createLabel="Add Tax Code" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('tax-codes.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_tax_code" value="Tax Code" />
                <x-input id="filter_tax_code" name="filter[tax_code]" type="text" class="mt-1 block w-full"
                    :value="request('filter.tax_code')" placeholder="GST-18" />
            </div>

            <div>
                <x-label for="filter_name" value="Tax Name" />
                <x-input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.name')" placeholder="GST @ 18%" />
            </div>

            <div>
                <x-label for="filter_tax_type" value="Tax Type" />
                <select id="filter_tax_type" name="filter[tax_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach ($taxTypeOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.tax_type')===$value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_calculation_method" value="Calculation Method" />
                <select id="filter_calculation_method" name="filter[calculation_method]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Methods</option>
                    @foreach ($calculationMethodOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.calculation_method')===$value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    <option value="1" {{ request('filter.is_active')==='1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('filter.is_active')==='0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div>
                <x-label for="filter_is_compound" value="Compound Tax" />
                <select id="filter_is_compound" name="filter[is_compound]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_compound')==='1' ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ request('filter.is_compound')==='0' ? 'selected' : '' }}>No</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$taxCodes" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Tax Code'],
        ['label' => 'Name'],
        ['label' => 'Tax Type', 'align' => 'text-center'],
        ['label' => 'Method', 'align' => 'text-center'],
        ['label' => 'Compound', 'align' => 'text-center'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No tax codes found." :emptyRoute="route('tax-codes.create')"
        emptyLinkText="Add a tax code">
        @foreach ($taxCodes as $index => $taxCode)
        @php
        $taxTypeLabel = $taxTypeOptions[$taxCode->tax_type] ?? ucfirst(str_replace('_', ' ', $taxCode->tax_type));
        $calculationMethodLabel = $calculationMethodOptions[$taxCode->calculation_method] ?? ucfirst(str_replace('_', ' ', $taxCode->calculation_method));
        @endphp
        <tr class="border-b border-gray-200 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $taxCodes->firstItem() + $index }}
            </td>
            <td class="py-1 px-2 font-semibold">
                {{ $taxCode->tax_code }}
            </td>
            <td class="py-1 px-2">
                {{ $taxCode->name }}
            </td>
            <td class="py-1 px-2 text-center">
                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                        @class([
                            'bg-blue-100 text-blue-700' => $taxCode->tax_type === 'gst',
                            'bg-green-100 text-green-700' => $taxCode->tax_type === 'vat',
                            'bg-purple-100 text-purple-700' => $taxCode->tax_type === 'sales_tax',
                            'bg-orange-100 text-orange-700' => $taxCode->tax_type === 'withholding_tax',
                            'bg-red-100 text-red-700' => $taxCode->tax_type === 'excise',
                            'bg-yellow-100 text-yellow-700' => $taxCode->tax_type === 'customs_duty',
                            'bg-gray-100 text-gray-600' => !in_array($taxCode->tax_type, ['gst', 'vat', 'sales_tax', 'withholding_tax', 'excise', 'customs_duty']),
                        ])">
                    {{ $taxTypeLabel }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                        @class([
                            'bg-indigo-100 text-indigo-700' => $taxCode->calculation_method === 'percentage',
                            'bg-cyan-100 text-cyan-700' => $taxCode->calculation_method === 'fixed_amount',
                            'bg-gray-100 text-gray-600' => !in_array($taxCode->calculation_method, ['percentage', 'fixed_amount']),
                        ])">
                    {{ $calculationMethodLabel }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $taxCode->is_compound ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $taxCode->is_compound ? 'Yes' : 'No' }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $taxCode->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $taxCode->is_active ? 'Active' : 'Inactive' }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('tax-codes.show', $taxCode) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                        title="View">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </a>
                    <a href="{{ route('tax-codes.edit', $taxCode) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                        title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </a>
                    <form method="POST" action="{{ route('tax-codes.destroy', $taxCode) }}"
                        onsubmit="return confirm('Are you sure you want to delete this tax code?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                            title="Delete">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        @endforeach
    </x-data-table>
</x-app-layout>

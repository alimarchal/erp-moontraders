<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Companies" :createRoute="route('companies.create')" createLabel="Add Company"
            :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('companies.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_company_name" value="Company Name" />
                <x-input id="filter_company_name" name="filter[company_name]" type="text"
                    class="mt-1 block w-full" :value="request('filter.company_name')" placeholder="Moon Traders" />
            </div>

            <div>
                <x-label for="filter_abbr" value="Abbreviation" />
                <x-input id="filter_abbr" name="filter[abbr]" type="text"
                    class="mt-1 block w-full" :value="request('filter.abbr')" placeholder="MT" />
            </div>

            <div>
                <x-label for="filter_country" value="Country" />
                <x-input id="filter_country" name="filter[country]" type="text"
                    class="mt-1 block w-full" :value="request('filter.country')" placeholder="Pakistan" />
            </div>

            <div>
                <x-label for="filter_is_group" value="Company Type" />
                <select id="filter_is_group" name="filter[is_group]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_group') === '1' ? 'selected' : '' }}>Group</option>
                    <option value="0" {{ request('filter.is_group') === '0' ? 'selected' : '' }}>Standalone</option>
                </select>
            </div>

            <div>
                <x-label for="filter_default_currency_id" value="Default Currency" />
                <select id="filter_default_currency_id" name="filter[default_currency_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All currencies</option>
                    @foreach ($currencyOptions as $currency)
                        <option value="{{ $currency->id }}" {{ (string) request('filter.default_currency_id') === (string) $currency->id ? 'selected' : '' }}>
                            {{ $currency->currency_code }} · {{ $currency->currency_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_date_of_establishment_from" value="Establishment From" />
                <x-input id="filter_date_of_establishment_from" name="filter[date_of_establishment_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.date_of_establishment_from')" />
            </div>

            <div>
                <x-label for="filter_date_of_establishment_to" value="Establishment To" />
                <x-input id="filter_date_of_establishment_to" name="filter[date_of_establishment_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.date_of_establishment_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$companies" :headers="[
            ['label' => '#', 'align' => 'text-center'],
            ['label' => 'Company'],
            ['label' => 'Default Currency', 'align' => 'text-center'],
            ['label' => 'Parent Company'],
            ['label' => 'Contact'],
            ['label' => 'Group', 'align' => 'text-center'],
            ['label' => 'Created At', 'align' => 'text-center'],
            ['label' => 'Actions', 'align' => 'text-center'],
        ]" emptyMessage="No companies found." :emptyRoute="route('companies.create')" emptyLinkText="Add a company">

        @foreach ($companies as $index => $company)
            <tr class="border-b border-gray-200 dark:border-gray-700 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $companies->firstItem() + $index }}
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ $company->company_name }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {!! $company->abbr ? '<strong>Abbr:</strong> ' . e($company->abbr) . ' · ' : '' !!}
                        {!! $company->country ? '<strong>Country:</strong> ' . e($company->country) : '' !!}
                    </div>
                </td>
                <td class="py-1 px-2 text-center">
                    {{ $company->defaultCurrency?->currency_code ?? '—' }}
                </td>
                <td class="py-1 px-2">
                    {{ $company->parentCompany?->company_name ?? '—' }}
                </td>
                <td class="py-1 px-2">
                    @if ($company->phone_no)
                        <div class="text-xs"><strong>Phone:</strong> {{ $company->phone_no }}</div>
                    @endif
                    @if ($company->email)
                        <div class="text-xs"><strong>Email:</strong> {{ $company->email }}</div>
                    @endif
                </td>
                <td class="py-1 px-2 text-center">
                    <span
                        class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $company->is_group ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700' }}">
                        {{ $company->is_group ? 'Group' : 'Standalone' }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    {{ optional($company->created_at)->format('d-m-Y') ?? '—' }}
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('companies.show', $company->id) }}"
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
                        <a href="{{ route('companies.edit', $company->id) }}"
                            class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                            title="Edit">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('companies.destroy', $company->id) }}"
                            onsubmit="return confirm('Delete this company?');">
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

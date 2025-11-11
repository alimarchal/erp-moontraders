<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Customers" :createRoute="route('customers.create')" createLabel="Add Customer"
            :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('customers.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_customer_name" value="Customer Name" />
                <x-input id="filter_customer_name" name="filter[customer_name]" type="text"
                    class="mt-1 block w-full" :value="request('filter.customer_name')" placeholder="Search by name" />
            </div>

            <div>
                <x-label for="filter_customer_code" value="Customer Code" />
                <x-input id="filter_customer_code" name="filter[customer_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.customer_code')" placeholder="CUST-001" />
            </div>

            <div>
                <x-label for="filter_city" value="City" />
                <x-input id="filter_city" name="filter[city]" type="text"
                    class="mt-1 block w-full" :value="request('filter.city')" placeholder="Muzaffarabad" />
            </div>

            <div>
                <x-label for="filter_sub_locality" value="Area / Sub Locality" />
                <x-input id="filter_sub_locality" name="filter[sub_locality]" type="text"
                    class="mt-1 block w-full" :value="request('filter.sub_locality')" placeholder="Satellite Town" />
            </div>

            <div>
                <x-label for="filter_channel_type" value="Channel Type" />
                <select id="filter_channel_type" name="filter[channel_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Channels</option>
                    @foreach ($channelTypes as $type)
                    <option value="{{ $type }}" {{ request('filter.channel_type') === $type ? 'selected' : '' }}>
                        {{ $type }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_customer_category" value="Customer Category" />
                <select id="filter_customer_category" name="filter[customer_category]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Categories</option>
                    @foreach ($customerCategories as $category)
                    <option value="{{ $category }}" {{ request('filter.customer_category') === $category ? 'selected' : '' }}>
                        Category {{ $category }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_sales_rep" value="Sales Rep" />
                <select id="filter_sales_rep" name="filter[sales_rep_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Reps</option>
                    @foreach ($salesRepOptions as $salesRep)
                    <option value="{{ $salesRep->id }}" {{ request('filter.sales_rep_id') === (string) $salesRep->id ? 'selected' : '' }}>
                        {{ $salesRep->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_phone" value="Phone" />
                <x-input id="filter_phone" name="filter[phone]" type="text" class="mt-1 block w-full"
                    :value="request('filter.phone')" placeholder="03XX-XXXXXXX" />
            </div>

            <div>
                <x-label for="filter_email" value="Email" />
                <x-input id="filter_email" name="filter[email]" type="text" class="mt-1 block w-full"
                    :value="request('filter.email')" placeholder="store@example.com" />
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.is_active') === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$customers" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Code', 'align' => 'text-left'],
        ['label' => 'Customer', 'align' => 'text-left'],
        ['label' => 'Contact', 'align' => 'text-left'],
        ['label' => 'Channel & Location', 'align' => 'text-left'],
        ['label' => 'Credit Snapshot', 'align' => 'text-left'],
        ['label' => 'Sales Rep', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No customers found." :emptyRoute="route('customers.create')" emptyLinkText="Add a customer">
        @foreach ($customers as $index => $customer)
        @php
        $creditLimit = (float) ($customer->credit_limit ?? 0);
        $creditUsed = (float) ($customer->credit_used ?? 0);
        $creditAvailable = $customer->getAvailableCredit();
        $creditPercent = $creditLimit > 0 ? min(100, round(($creditUsed / $creditLimit) * 100, 1)) : 0;
        @endphp
        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
            <td class="py-2 px-2 text-center">
                {{ $customers->firstItem() + $index }}
            </td>
            <td class="py-2 px-2 font-semibold text-gray-900">
                <div>{{ $customer->customer_code }}</div>
                <div class="text-xs text-gray-500">ID: {{ $customer->id }}</div>
            </td>
            <td class="py-2 px-2">
                <div class="font-semibold">{{ $customer->customer_name }}</div>
                <div class="text-xs text-gray-500">{{ $customer->business_name ?? '—' }}</div>
            </td>
            <td class="py-2 px-2 text-sm">
                <div>{{ $customer->phone ?? '—' }}</div>
                <div class="text-xs text-gray-500">{{ $customer->email ?? '—' }}</div>
            </td>
            <td class="py-2 px-2 text-sm">
                <div>{{ $customer->channel_type }} · Category {{ $customer->customer_category }}</div>
                <div class="text-xs text-gray-500">
                    {{ $customer->sub_locality ? $customer->sub_locality . ',' : '' }}
                    {{ $customer->city ?? '—' }}
                </div>
            </td>
            <td class="py-2 px-2 text-sm">
                <div class="flex items-center gap-2">
                    <span class="text-gray-900 font-semibold">
                        {{ number_format($creditUsed, 0) }} / {{ number_format($creditLimit, 0) }}
                    </span>
                    <span class="text-xs text-gray-500">{{ $creditPercent }}%</span>
                </div>
                <div class="text-xs text-emerald-600 font-semibold">
                    Available: {{ number_format($creditAvailable, 0) }}
                </div>
                <div class="text-xs text-slate-500">
                    AR: {{ number_format((float) $customer->receivable_balance, 0) }} · AP:
                    {{ number_format((float) $customer->payable_balance, 0) }}
                </div>
            </td>
            <td class="py-2 px-2 text-sm">
                @if ($customer->salesRep)
                <div>{{ $customer->salesRep->name }}</div>
                <div class="text-xs text-gray-500">Rep ID: {{ $customer->salesRep->id }}</div>
                @else
                <span class="text-gray-500">Unassigned</span>
                @endif
            </td>
            <td class="py-2 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $customer->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                </span>
            </td>
            <td class="py-2 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('customers.show', $customer) }}"
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
                    <a href="{{ route('customers.edit', $customer) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                        title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </a>
                </div>
            </td>
        </tr>
        @endforeach
    </x-data-table>
</x-app-layout>

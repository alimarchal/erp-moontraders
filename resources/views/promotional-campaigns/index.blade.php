<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Promotional Campaigns" :createRoute="route('promotional-campaigns.create')"
            createLabel="New Campaign" createPermission="promotional-campaign-create" :showSearch="true"
            :showRefresh="true" />
    </x-slot>

    <x-filter-section :action="route('promotional-campaigns.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_campaign_code" value="Campaign Code" />
                <x-input id="filter_campaign_code" name="filter[campaign_code]" type="text" class="mt-1 block w-full"
                    :value="request('filter.campaign_code')" placeholder="PROMO2025" />
            </div>

            <div>
                <x-label for="filter_campaign_name" value="Campaign Name" />
                <x-input id="filter_campaign_name" name="filter[campaign_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.campaign_name')" placeholder="Search..." />
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_active') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('filter.is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$campaigns" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Code'],
        ['label' => 'Campaign Name'],
        ['label' => 'Type'],
        ['label' => 'Start Date', 'align' => 'text-center'],
        ['label' => 'End Date', 'align' => 'text-center'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No promotional campaigns found."
        :emptyRoute="route('promotional-campaigns.create')" emptyLinkText="Create a Campaign">

        @foreach ($campaigns as $index => $campaign)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $campaigns->firstItem() + $index }}
                </td>
                <td class="py-1 px-2">
                    <span class="font-semibold text-gray-900">{{ $campaign->campaign_code }}</span>
                </td>
                <td class="py-1 px-2">
                    <div class="font-medium text-gray-900">{{ $campaign->campaign_name }}</div>
                    @if ($campaign->description)
                        <div class="text-xs text-gray-500 truncate max-w-xs">{{ $campaign->description }}</div>
                    @endif
                </td>
                <td class="py-1 px-2">
                    @if ($campaign->discount_type === 'buy_x_get_y')
                        <span class="px-2 py-1 text-xs font-semibold rounded bg-orange-100 text-orange-800">
                            {{ $campaign->buy_quantity }}+{{ $campaign->get_quantity }}
                        </span>
                    @elseif ($campaign->discount_type === 'percentage')
                        <span class="text-xs text-gray-600">{{ $campaign->discount_value }}% Off</span>
                    @elseif ($campaign->discount_type === 'fixed_amount')
                                <span class="text-xs text-gray-600">Rs. {{ number_format(
                            $campaign->discount_value,
                            2
                        ) }} Off</span>
                    @else
                        <span class="text-xs text-gray-600">Special Price</span>
                    @endif
                </td>
                <td class="py-1 px-2 text-center">
                    {{ $campaign->start_date->format('d M Y') }}
                </td>
                <td class="py-1 px-2 text-center">
                    {{ $campaign->end_date->format('d M Y') }}
                </td>
                <td class="py-1 px-2 text-center">
                    @if ($campaign->is_active)
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                            Active
                        </span>
                    @else
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                            Inactive
                        </span>
                    @endif
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        @can('promotional-campaign-edit')
                            <a href="{{ route('promotional-campaigns.edit', $campaign->id) }}"
                                class="text-blue-600 hover:text-blue-800" title="Edit">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        @endcan
                        @can('promotional-campaign-delete')
                            <form action="{{ route('promotional-campaigns.destroy', $campaign->id) }}" method="POST"
                                onsubmit="return confirm('Are you sure you want to delete this campaign?');"
                                class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach

    </x-data-table>
</x-app-layout>
<x-app-layout>
    <x-slot name="header">
        <x-page-header 
            title="Product Recalls" 
            :createRoute="route('product-recalls.create')" 
            createLabel="New Recall"
            createPermission="product-recall-create" 
            :showSearch="true" 
            :showRefresh="true" 
            backRoute="dashboard" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recall #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Supplier</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Qty</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Value</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($recalls as $recall)
                                    <tr>
                                        <td class="px-6 py-4">
                                            <a href="{{ route('product-recalls.show', $recall) }}" class="text-indigo-600 dark:text-indigo-400">
                                                {{ $recall->recall_number }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4">{{ $recall->recall_date->format('Y-m-d') }}</td>
                                        <td class="px-6 py-4">{{ $recall->supplier->supplier_name }}</td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                {{ ucfirst(str_replace('_', ' ', $recall->recall_type)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">{{ number_format($recall->total_quantity_recalled, 2) }}</td>
                                        <td class="px-6 py-4 text-right">{{ number_format($recall->total_value, 2) }}</td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs rounded-full 
                                                @if($recall->status === 'posted') bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100
                                                @elseif($recall->status === 'draft') bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                                                @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @endif">
                                                {{ ucfirst($recall->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <a href="{{ route('product-recalls.show', $recall) }}" class="text-indigo-600 dark:text-indigo-400">View</a>
                                            @if($recall->isDraft())
                                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                                <a href="{{ route('product-recalls.edit', $recall) }}" class="text-yellow-600 dark:text-yellow-400">Edit</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center">No product recalls found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $recalls->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <x-page-header 
            title="Stock Adjustments" 
            :createRoute="route('stock-adjustments.create')" 
            createLabel="New Adjustment"
            createPermission="stock-adjustment-create" 
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Adjustment #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($adjustments as $adjustment)
                                    <tr>
                                        <td class="px-6 py-4">{{ $adjustment->adjustment_number }}</td>
                                        <td class="px-6 py-4">{{ $adjustment->adjustment_date->format('Y-m-d') }}</td>
                                        <td class="px-6 py-4">{{ ucfirst($adjustment->adjustment_type) }}</td>
                                        <td class="px-6 py-4">{{ ucfirst($adjustment->status) }}</td>
                                        <td class="px-6 py-4">
                                            <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="text-indigo-600">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center">No stock adjustments found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $adjustments->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

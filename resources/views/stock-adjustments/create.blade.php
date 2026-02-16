<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Create Stock Adjustment
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                <form method="POST" action="{{ route('stock-adjustments.store') }}">
                    @csrf
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        Note: Create and edit views require dynamic JavaScript functionality for adding items.
                        This is a placeholder view. Please implement full functionality using Livewire or Vue.js.
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-label for="adjustment_date" value="Adjustment Date" />
                            <x-input id="adjustment_date" type="date" name="adjustment_date" :value="old('adjustment_date', today()->toDateString())" required />
                        </div>
                        <div>
                            <x-label for="warehouse_id" value="Warehouse" />
                            <select id="warehouse_id" name="warehouse_id" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full" required>
                                <option value="">Select Warehouse</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->warehouse_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-label for="adjustment_type" value="Adjustment Type" />
                            <select id="adjustment_type" name="adjustment_type" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full" required>
                                <option value="">Select Type</option>
                                <option value="damage">Damage</option>
                                <option value="theft">Theft</option>
                                <option value="count_variance">Count Variance</option>
                                <option value="expiry">Expiry</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <x-label for="reason" value="Reason" />
                            <textarea id="reason" name="reason" rows="3" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full" required></textarea>
                        </div>
                    </div>
                    <div class="mt-6">
                        <x-button>Save Draft</x-button>
                        <a href="{{ route('stock-adjustments.index') }}" class="ml-3 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

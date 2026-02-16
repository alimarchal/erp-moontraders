<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Create Product Recall
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                <form method="POST" action="{{ route('product-recalls.store') }}">
                    @csrf
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        Note: Create and edit views require dynamic JavaScript/Livewire for batch selection.
                        This is a placeholder view. Full implementation should include:
                        - Batch selection by batch code
                        - Batch selection by expiry date range
                        - Manual batch selection from list
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-label for="recall_date" value="Recall Date" />
                            <x-input id="recall_date" type="date" name="recall_date" :value="old('recall_date', today()->toDateString())" required />
                        </div>
                        <div>
                            <x-label for="supplier_id" value="Supplier" />
                            <select id="supplier_id" name="supplier_id" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full" required>
                                <option value="">Select Supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                                @endforeach
                            </select>
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
                            <x-label for="recall_type" value="Recall Type" />
                            <select id="recall_type" name="recall_type" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full" required>
                                <option value="">Select Type</option>
                                <option value="supplier_initiated">Supplier Initiated</option>
                                <option value="quality_issue">Quality Issue</option>
                                <option value="expiry">Expiry</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <x-label for="reason" value="Reason for Recall" />
                            <textarea id="reason" name="reason" rows="3" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full" required></textarea>
                        </div>
                    </div>
                    <div class="mt-6">
                        <x-button>Save Draft</x-button>
                        <a href="{{ route('product-recalls.index') }}" class="ml-3 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

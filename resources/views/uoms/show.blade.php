<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            View Unit: {{ $uom->uom_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('uoms.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <x-status-message class="mb-4 mt-4" />
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label for="uom_name" value="Unit Name" />
                            <x-input id="uom_name" type="text" name="uom_name"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                :value="$uom->uom_name" disabled readonly />
                        </div>

                        <div>
                            <x-label for="symbol" value="Symbol" />
                            <x-input id="symbol" type="text" name="symbol"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                :value="$uom->symbol ?? '—'" disabled readonly />
                        </div>
                    </div>

                    <div>
                        <x-label for="description" value="Description" />
                        <textarea id="description" name="description" rows="4"
                            class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-700 dark:text-gray-300 rounded-md shadow-sm"
                            disabled readonly>{{ $uom->description ?? '—' }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label value="Quantity Type" />
                            <div
                                class="mt-1 inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $uom->must_be_whole_number ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $uom->must_be_whole_number ? 'Whole Numbers Only' : 'Any Quantity' }}
                            </div>
                        </div>

                        <div>
                            <x-label value="Status" />
                            <div
                                class="mt-1 inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $uom->enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ $uom->enabled ? 'Enabled' : 'Disabled' }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label for="created_at" value="Created At" />
                            <x-input id="created_at" type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                :value="$uom->created_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                        </div>
                        <div>
                            <x-label for="updated_at" value="Last Updated" />
                            <x-input id="updated_at" type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                :value="$uom->updated_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-4 space-x-2">
                        <a href="{{ route('uoms.edit', $uom) }}"
                            class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                            Edit Unit
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

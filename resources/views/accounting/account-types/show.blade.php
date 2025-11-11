<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Account Type: {{ $accountType->type_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('account-types.index') }}"
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
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <x-status-message class="mb-4 mt-4" />
                <div class="p-6">
                    <form>
                        <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="flex-1">
                                    <x-label for="type_name" value="Type Name" :required="true" />
                                    <x-input id="type_name" type="text" name="type_name"
                                        class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                        :value="$accountType->type_name" disabled readonly />
                                </div>

                                <div class="flex-1">
                                    <x-label for="report_group" value="Report Group" />
                                    <x-input id="report_group" type="text" name="report_group"
                                        class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                        :value="$accountType->report_group ?? '-'" disabled readonly />
                                </div>
                            </div>
                        </div>

                        <div class="col-span-2 mt-4">
                            <x-label for="description" value="Description" />
                            <textarea id="description" name="description"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100 border-gray-300 rounded-md shadow-sm"
                                rows="3" disabled readonly>{{ $accountType->description ?? '-' }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="created_at" value="Created At" />
                                <x-input id="created_at" type="text" name="created_at"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountType->created_at->format('d-m-Y H:i:s')" disabled readonly />
                            </div>

                            <div>
                                <x-label for="updated_at" value="Last Updated" />
                                <x-input id="updated_at" type="text" name="updated_at"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountType->updated_at->format('d-m-Y H:i:s')" disabled readonly />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-2">
                            <a href="{{ route('account-types.edit', $accountType) }}"
                                class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit Account Type
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Add not-allowed cursor on hover for disabled inputs */
        input:disabled,
        textarea:disabled {
            cursor: not-allowed !important;
        }
    </style>
</x-app-layout>
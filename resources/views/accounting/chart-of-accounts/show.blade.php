<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            View Account: {{ $chartOfAccount->account_code }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('chart-of-accounts.index') }}"
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
                <div class="p-6">
                    <form>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="account_code" value="Account Code" :required="true" />
                                <x-input id="account_code" type="text" name="account_code"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                    :value="$chartOfAccount->account_code" disabled readonly />
                            </div>

                            <div>
                                <x-label for="account_name" value="Account Name" :required="true" />
                                <x-input id="account_name" type="text" name="account_name"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                    :value="$chartOfAccount->account_name" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="account_type" value="Account Type" />
                                <x-input id="account_type" type="text" name="account_type"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                    :value="$chartOfAccount->accountType->type_name ?? '-'" disabled readonly />
                            </div>

                            <div>
                                <x-label for="currency" value="Currency" />
                                <x-input id="currency" type="text" name="currency"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                    :value="$chartOfAccount->currency->currency_code ?? '-'" disabled readonly />
                            </div>

                            <div>
                                <x-label for="normal_balance" value="Normal Balance" />
                                <x-input id="normal_balance" type="text" name="normal_balance"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700 capitalize"
                                    :value="$chartOfAccount->normal_balance" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="parent_account" value="Parent Account" />
                                <x-input id="parent_account" type="text" name="parent_account"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                    :value="$chartOfAccount->parent ? $chartOfAccount->parent->account_code . ' - ' . $chartOfAccount->parent->account_name : '-'"
                                    disabled readonly />
                            </div>

                            <div class="flex items-center space-x-6 mt-6">
                                <div class="flex items-center">
                                    <input id="is_group" type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                        {{ $chartOfAccount->is_group ? 'checked' : '' }} disabled>
                                    <label for="is_group" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Group (non-posting) account
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input id="is_active" type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                        {{ $chartOfAccount->is_active ? 'checked' : '' }} disabled>
                                    <label for="is_active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Account is active
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-label for="description" value="Description" />
                            <textarea id="description" name="description"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-700 dark:text-gray-300 rounded-md shadow-sm"
                                rows="3" disabled readonly>{{ $chartOfAccount->description ?? '-' }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="created_at" value="Created At" />
                                <x-input id="created_at" type="text" name="created_at"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                    :value="$chartOfAccount->created_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                            </div>

                            <div>
                                <x-label for="updated_at" value="Last Updated" />
                                <x-input id="updated_at" type="text" name="updated_at"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-700"
                                    :value="$chartOfAccount->updated_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-2">
                            <a href="{{ route('chart-of-accounts.edit', $chartOfAccount) }}"
                                class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit Account
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        input:disabled,
        textarea:disabled {
            cursor: not-allowed !important;
        }
    </style>
</x-app-layout>

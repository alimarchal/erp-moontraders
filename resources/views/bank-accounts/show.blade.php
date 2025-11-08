<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Bank Account Details
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('bank-accounts.edit', $account) }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-green-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Edit
            </a>
            <a href="{{ route('bank-accounts.index') }}"
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
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Name</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $account->account_name }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $account->account_number }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Bank Name</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $account->bank_name ?? '—' }}
                            </p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Branch</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $account->branch ?? '—' }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">IBAN</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $account->iban ?? '—' }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">SWIFT Code</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $account->swift_code ?? '—' }}
                            </p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Chart of Account</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                                {{ $account->chartOfAccount ? $account->chartOfAccount->account_code . ' - ' .
                                $account->chartOfAccount->account_name : '—' }}
                            </p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</h3>
                            <p class="mt-1">
                                <span
                                    class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full {{ $account->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </p>
                        </div>

                        @if($account->description)
                        <div class="md:col-span-2">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</h3>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $account->description }}</p>
                        </div>
                        @endif

                        <div class="md:col-span-2">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</h3>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $account->created_at->format('F j, Y g:i
                                A') }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</h3>
                            <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $account->updated_at->format('F j, Y g:i
                                A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
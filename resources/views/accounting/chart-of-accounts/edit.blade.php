<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Edit Account &mdash; {{ $chartOfAccount->account_code }}
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
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />
                    <form method="POST" action="{{ route('chart-of-accounts.update', $chartOfAccount) }}">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="account_code" value="Account Code" :required="true" />
                                <x-input id="account_code" type="text" name="account_code"
                                    class="mt-1 block w-full" :value="old('account_code', $chartOfAccount->account_code)"
                                    required />
                            </div>

                            <div>
                                <x-label for="account_name" value="Account Name" :required="true" />
                                <x-input id="account_name" type="text" name="account_name"
                                    class="mt-1 block w-full" :value="old('account_name', $chartOfAccount->account_name)"
                                    required />
                            </div>

                            <div>
                                <x-label for="account_type_id" value="Account Type" :required="true" />
                                <select id="account_type_id" name="account_type_id"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
                                    required>
                                    <option value="">Select Type</option>
                                    @foreach ($accountTypes as $typeId => $typeName)
                                        <option value="{{ $typeId }}"
                                            {{ old('account_type_id', $chartOfAccount->account_type_id) == $typeId ? 'selected' : '' }}>
                                            {{ $typeName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="currency_id" value="Currency" :required="true" />
                                <select id="currency_id" name="currency_id"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
                                    required>
                                    <option value="">Select Currency</option>
                                    @foreach ($currencies as $currencyId => $currencyCode)
                                        <option value="{{ $currencyId }}"
                                            {{ old('currency_id', $chartOfAccount->currency_id) == $currencyId ? 'selected' : '' }}>
                                            {{ $currencyCode }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="normal_balance" value="Normal Balance" :required="true" />
                                <select id="normal_balance" name="normal_balance"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
                                    required>
                                    <option value="">Select Balance</option>
                                    @foreach ($normalBalances as $value => $label)
                                        <option value="{{ $value }}"
                                            {{ old('normal_balance', $chartOfAccount->normal_balance) == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="parent_id" value="Parent Account" />
                                <select id="parent_id" name="parent_id"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">No Parent (top level)</option>
                                    @foreach ($parentAccounts as $parent)
                                        <option value="{{ $parent->id }}"
                                            {{ old('parent_id', $chartOfAccount->parent_id) == $parent->id ? 'selected' : '' }}>
                                            {{ $parent->account_code }} · {{ $parent->account_name }} {{ $parent->is_group ? '(Group)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <x-label for="description" value="Description" />
                                <textarea id="description" name="description"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    rows="3" placeholder="Optional notes for this account">{{ old('description', $chartOfAccount->description) }}</textarea>
                            </div>

                            <div class="flex items-center space-x-6 md:col-span-2">
                                <div class="flex items-center">
                                    <input type="hidden" name="is_group" value="0">
                                    <input id="is_group" type="checkbox" name="is_group" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        {{ old('is_group', $chartOfAccount->is_group) ? 'checked' : '' }}>
                                    <label for="is_group" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        This is a group (non-posting) account
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="hidden" name="is_active" value="0">
                                    <input id="is_active" type="checkbox" name="is_active" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        {{ old('is_active', $chartOfAccount->is_active) ? 'checked' : '' }}>
                                    <label for="is_active" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        Account is active
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button class="ml-4">
                                Update Account
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Create Promotional Campaign
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('promotional-campaigns.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4" />
                    <form method="POST" action="{{ route('promotional-campaigns.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-label for="campaign_code" value="Campaign Code *" />
                                <x-input id="campaign_code" name="campaign_code" type="text" class="mt-1 block w-full"
                                    :value="old('campaign_code')" required autofocus placeholder="PROMO2025" />
                            </div>

                            <div>
                                <x-label for="campaign_name" value="Campaign Name *" />
                                <x-input id="campaign_name" name="campaign_name" type="text" class="mt-1 block w-full"
                                    :value="old('campaign_name')" required placeholder="Winter Sale 2025" />
                            </div>

                            <div>
                                <x-label for="start_date" value="Start Date *" />
                                <x-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                    :value="old('start_date')" required />
                            </div>

                            <div>
                                <x-label for="end_date" value="End Date *" />
                                <x-input id="end_date" name="end_date" type="date" class="mt-1 block w-full"
                                    :value="old('end_date')" required />
                            </div>

                            <div>
                                <x-label for="discount_percent" value="Default Discount %" />
                                <x-input id="discount_percent" name="discount_percent" type="number" step="0.01" min="0"
                                    max="100" class="mt-1 block w-full" :value="old('discount_percent')"
                                    placeholder="10.00" />
                            </div>

                            <div class="flex items-center mt-6">
                                <label for="is_active" class="flex items-center">
                                    <input type="checkbox" id="is_active" name="is_active" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                        {{ old('is_active', true) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                                </label>
                            </div>

                            <div class="md:col-span-2">
                                <x-label for="description" value="Description" />
                                <textarea id="description" name="description" rows="3"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button>
                                Create Campaign
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
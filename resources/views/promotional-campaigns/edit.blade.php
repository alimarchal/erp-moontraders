<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Edit Promotional Campaign
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
                    <form method="POST" action="{{ route('promotional-campaigns.update', $promotionalCampaign->id) }}"
                        x-data="campaignForm()">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-label for="campaign_code" value="Campaign Code *" />
                                <x-input id="campaign_code" name="campaign_code" type="text" class="mt-1 block w-full"
                                    :value="old('campaign_code', $promotionalCampaign->campaign_code)" required
                                    autofocus placeholder="PROMO2025" />
                            </div>

                            <div>
                                <x-label for="campaign_name" value="Campaign Name *" />
                                <x-input id="campaign_name" name="campaign_name" type="text" class="mt-1 block w-full"
                                    :value="old('campaign_name', $promotionalCampaign->campaign_name)" required
                                    placeholder="Winter Sale 2025" />
                            </div>

                            <div>
                                <x-label for="start_date" value="Start Date *" />
                                <x-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                    :value="old('start_date', $promotionalCampaign->start_date->format('Y-m-d'))"
                                    required />
                            </div>

                            <div>
                                <x-label for="end_date" value="End Date *" />
                                <x-input id="end_date" name="end_date" type="date" class="mt-1 block w-full"
                                    :value="old('end_date', $promotionalCampaign->end_date->format('Y-m-d'))"
                                    required />
                            </div>

                            <div class="md:col-span-2">
                                <x-label for="discount_type" value="Promotion Type *" />
                                <select id="discount_type" name="discount_type" x-model="discountType" required
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="percentage">Percentage Discount</option>
                                    <option value="fixed_amount">Fixed Amount Off</option>
                                    <option value="special_price">Special Price</option>
                                    <option value="buy_x_get_y">Buy X Get Y Free (e.g., 11+1)</option>
                                </select>
                            </div>

                            <!-- Show for percentage, fixed_amount, special_price -->
                            <div x-show="discountType !== 'buy_x_get_y'">
                                <x-label for="discount_value" value="Discount Value" />
                                <x-input id="discount_value" name="discount_value" type="number" step="0.01" min="0"
                                    class="mt-1 block w-full"
                                    :value="old('discount_value', $promotionalCampaign->discount_value)"
                                    placeholder="10.00" />
                                <p class="text-xs text-gray-500 mt-1" x-show="discountType === 'percentage'">Enter
                                    percentage (e.g., 10 for 10% off)</p>
                                <p class="text-xs text-gray-500 mt-1" x-show="discountType === 'fixed_amount'">Enter
                                    amount (e.g., 500 for Rs. 500 off)</p>
                                <p class="text-xs text-gray-500 mt-1" x-show="discountType === 'special_price'">Enter
                                    special price</p>
                            </div>

                            <!-- Show for buy_x_get_y -->
                            <div x-show="discountType === 'buy_x_get_y'" class="md:col-span-2">
                                <div
                                    class="grid grid-cols-2 gap-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                    <div>
                                        <x-label for="buy_quantity" value="Buy Quantity *" />
                                        <x-input id="buy_quantity" name="buy_quantity" type="number" step="1" min="1"
                                            class="mt-1 block w-full"
                                            :value="old('buy_quantity', $promotionalCampaign->buy_quantity)"
                                            placeholder="11" />
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Number of units
                                            customer must buy</p>
                                    </div>
                                    <div>
                                        <x-label for="get_quantity" value="Get Free Quantity *" />
                                        <x-input id="get_quantity" name="get_quantity" type="number" step="1" min="1"
                                            class="mt-1 block w-full"
                                            :value="old('get_quantity', $promotionalCampaign->get_quantity)"
                                            placeholder="1" />
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Number of units
                                            customer gets free</p>
                                    </div>
                                    <div class="col-span-2">
                                        <div
                                            class="flex items-center p-3 bg-green-50 dark:bg-green-900/20 rounded border border-green-200 dark:border-green-800">
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                                Example: Buy <strong>11</strong> packs, get <strong>1</strong> pack free
                                                = "11+1" promotion
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center mt-6">
                                <label for="is_active" class="flex items-center">
                                    <input type="checkbox" id="is_active" name="is_active" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                        {{ old('is_active', $promotionalCampaign->is_active) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                                </label>
                            </div>

                            <div class="md:col-span-2">
                                <x-label for="description" value="Description" />
                                <textarea id="description" name="description" rows="3"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">{{ old('description', $promotionalCampaign->description) }}</textarea>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button>
                                Update Campaign
                            </x-button>
                        </div>
                    </form>

                    <script>
                        function campaignForm() {
                            return {
                                discountType: '{{ old('discount_type', $promotionalCampaign->discount_type ?? 'percentage') }}'
                            }
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
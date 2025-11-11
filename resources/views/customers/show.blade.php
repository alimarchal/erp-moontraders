<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Customer: {{ $customer->customer_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('customers.index') }}"
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
                <div class="p-6 space-y-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500">Customer Overview</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $customer->customer_code }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full {{ $customer->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ $customer->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <a href="{{ route('customers.edit', $customer) }}"
                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit Customer
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label value="Customer Name" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->customer_name" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Business Name" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->business_name ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Sales Representative" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->salesRep?->name ?? 'Unassigned'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Channel & Category" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->channel_type . ' · Category ' . $customer->customer_category" disabled
                                readonly />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label value="Phone" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->phone ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Email" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->email ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Payment Terms (days)" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->payment_terms ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Last Sale Date" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="optional($customer->last_sale_date)?->format('Y-m-d') ?? '—'" disabled readonly />
                        </div>
                    </div>

                    <div>
                        <x-label value="Address" />
                        <textarea rows="3"
                            class="mt-1 block w-full border-gray-300 bg-gray-100 text-gray-700 rounded-md shadow-sm cursor-not-allowed"
                            disabled readonly>{{ $customer->address ?? '—' }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <x-label value="Sub Locality" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->sub_locality ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="City" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->city ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="State / Region" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->state ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Country" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->country ?? '—'" disabled readonly />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-xs uppercase text-gray-500">Credit Limit</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                {{ number_format((float) $customer->credit_limit, 2) }}
                            </p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-xs uppercase text-gray-500">Credit Used</p>
                            <p class="text-2xl font-semibold text-amber-600">
                                {{ number_format((float) $customer->credit_used, 2) }}
                            </p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-xs uppercase text-gray-500">Credit Available</p>
                            <p class="text-2xl font-semibold text-emerald-600">
                                {{ number_format($customer->getAvailableCredit(), 2) }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-label value="Receivable Balance (AR)" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="number_format((float) $customer->receivable_balance, 2)" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Payable Balance (AP)" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="number_format((float) $customer->payable_balance, 2)" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Lifetime Value" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="number_format((float) $customer->lifetime_value, 2)" disabled readonly />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label value="Receivable Account" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->receivableAccount ? $customer->receivableAccount->account_code . ' — ' . $customer->receivableAccount->account_name : 'Not linked'"
                                disabled readonly />
                        </div>
                        <div>
                            <x-label value="Payable Account" />
                            <x-input type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$customer->payableAccount ? $customer->payableAccount->account_code . ' — ' . $customer->payableAccount->account_name : 'Not linked'"
                                disabled readonly />
                        </div>
                    </div>

                    <div>
                        <x-label value="Internal Notes" />
                        <textarea rows="4"
                            class="mt-1 block w-full border-gray-300 bg-gray-100 text-gray-700 rounded-md shadow-sm cursor-not-allowed"
                            disabled readonly>{{ $customer->notes ?? '—' }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

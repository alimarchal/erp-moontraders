@php
    $taxTypeOptions = \App\Models\TaxCode::taxTypeOptions();
    $calculationMethodOptions = \App\Models\TaxCode::calculationMethodOptions();
    $taxTypeLabel = $taxTypeOptions[$taxCode->tax_type] ?? ucfirst(str_replace('_', ' ', $taxCode->tax_type));
    $calculationMethodLabel = $calculationMethodOptions[$taxCode->calculation_method] ?? ucfirst(str_replace('_', ' ', $taxCode->calculation_method));
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Tax Code: {{ $taxCode->tax_code }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('tax-codes.index') }}"
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
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="tax_code" value="Tax Code" />
                                <x-input id="tax_code" type="text" name="tax_code"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxCode->tax_code" disabled readonly />
                            </div>

                            <div>
                                <x-label for="name" value="Tax Name" />
                                <x-input id="name" type="text" name="name"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxCode->name" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <x-label for="description" value="Description" />
                                <textarea id="description" name="description"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full cursor-not-allowed bg-gray-100"
                                    rows="3" disabled readonly>{{ $taxCode->description }}</textarea>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="tax_type" value="Tax Type" />
                                <x-input id="tax_type" type="text" name="tax_type"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxTypeLabel" disabled readonly />
                            </div>

                            <div>
                                <x-label for="calculation_method" value="Calculation Method" />
                                <x-input id="calculation_method" type="text" name="calculation_method"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$calculationMethodLabel" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="tax_payable_account" value="Tax Payable Account" />
                                <x-input id="tax_payable_account" type="text" name="tax_payable_account"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxCode->taxPayableAccount ? $taxCode->taxPayableAccount->account_code . ' - ' . $taxCode->taxPayableAccount->account_name : 'N/A'"
                                    disabled readonly />
                            </div>

                            <div>
                                <x-label for="tax_receivable_account" value="Tax Receivable Account" />
                                <x-input id="tax_receivable_account" type="text" name="tax_receivable_account"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxCode->taxReceivableAccount ? $taxCode->taxReceivableAccount->account_code . ' - ' . $taxCode->taxReceivableAccount->account_name : 'N/A'"
                                    disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                            <div class="flex items-center">
                                <input id="is_active" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $taxCode->is_active ? 'checked' : '' }} disabled>
                                <label for="is_active" class="ml-2 text-sm text-gray-700">
                                    Active
                                </label>
                            </div>

                            <div class="flex items-center">
                                <input id="is_compound" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $taxCode->is_compound ? 'checked' : '' }} disabled>
                                <label for="is_compound" class="ml-2 text-sm text-gray-700">
                                    Compound Tax
                                </label>
                            </div>

                            <div class="flex items-center">
                                <input id="included_in_price" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $taxCode->included_in_price ? 'checked' : '' }} disabled>
                                <label for="included_in_price" class="ml-2 text-sm text-gray-700">
                                    Included in Price
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="created_at" value="Created At" />
                                <x-input id="created_at" type="text" name="created_at"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxCode->created_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                            </div>

                            <div>
                                <x-label for="updated_at" value="Last Updated" />
                                <x-input id="updated_at" type="text" name="updated_at"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxCode->updated_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-2">
                            <a href="{{ route('tax-codes.edit', $taxCode) }}"
                                class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit Tax Code
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tax Rates Section -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mt-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Tax Rates</h3>
                    </div>

                    @if($taxCode->taxRates->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate (%)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective From</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective To</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Region</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($taxCode->taxRates->sortByDesc('effective_from') as $rate)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ number_format($rate->rate, 2) }}%
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $rate->effective_from->format('d-m-Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $rate->effective_to ? $rate->effective_to->format('d-m-Y') : 'Ongoing' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $rate->region ?? 'All Regions' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                                                    {{ $rate->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                                    {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 text-sm">No tax rates defined for this tax code.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        input:disabled {
            cursor: not-allowed !important;
        }
    </style>
</x-app-layout>

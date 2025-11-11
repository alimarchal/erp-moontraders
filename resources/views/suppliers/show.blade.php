@php
    $currency = $supplier->defaultCurrency;
    $bankAccount = $supplier->defaultBankAccount;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Supplier: {{ $supplier->supplier_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('suppliers.index') }}"
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
                                <x-label value="Supplier Name" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->supplier_name" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Short Name" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 uppercase"
                                    :value="$supplier->short_name ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Supplier Group" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->supplier_group ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Supplier Type" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->supplier_type ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Country" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->country ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Default Price List" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->default_price_list ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Default Currency" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$currency ? $currency->currency_code . ' · ' . $currency->currency_name : '—'"
                                    disabled readonly />
                            </div>
                            <div>
                                <x-label value="Default Bank Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$bankAccount ? $bankAccount->account_code . ' · ' . $bankAccount->account_name : '—'"
                                    disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Website" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->website ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Print Language" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->print_language ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Tax ID" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->tax_id ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="PAN Number" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->pan_number ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div class="flex items-center">
                                <input type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $supplier->disabled ? 'checked' : '' }} disabled>
                                <label class="ml-2 text-sm text-gray-700">
                                    Supplier is disabled
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $supplier->is_transporter ? 'checked' : '' }} disabled>
                                <label class="ml-2 text-sm text-gray-700">
                                    Provides transportation
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $supplier->is_internal_supplier ? 'checked' : '' }} disabled>
                                <label class="ml-2 text-sm text-gray-700">
                                    Internal supplier
                                </label>
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-label value="Primary Address" />
                            <textarea rows="3"
                                class="mt-1 block w-full border-gray-300 cursor-not-allowed bg-gray-100 rounded-md shadow-sm"
                                disabled readonly>{{ $supplier->supplier_primary_address ?? '—' }}</textarea>
                        </div>

                        <div class="mt-4">
                            <x-label value="Primary Contact" />
                            <textarea rows="3"
                                class="mt-1 block w-full border-gray-300 cursor-not-allowed bg-gray-100 rounded-md shadow-sm"
                                disabled readonly>{{ $supplier->supplier_primary_contact ?? '—' }}</textarea>
                        </div>

                        <div class="mt-4">
                            <x-label value="Additional Details" />
                            <textarea rows="4"
                                class="mt-1 block w-full border-gray-300 cursor-not-allowed bg-gray-100 rounded-md shadow-sm"
                                disabled readonly>{{ $supplier->supplier_details ?? '—' }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Created At" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->created_at?->format('d-m-Y H:i:s') ?? '—'"
                                    disabled readonly />
                            </div>
                            <div>
                                <x-label value="Last Updated" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$supplier->updated_at?->format('d-m-Y H:i:s') ?? '—'"
                                    disabled readonly />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-2">
                            <a href="{{ route('suppliers.edit', $supplier->id) }}"
                                class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit Supplier
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

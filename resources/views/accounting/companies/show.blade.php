<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Company: {{ $company->company_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('companies.index') }}"
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
                                <x-label value="Company Name" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->company_name" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Abbreviation" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->abbr ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Country" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->country ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Tax ID" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->tax_id ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Domain" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->domain ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Parent Company" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->parentCompany ? $company->parentCompany->company_name : '—'"
                                    disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Phone Number" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->phone_no ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Email" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->email ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Fax" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->fax ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Website" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->website ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label value="Date of Establishment" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="optional($company->date_of_establishment)?->format('d-m-Y') ?? '—'" disabled
                                    readonly />
                            </div>
                            <div>
                                <x-label value="Date of Incorporation" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="optional($company->date_of_incorporation)?->format('d-m-Y') ?? '—'" disabled
                                    readonly />
                            </div>
                            <div>
                                <x-label value="Date of Commencement" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="optional($company->date_of_commencement)?->format('d-m-Y') ?? '—'" disabled
                                    readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Default Currency" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->defaultCurrency ? ($company->defaultCurrency->currency_code . ' · ' . $company->defaultCurrency->currency_name) : '—'"
                                    disabled readonly />
                            </div>
                            <div>
                                <x-label value="Default Cost Center" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->costCenter ? ($company->costCenter->code . ' · ' . $company->costCenter->name) : '—'"
                                    disabled readonly />
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-label value="Company Description" />
                            <textarea rows="3"
                                class="mt-1 block w-full border-gray-300 cursor-not-allowed bg-gray-100 rounded-md shadow-sm"
                                disabled readonly>{{ $company->company_description ?? '—' }}</textarea>
                        </div>

                        <div class="mt-4">
                            <x-label value="Registration Details" />
                            <textarea rows="3"
                                class="mt-1 block w-full border-gray-300 cursor-not-allowed bg-gray-100 rounded-md shadow-sm"
                                disabled readonly>{{ $company->registration_details ?? '—' }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Credit Limit" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="!is_null($company->credit_limit) ? number_format((float) $company->credit_limit, 2) : '—'"
                                    disabled readonly />
                            </div>
                            <div>
                                <x-label value="Monthly Sales Target" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="!is_null($company->monthly_sales_target) ? number_format((float) $company->monthly_sales_target, 2) : '—'"
                                    disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            @php
                            $accountDisplay = function ($account) {
                            return $account ? ($account->account_code . ' · ' . $account->account_name) : '—';
                            };
                            @endphp

                            <div>
                                <x-label value="Default Bank Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->defaultBankAccount)" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Default Cash Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->defaultCashAccount)" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Default Receivable Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->defaultReceivableAccount)" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Default Payable Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->defaultPayableAccount)" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Default Expense Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->defaultExpenseAccount)" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Default Income Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->defaultIncomeAccount)" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Write-off Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->writeOffAccount)" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Round-off Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->roundOffAccount)" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Default Inventory Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->defaultInventoryAccount)" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Stock Adjustment Account" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$accountDisplay($company->stockAdjustmentAccount)" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                            <div class="flex items-center">
                                <input type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $company->is_group ? 'checked' : '' }} disabled>
                                <label class="ml-2 text-sm text-gray-700">
                                    This is a group company
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $company->enable_perpetual_inventory ? 'checked' : '' }} disabled>
                                <label class="ml-2 text-sm text-gray-700">
                                    Enable perpetual inventory
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $company->allow_account_creation_against_child_company ? 'checked' : '' }}
                                disabled>
                                <label class="ml-2 text-sm text-gray-700">
                                    Allow account creation against child companies
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Created At" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->created_at?->format('d-m-Y H:i:s') ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Last Updated" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$company->updated_at?->format('d-m-Y H:i:s') ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-2">
                            <a href="{{ route('companies.edit', $company->id) }}"
                                class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit Company
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
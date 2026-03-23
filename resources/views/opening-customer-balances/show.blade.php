<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Opening Balance: {{ $transaction->account->customer->customer_name }}
        </h2>
        <div class="flex justify-center items-center float-right gap-2">
            @if(!$transaction->isPosted())
                @can('opening-customer-balance-post')
                    <button type="button" x-data x-on:click="$dispatch('open-post-modal')"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Post to GL
                    </button>
                @endcan
            @else
                <span
                    class="inline-flex items-center px-3 py-2 bg-emerald-100 text-emerald-800 rounded-md text-xs font-semibold uppercase">
                    <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Posted
                </span>
            @endif
            @if(!$transaction->isPosted())
                @can('opening-customer-balance-edit')
                    <a href="{{ route('opening-customer-balances.edit', $transaction) }}"
                        class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </a>
                @endcan
            @endif
            <a href="{{ route('opening-customer-balances.index') }}"
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
            <x-status-message class="mb-4 mt-4" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label value="Employee / Salesman" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$transaction->account->employee->employee_code . ' — ' . $transaction->account->employee->name" disabled readonly />
                        </div>

                        <div>
                            <x-label value="Customer" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$transaction->account->customer->customer_code . ' — ' . $transaction->account->customer->customer_name" disabled readonly />
                        </div>

                        <div>
                            <x-label value="Supplier" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$transaction->account->employee->supplier->supplier_name ?? '—'" disabled
                                readonly />
                        </div>

                        <div>
                            <x-label value="Account Number" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$transaction->account->account_number" disabled readonly />
                        </div>

                        <div>
                            <x-label value="Balance Date" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$transaction->transaction_date->format('d-M-Y')" disabled readonly />
                        </div>

                        <div>
                            <x-label value="Opening Balance" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100 font-mono"
                                :value="number_format($transaction->debit, 2)" disabled readonly />
                        </div>

                        <div>
                            <x-label value="Reference Number" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$transaction->reference_number ?? '—'" disabled readonly />
                        </div>

                        <div>
                            <x-label value="Created By" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$transaction->createdBy->name ?? '—'" disabled readonly />
                        </div>
                    </div>

                    <div>
                        <x-label value="Description" />
                        <textarea rows="2"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm cursor-not-allowed bg-gray-100"
                            disabled readonly>{{ $transaction->description ?? '—' }}</textarea>
                    </div>

                    @if($transaction->notes)
                        <div>
                            <x-label value="Notes" />
                            <textarea rows="2"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm cursor-not-allowed bg-gray-100"
                                disabled readonly>{{ $transaction->notes }}</textarea>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-500">
                        <div>Created: {{ $transaction->created_at->format('d-M-Y H:i') }}</div>
                        <div>Updated: {{ $transaction->updated_at->format('d-M-Y H:i') }}</div>
                    </div>

                    @if($transaction->isPosted())
                        <div class="border-t pt-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">GL Posting Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-label value="Journal Entry" />
                                    <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-green-50"
                                        :value="'JE #' . $transaction->journal_entry_id" disabled readonly />
                                </div>
                                <div>
                                    <x-label value="Posted At" />
                                    <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-green-50"
                                        :value="$transaction->posted_at->format('d-M-Y H:i')" disabled readonly />
                                </div>
                                <div>
                                    <x-label value="Posted By" />
                                    <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-green-50"
                                        :value="$transaction->postedBy->name ?? '—'" disabled readonly />
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!$transaction->isPosted())
        <x-alpine-confirmation-modal eventName="open-post-modal" title="Post to General Ledger"
            message="Are you sure you want to post the opening balance of <strong>{{ number_format($transaction->debit, 2) }}</strong> for <strong>{{ $transaction->account->customer->customer_name }}</strong> to the General Ledger?<br><br>This will create a Journal Entry:<br>• <strong>Dr</strong> Debtors (Accounts Receivable)<br>• <strong>Cr</strong> Opening Balance Equity<br><br>This action cannot be undone."
            confirmButtonText="Post to GL" confirmButtonClass="bg-emerald-600 hover:bg-emerald-700"
            iconBgClass="bg-emerald-100" iconColorClass="text-emerald-600"
            iconPath="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" :formAction="route('opening-customer-balances.post', $transaction)" />
    @endif
</x-app-layout>
@php
    $statusOptions = $statusOptions ?? [];
    $transactionTypeOptions = $transactionTypeOptions ?? [];
    $paymentMethodOptions = $paymentMethodOptions ?? [];
    $statusLabel = $statusOptions[$transaction->status] ?? ucfirst($transaction->status);
    $typeLabel = $transactionTypeOptions[$transaction->transaction_type] ?? ucfirst($transaction->transaction_type);
    $paymentLabel = $paymentMethodOptions[$transaction->payment_method] ?? $transaction->payment_method ?? '-';
    $balance = (float) $transaction->debit - (float) $transaction->credit;
    $canPost = in_array($transaction->status, ['Pending', 'Approved']);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Transaction: {{ $transaction->reference_number ?? '#' . $transaction->id }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('employee-salary-transactions.index') }}"
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
                        {{-- Row 1: Employee, Supplier, Transaction Type --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-label for="employee" value="Employee" />
                                <x-input id="employee" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->employee?->name ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="supplier" value="Supplier" />
                                <x-input id="supplier" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->supplier?->supplier_name ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="transaction_type" value="Transaction Type" />
                                <x-input id="transaction_type" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$typeLabel" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 2: Transaction Date, Status, Reference --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="transaction_date" value="Transaction Date" />
                                <x-input id="transaction_date" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->transaction_date?->format('d-m-Y') ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="status" value="Status" />
                                <x-input id="status" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$statusLabel" disabled readonly />
                            </div>
                            <div>
                                <x-label for="reference_number" value="Reference Number" />
                                <x-input id="reference_number" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->reference_number ?? '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 3: Salary Month, Period Start, Period End --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="salary_month" value="Salary Month" />
                                <x-input id="salary_month" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->salary_month ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="period_start" value="Period Start" />
                                <x-input id="period_start" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->period_start?->format('d-m-Y') ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="period_end" value="Period End" />
                                <x-input id="period_end" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->period_end?->format('d-m-Y') ?? '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 4: Debit, Credit, Balance --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="debit" value="Debit" />
                                <x-input id="debit" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="number_format($transaction->debit, 2)" disabled readonly />
                            </div>
                            <div>
                                <x-label for="credit" value="Credit" />
                                <x-input id="credit" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="number_format($transaction->credit, 2)" disabled readonly />
                            </div>
                            <div>
                                <x-label for="balance" value="Balance" />
                                <x-input id="balance" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 font-bold {{ $balance > 0 ? 'text-red-600' : ($balance < 0 ? 'text-green-600' : '') }}"
                                    :value="number_format($balance, 2)" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 5: Description --}}
                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <x-label for="description" value="Description" />
                                <x-input id="description" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->description ?? '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 6: Accounts --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="debit_account" value="Debit Account" />
                                <x-input id="debit_account" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->debitAccount ? $transaction->debitAccount->account_code . ' - ' . $transaction->debitAccount->account_name : '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="credit_account" value="Credit Account" />
                                <x-input id="credit_account" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->creditAccount ? $transaction->creditAccount->account_code . ' - ' . $transaction->creditAccount->account_name : '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 7: Payment Details --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="payment_method" value="Payment Method" />
                                <x-input id="payment_method" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$paymentLabel" disabled readonly />
                            </div>
                            <div>
                                <x-label for="cheque_number" value="Cheque Number" />
                                <x-input id="cheque_number" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->cheque_number ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="cheque_date" value="Cheque Date" />
                                <x-input id="cheque_date" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->cheque_date?->format('d-m-Y') ?? '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 8: Bank Account --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="bank_account" value="Bank Account" />
                                <x-input id="bank_account" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->bankAccount ? $transaction->bankAccount->account_name . ' (' . $transaction->bankAccount->bank_name . ')' : '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 9: Notes --}}
                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <x-label for="notes" value="Notes" />
                                <x-input id="notes" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->notes ?? '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 10: Timestamps --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="created_at" value="Created At" />
                                <x-input id="created_at" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->created_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="updated_at" value="Last Updated" />
                                <x-input id="updated_at" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transaction->updated_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-2">
                            @if ($canPost)
                                @can('employee-salary-transaction-post')
                                    <form method="POST" action="{{ route('employee-salary-transactions.post', $transaction) }}"
                                        onsubmit="return confirm('Are you sure you want to post this transaction?');" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-indigo-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Post Transaction
                                        </button>
                                    </form>
                                @endcan
                            @endif
                            <a href="{{ route('employee-salary-transactions.edit', $transaction) }}"
                                class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit Transaction
                            </a>
                        </div>
                    </form>
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

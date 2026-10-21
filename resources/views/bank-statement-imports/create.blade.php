<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Import Bank Statement" :showSearch="false" :showRefresh="false" backRoute="bank-statement-imports.index" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />

            {{-- Step indicator --}}
            <ol class="flex items-center gap-2 mb-4 text-sm">
                <li class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-blue-950 text-white text-xs grid place-items-center">1</span><b>Upload</b></li>
                <li class="text-gray-300">——</li>
                <li class="flex items-center gap-2 text-gray-400"><span class="w-6 h-6 rounded-full bg-gray-200 text-xs grid place-items-center">2</span>Check summary</li>
                <li class="text-gray-300">——</li>
                <li class="flex items-center gap-2 text-gray-400"><span class="w-6 h-6 rounded-full bg-gray-200 text-xs grid place-items-center">3</span>Confirm</li>
            </ol>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <x-validation-errors class="mb-4" />
                <form method="POST" action="{{ route('bank-statement-imports.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label for="bank_account_id" value="Bank account" :required="true" />
                            <select data-allow-clear="false" id="bank_account_id" name="bank_account_id" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full" required>
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((int) old('bank_account_id') === $account->id)>{{ $account->account_name }} ({{ $account->account_number }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-label for="file" value="Statement file (Excel or CSV)" :required="true" />
                            <input id="file" name="file" type="file" accept=".xlsx,.xls,.csv" required class="mt-1 block w-full text-sm border border-gray-300 rounded-md p-1.5">
                        </div>
                    </div>

                    <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <p class="text-sm font-semibold text-blue-900">Figures from the printed statement (recommended)</p>
                        <p class="text-xs text-blue-800 mb-3">The system adds up the file and compares it with these. If they differ, the import is blocked so nothing is lost or counted twice.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="opening_balance" value="Opening balance" />
                                <x-input id="opening_balance" name="opening_balance" type="number" step="0.01" class="mt-1 block w-full" :value="old('opening_balance')" />
                            </div>
                            <div>
                                <x-label for="closing_balance" value="Closing balance" />
                                <x-input id="closing_balance" name="closing_balance" type="number" step="0.01" class="mt-1 block w-full" :value="old('closing_balance')" />
                            </div>
                            <div>
                                <x-label for="control_total_debit" value="Total debits (withdrawals)" />
                                <x-input id="control_total_debit" name="control_total_debit" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('control_total_debit')" />
                            </div>
                            <div>
                                <x-label for="control_total_credit" value="Total credits (deposits)" />
                                <x-input id="control_total_credit" name="control_total_credit" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('control_total_credit')" />
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-gray-500">The file needs a header row with <b>Posting date</b> and <b>Debit</b> / <b>Credit</b> columns. Other columns (reference, narration, party names, value date, balance) are picked up when present.</p>

                    <div class="flex items-center justify-between mt-6">
                        <a href="{{ route('bank-statement-imports.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                        <x-button class="bg-blue-950">Read file &amp; show summary</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

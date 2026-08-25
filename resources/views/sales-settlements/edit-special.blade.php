<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Special Edit — Settlement: {{ $settlement->settlement_number }}
            </h2>
            <div class="flex items-center space-x-2">
                <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                    Super Admin Only
                </span>
                <a href="{{ route('sales-settlements.show', $settlement->id) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md" />

            {{-- Warning Banner --}}
            <div class="mb-4 p-4 bg-amber-50 border border-amber-300 rounded-lg shadow">
                <div class="flex items-start space-x-3">
                    <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    <div class="text-sm text-amber-800">
                        <p class="font-semibold mb-1">This action corrects the settlement date across 8 tables
                            simultaneously.</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            <li><strong>sales_settlements</strong>.settlement_date</li>
                            <li><strong>stock_movements</strong>.movement_date (sale, return &amp; shortage movements)
                            </li>
                            <li><strong>inventory_ledger_entries</strong>.date</li>
                            <li><strong>customer_employee_account_transactions</strong>.transaction_date (credit
                                sales &amp; recoveries)</li>
                            <li><strong>sales_settlement_expenses</strong>.expense_date</li>
                            <li><strong>sales_settlement_bank_transfers</strong>.transfer_date (only if unchanged from
                                settlement date)</li>
                            <li><strong>sales_settlement_cheques</strong>.cheque_date (only if unchanged from
                                settlement date)</li>
                            <li><strong>sales_settlement_bank_slips</strong>.deposit_date (only if unchanged from
                                settlement date)</li>
                        </ul>
                        <p class="font-semibold mt-2">The linked journal entry's date is NOT changed — posted journal
                            entries are immutable by design. Only the operational date is corrected.</p>
                    </div>
                </div>
            </div>

            {{-- Settlement Header Info --}}
            <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Employee</span>
                        <p class="font-medium">{{ $settlement->employee->name ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Vehicle</span>
                        <p class="font-medium">{{ $settlement->vehicle->vehicle_number ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Current Settlement Date</span>
                        <p class="font-medium">{{ $settlement->settlement_date->toDateString() }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Status</span>
                        <p class="font-medium capitalize">{{ $settlement->status }}</p>
                    </div>
                </div>
            </div>

            {{-- Date Correction Form --}}
            <form action="{{ route('sales-settlements.update-special', $settlement->id) }}" method="POST">
                @csrf

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <x-label for="settlement_date" value="New Settlement Date" />
                    <x-input id="settlement_date" name="settlement_date" type="date" class="mt-1 block w-full"
                        value="{{ old('settlement_date', $settlement->settlement_date->toDateString()) }}" required />
                </div>

                <div class="mt-4 flex justify-end space-x-3">
                    <a href="{{ route('sales-settlements.show', $settlement->id) }}"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit" onclick="return confirm('Apply this date correction? This cannot be undone.')"
                        class="inline-flex items-center px-6 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Apply Correction
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Start Bank Reconciliation" :showSearch="false" :showRefresh="false" backRoute="bank-reconciliations.index" />
    </x-slot>
    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <x-validation-errors class="mb-4" />
                <form method="POST" action="{{ route('bank-reconciliations.store') }}">
                    @csrf
                    <div>
                        <x-label for="bank_account_id" value="Bank account" :required="true" />
                        <select id="bank_account_id" name="bank_account_id" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full" required>
                            @foreach ($bankAccounts as $account)
                                <option value="{{ $account->id }}" @selected((int) old('bank_account_id') === $account->id)>{{ $account->account_name }} ({{ $account->account_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <x-label for="period_from" value="Period from" :required="true" />
                            <x-input id="period_from" name="period_from" type="date" class="mt-1 block w-full" required :value="old('period_from')" />
                        </div>
                        <div>
                            <x-label for="period_to" value="Period to" :required="true" />
                            <x-input id="period_to" name="period_to" type="date" class="mt-1 block w-full" required :value="old('period_to')" />
                        </div>
                        <div>
                            <x-label for="opening_balance" value="Statement opening balance" :required="true" />
                            <x-input id="opening_balance" name="opening_balance" type="number" step="0.01" class="mt-1 block w-full" required :value="old('opening_balance', 0)" />
                        </div>
                        <div>
                            <x-label for="statement_balance" value="Statement closing balance" :required="true" />
                            <x-input id="statement_balance" name="statement_balance" type="number" step="0.01" class="mt-1 block w-full" required :value="old('statement_balance')" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="2" dir="auto" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">{{ old('notes') }}</textarea>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">Lines already posted from the register are matched automatically. Anything left over is matched by hand on the next screen. When every line is matched and the difference is zero, the period is locked.</p>
                    <div class="flex items-center justify-between mt-6">
                        <a href="{{ route('bank-reconciliations.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                        <x-button class="bg-blue-950">Start</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bank Register Ledger Accounts" :showSearch="false" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-900">
                <p class="font-semibold">The Bank Register keeps its own books.</p>
                <p>Every journal it posts uses only the accounts below (codes 6000 onwards). GRN, settlements, goods issues, supplier payments and every other live account are never touched, and these accounts are kept out of the <b>Balance Sheet</b> and <b>Income Statement</b> (report group <i>BankRegister</i>). They do appear in the Trial Balance, General Ledger and Account Balances, so the register can be tracked on its own.</p>
            </div>

            <div class="bg-white shadow-xl sm:rounded-lg overflow-hidden">
                <p class="font-semibold bg-gray-50 border-b px-4 py-2">Register accounts</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase text-gray-500 bg-gray-50">
                            <tr><th class="px-4 py-2 text-left">Code</th><th class="px-4 py-2 text-left">Account</th><th class="px-4 py-2 text-left">Normal</th><th class="px-4 py-2 text-right">Lines</th><th class="px-4 py-2 text-right">Debits</th><th class="px-4 py-2 text-right">Credits</th><th class="px-4 py-2 text-right">Balance</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $account)
                                @php($t = $totals[$account->id] ?? null)
                                @php($balance = $t ? (float) $t->total_debit - (float) $t->total_credit : 0.0)
                                <tr class="border-t {{ $account->is_group ? 'bg-gray-50 font-semibold' : '' }}">
                                    <td class="px-4 py-1.5 font-mono">{{ $account->account_code }}</td>
                                    <td class="px-4 py-1.5" style="padding-inline-start: {{ $account->is_group ? '1rem' : '2rem' }}">{{ $account->account_name }}</td>
                                    <td class="px-4 py-1.5 text-gray-500">{{ ucfirst($account->normal_balance) }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums">{{ $t ? number_format($t->line_count) : '' }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums">{{ $t ? number_format($t->total_debit, 2) : '' }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums">{{ $t ? number_format($t->total_credit, 2) : '' }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums {{ $balance < 0 ? 'text-red-700' : '' }}">{{ $t ? number_format($balance, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow-xl sm:rounded-lg overflow-hidden">
                <p class="font-semibold bg-gray-50 border-b px-4 py-2">Each bank / cash account: live ledger vs register ledger</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase text-gray-500 bg-gray-50">
                            <tr><th class="px-4 py-2 text-left">Account</th><th class="px-4 py-2 text-left">Live ledger (used by settlements, payments …)</th><th class="px-4 py-2 text-left">Register ledger (used only here)</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($bankAccounts as $bank)
                                <tr class="border-t">
                                    <td class="px-4 py-1.5">{{ $bank->account_name }} <span class="text-xs text-gray-500">{{ $bank->account_number }}</span>@unless ($bank->is_active) <span class="text-xs text-red-600">(inactive)</span>@endunless</td>
                                    <td class="px-4 py-1.5">{{ $bank->chartOfAccount ? $bank->chartOfAccount->account_code.' — '.$bank->chartOfAccount->account_name : '—' }}</td>
                                    <td class="px-4 py-1.5">{{ $bank->registerLedger ? $bank->registerLedger->account_code.' — '.$bank->registerLedger->account_name : 'created on first posting' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

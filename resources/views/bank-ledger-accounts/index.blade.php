<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Ledger accounts" sub="The register's own chart of accounts (6000 onwards), kept apart from every live account." :crumbs="['Ledger accounts' => null]">
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page">
        <x-bank.subnav inside />
        <x-bank.print-title title="Bank Register Ledger Accounts" />

        <div class="ak-alert bank-noprint" role="note" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a">
            <b>The Bank Register keeps its own books.</b>
            Every journal it posts uses only the accounts below (codes 6000 onwards). GRN, settlements, goods issues, supplier payments and every other live account are never touched, and these accounts are kept out of the <b>Balance Sheet</b> and <b>Income Statement</b> (report group <i>BankRegister</i>). They do appear in the Trial Balance, General Ledger and Account Balances, so the register can be tracked on its own.
        </div>

        <section class="ak-card">
            <div class="ak-dt-toolbar"><p><b>Register accounts</b></p></div>
            <div class="ak-dt-scroll" tabindex="0">
                <table class="ak-dt">
                    <caption class="sr-only">Register accounts with their balances</caption>
                    <thead><tr><th scope="col">Code</th><th scope="col">Account</th><th scope="col">Normal</th><th scope="col" class="ak-num">Lines</th><th scope="col" class="ak-num">Debits</th><th scope="col" class="ak-num">Credits</th><th scope="col" class="ak-num">Balance</th></tr></thead>
                    <tbody>
                        @foreach ($accounts as $account)
                            @php($t = $totals[$account->id] ?? null)
                            @php($balance = $t ? (float) $t->total_debit - (float) $t->total_credit : 0.0)
                            <tr>
                                <td class="ak-mono"><b>{{ $account->account_code }}</b></td>
                                <td style="padding-inline-start: {{ $account->is_group ? '10px' : '30px' }}">@if ($account->is_group)<b>{{ $account->account_name }}</b>@else{{ $account->account_name }}@endif</td>
                                <td class="ak-muted">{{ ucfirst($account->normal_balance) }}</td>
                                <td class="ak-num">{{ $t ? number_format($t->line_count) : '' }}</td>
                                <td class="ak-num">{{ $t ? number_format($t->total_debit, 2) : '' }}</td>
                                <td class="ak-num">{{ $t ? number_format($t->total_credit, 2) : '' }}</td>
                                <td class="ak-num"><b class="{{ $balance < 0 ? 'bk-out' : '' }}">{{ $t ? number_format($balance, 2) : '' }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ak-card">
            <div class="ak-dt-toolbar"><p><b>Each bank / cash account: live ledger vs register ledger</b></p></div>
            <div class="ak-dt-scroll" tabindex="0">
                <table class="ak-dt">
                    <caption class="sr-only">Live ledger and register ledger of every bank account</caption>
                    <thead><tr><th scope="col">Account</th><th scope="col">Live ledger (used by settlements, payments …)</th><th scope="col">Register ledger (used only here)</th></tr></thead>
                    <tbody>
                        @foreach ($bankAccounts as $bank)
                            <tr>
                                <td><b>{{ $bank->account_name }}</b> <span class="ak-muted">{{ $bank->account_number }}</span>@unless ($bank->is_active) <span class="ak-status ak-status-slate">Inactive</span>@endunless</td>
                                <td>{{ $bank->chartOfAccount ? $bank->chartOfAccount->account_code.' — '.$bank->chartOfAccount->account_name : '—' }}</td>
                                <td>{{ $bank->registerLedger ? $bank->registerLedger->account_code.' — '.$bank->registerLedger->account_name : 'created on first posting' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>

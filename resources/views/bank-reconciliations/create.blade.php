<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Start reconciliation" sub="Compare the bank statement with the register for a period, then lock the period." :crumbs="['Reconciliation' => route('bank-reconciliations.index'), 'Start' => null]">
            <a href="{{ route('bank-reconciliations.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page" style="max-width:48rem">
        <x-status-message />
        <x-validation-errors />
        <form method="POST" action="{{ route('bank-reconciliations.store') }}" class="bk-stack">
            @csrf
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Statement</h2></div>
                <div class="uf-body"><div class="uf-fields">
                    <div class="uf-field" style="grid-column:1/-1"><label for="bank_account_id">Bank account <span class="uf-req">*</span></label>
                        <select data-allow-clear="false" id="bank_account_id" name="bank_account_id" class="select2 bank-select" required>
                            @foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected((int) old('bank_account_id') === $account->id)>{{ $account->account_name }} ({{ $account->account_number }})</option>@endforeach
                        </select></div>
                    <div class="uf-field"><label for="period_from">Period from <span class="uf-req">*</span></label><input id="period_from" name="period_from" type="date" required value="{{ old('period_from') }}"></div>
                    <div class="uf-field"><label for="period_to">Period to <span class="uf-req">*</span></label><input id="period_to" name="period_to" type="date" required value="{{ old('period_to') }}"></div>
                    <div class="uf-field"><label for="opening_balance">Statement opening balance <span class="uf-req">*</span></label><input id="opening_balance" name="opening_balance" type="number" step="0.01" required value="{{ old('opening_balance', 0) }}"></div>
                    <div class="uf-field"><label for="statement_balance">Statement closing balance <span class="uf-req">*</span></label><input id="statement_balance" name="statement_balance" type="number" step="0.01" required value="{{ old('statement_balance') }}"></div>
                    <div class="uf-field" style="grid-column:1/-1"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" dir="auto" style="width:100%; padding:10px 12px; border:1px solid var(--ak-border-strong); border-radius:8px; font-size:14px">{{ old('notes') }}</textarea></div>
                </div>
                <p class="ak-muted" style="margin:12px 0 0">Lines already posted from the register are matched automatically. Anything left over is matched by hand on the next screen. When every line is matched and the difference is zero, the period is locked.</p></div>
            </section>
            <div class="bk-row" style="justify-content:space-between"><a href="{{ route('bank-reconciliations.index') }}" class="ak-btn ak-btn-ghost">Cancel</a><button class="ak-btn ak-btn-primary">Start</button></div>
        </form>
    </div>
</x-app-layout>

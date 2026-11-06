<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Import bank statement" sub="Step 1 of 3: upload the file. You check a summary before anything is imported." :crumbs="['Statement imports' => route('bank-statement-imports.index'), 'Import' => null]">
            <a href="{{ route('bank-statement-imports.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <x-status-message />
        <x-validation-errors />

        <form method="POST" action="{{ route('bank-statement-imports.store') }}" enctype="multipart/form-data" class="bk-stack">
            @csrf
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">1. Statement file</h2></div>
                <div class="uf-body"><div class="uf-fields">
                    <div class="uf-field"><label for="bank_account_id">Bank account <span class="uf-req">*</span></label>
                        <select data-allow-clear="false" id="bank_account_id" name="bank_account_id" class="select2 bank-select" required>
                            @foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected((int) old('bank_account_id') === $account->id)>{{ $account->account_name }} ({{ $account->account_number }})</option>@endforeach
                        </select></div>
                    <div class="uf-field"><label for="file">Statement file (Excel or CSV) <span class="uf-req">*</span></label><input id="file" name="file" type="file" accept=".xlsx,.xls,.csv" required style="height:auto; padding:8px"></div>
                </div>
                <p class="ak-muted" style="margin:12px 0 0">The file needs a header row with <b>Posting date</b> and <b>Debit</b> / <b>Credit</b> columns. Other columns (reference, narration, party names, value date, balance) are picked up when present.</p></div>
            </section>

            <section class="uf-card">
                <div class="uf-card-head"><div><h2 class="uf-card-title">2. Figures from the printed statement <span class="ak-muted" style="font-weight:500">(recommended)</span></h2>
                    <p class="uf-card-sub">The system adds up the file and compares it with these. If they differ, the import is blocked so nothing is lost or counted twice.</p></div></div>
                <div class="uf-body"><div class="uf-fields">
                    <div class="uf-field"><label for="opening_balance">Opening balance</label><input id="opening_balance" name="opening_balance" type="number" step="0.01" value="{{ old('opening_balance') }}"></div>
                    <div class="uf-field"><label for="closing_balance">Closing balance</label><input id="closing_balance" name="closing_balance" type="number" step="0.01" value="{{ old('closing_balance') }}"></div>
                    <div class="uf-field"><label for="control_total_debit">Total debits (withdrawals)</label><input id="control_total_debit" name="control_total_debit" type="number" step="0.01" min="0" value="{{ old('control_total_debit') }}"></div>
                    <div class="uf-field"><label for="control_total_credit">Total credits (deposits)</label><input id="control_total_credit" name="control_total_credit" type="number" step="0.01" min="0" value="{{ old('control_total_credit') }}"></div>
                </div></div>
            </section>

            <div class="bk-row" style="justify-content:space-between">
                <a href="{{ route('bank-statement-imports.index') }}" class="ak-btn ak-btn-ghost">Cancel</a>
                <button class="ak-btn ak-btn-primary">Read file &amp; show summary</button>
            </div>
        </form>
    </div>
</x-app-layout>

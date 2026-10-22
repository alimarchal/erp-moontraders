<x-app-layout>
    <x-slot name="header">
        <x-bank.head :title="$entry->import_id === null ? 'Edit entry #'.$entry->id : 'Classify entry #'.$entry->id" sub="Company, category and notes. Imported lines keep exactly what the bank printed." :crumbs="['Transaction #'.$entry->id => route('bank-statement-entries.show', $entry), 'Edit' => null]">
            <a href="{{ route('bank-statement-entries.show', $entry) }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <x-status-message />
        <x-validation-errors />

        @if ($entry->import_id === null)
            <div class="ak-alert ak-alert-warn" role="status">This entry was typed in by hand, so its date, amount and details can still be corrected until it is posted.</div>
        @else
            <div class="bk-box">
                <p class="bk-amount {{ $entry->direction === 'credit' ? 'bk-in' : 'bk-out' }}">{{ $entry->direction === 'credit' ? '+' : '−' }} PKR {{ number_format($entry->amount, 2) }}</p>
                <p class="ak-muted">{{ $entry->posting_date->format('d-m-Y') }} · {{ $entry->reference }}</p>
                <p dir="auto">{{ $entry->narration }}</p>
            </div>
        @endif

        @if ($entry->status === 'pending_approval')
            <div class="ak-alert ak-alert-warn" role="status">This entry is waiting for approval. Changing the company or category withdraws it and it has to be submitted again.</div>
        @endif

        <form method="POST" action="{{ route('bank-statement-entries.update', $entry) }}" class="bk-stack">
            @csrf @method('PUT')

            @if ($entry->import_id === null)
                <section class="uf-card">
                    <div class="uf-card-head"><h2 class="uf-card-title">Money and date</h2></div>
                    <div class="uf-body"><div class="uf-fields">
                        <div class="uf-field"><label for="direction">Money</label>
                            <select data-allow-clear="false" id="direction" name="direction" class="select2 bank-select">
                                <option value="credit" @selected(old('direction', $entry->direction) === 'credit')>Received</option>
                                <option value="debit" @selected(old('direction', $entry->direction) === 'debit')>Paid out</option>
                            </select></div>
                        <div class="uf-field"><label for="amount">Amount (PKR) <span class="uf-req">*</span></label><input id="amount" name="amount" type="number" step="0.01" min="0.01" required value="{{ old('amount', $entry->amount) }}"></div>
                        <div class="uf-field"><label for="posting_date">Date <span class="uf-req">*</span></label><input id="posting_date" name="posting_date" type="date" required value="{{ old('posting_date', $entry->posting_date->format('Y-m-d')) }}"></div>
                        <div class="uf-field"><label for="value_date">Value date</label><input id="value_date" name="value_date" type="date" value="{{ old('value_date', $entry->value_date?->format('Y-m-d')) }}"></div>
                        <div class="uf-field"><label for="party_name_in_statement">Party name</label><input id="party_name_in_statement" name="party_name_in_statement" type="text" dir="auto" value="{{ old('party_name_in_statement', $entry->party_name_in_statement) }}"></div>
                        <div class="uf-field"><label for="reference">Reference / cheque no.</label><input id="reference" name="reference" type="text" value="{{ old('reference', $entry->reference) }}"></div>
                        <div class="uf-field" style="grid-column:1/-1"><label for="narration">Narration</label><input id="narration" name="narration" type="text" dir="auto" value="{{ old('narration', $entry->narration) }}"></div>
                    </div></div>
                </section>
            @endif

            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Company and category</h2></div>
                <div class="uf-body"><div class="uf-fields">
                    <div class="uf-field"><label for="company_id">Company / principal</label>
                        <select data-placeholder="—" id="company_id" name="company_id" class="select2 bank-select">
                            <option value="">—</option>
                            @foreach ($companies as $company)<option value="{{ $company->id }}" @selected((int) old('company_id', $entry->company_id) === $company->id)>{{ $company->supplier_name }}</option>@endforeach
                        </select></div>
                    <div class="uf-field"><label for="bank_entry_category_id">Category</label>
                        <select data-placeholder="—" id="bank_entry_category_id" name="bank_entry_category_id" class="select2 bank-select">
                            <option value="">—</option>
                            @foreach ($categories->filter(fn ($c) => in_array($c->type, ['both', $entry->direction === 'credit' ? 'receipt' : 'payment'], true)) as $category)
                                <option value="{{ $category->id }}" @selected((int) old('bank_entry_category_id', $entry->bank_entry_category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select></div>
                    <div class="uf-field" style="grid-column:1/-1"><label for="description">Note</label>
                        <textarea id="description" name="description" rows="3" dir="auto" placeholder="Anything the auditor should know about this entry" style="width:100%; padding:10px 12px; border:1px solid var(--ak-border-strong); border-radius:8px; font-size:14px">{{ old('description', $entry->description) }}</textarea></div>
                    <label style="display:inline-flex; gap:8px; align-items:center"><input type="hidden" name="needs_review" value="0"><input type="checkbox" name="needs_review" value="1" @checked(old('needs_review', $entry->needs_review))> Needs review</label>
                </div></div>
            </section>

            <div class="bk-row" style="justify-content:space-between">
                <a href="{{ route('bank-statement-entries.show', $entry) }}" class="ak-btn ak-btn-ghost">Cancel</a>
                <button class="ak-btn ak-btn-primary">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>

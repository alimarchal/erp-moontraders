@php
    $categoryList = $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type])->values();
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Add entry" sub="Type in a bank, cash or wallet line the day it happens. The receipt can follow later." :crumbs="['Add entry' => null]">
            <a href="{{ route('bank-statement-entries.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <x-status-message />
        <x-validation-errors />

        @if ($scan)
            <div class="bk-box bk-row" style="gap:16px">
                @if ($scan->isImage())<a href="{{ route('bank-receipt-scans.file', $scan) }}" target="_blank"><img src="{{ route('bank-receipt-scans.file', $scan) }}" alt="" style="height:80px; width:112px; object-fit:cover; border:1px solid var(--ak-border); border-radius:6px"></a>@endif
                <div>
                    <p><b>Adding an entry for the scan “{{ $scan->file_name }}”</b></p>
                    <p class="ak-muted">It will be attached to the entry as its receipt when you save. <a href="{{ route('bank-receipt-scans.file', $scan) }}" target="_blank">Open the scan</a></p>
                </div>
            </div>
        @endif

        @if (session('possible_duplicates'))
            <div class="ak-alert ak-alert-warn" role="alert">
                <p><b>An entry with the same account, date and amount is already in the register:</b></p>
                <ul style="margin:6px 0 0; padding-left:18px">
                    @foreach (session('possible_duplicates') as $duplicate)
                        <li><a href="{{ route('bank-statement-entries.show', $duplicate['id']) }}" data-entry="{{ route('bank-statement-entries.drawer', $duplicate['id']) }}">{{ $duplicate['text'] }}</a></li>
                    @endforeach
                </ul>
                <label style="display:inline-flex; gap:8px; margin-top:8px"><input type="checkbox" form="entry-form" name="confirm_duplicate" value="1"> <span>It is a different transaction: save it anyway</span></label>
            </div>
        @endif

        @php
            $moreOpen = filled(old('reference')) || filled(old('narration')) || filled(old('value_date')) || filled($defaults['description']) || filled(old('attachment_description'));
        @endphp
        <form id="entry-form" method="POST" action="{{ route('bank-statement-entries.store') }}" enctype="multipart/form-data" class="bk-stack"
            x-data="{
                busy: false,
                direction: '{{ $defaults['direction'] === 'debit' ? 'debit' : 'credit' }}',
                categories: @js($categoryList),
                category: '{{ old('bank_entry_category_id') }}',
                get options() { return this.categories.filter(c => c.type === 'both' || c.type === (this.direction === 'credit' ? 'receipt' : 'payment')); },
                pick(d) { this.direction = d; if (! this.options.some(c => String(c.id) === String(this.category))) { this.category = ''; } }
            }" @submit="if (busy) { $event.preventDefault(); return; } busy = true">
            @csrf
            <input type="hidden" name="direction" :value="direction">
            {{-- The button that was pressed writes its choice here. The buttons themselves are never disabled: a disabled
                 button is left out of the form, which is how "Save & submit" used to arrive as a plain "Save". --}}
            <input type="hidden" name="after" value="save" x-ref="after">
            @if ($scan)<input type="hidden" name="scan_id" value="{{ $scan->id }}">@endif

            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Entry</h2></div>
                <div class="uf-body">
                    <div class="uf-fields">
                        <div class="uf-field">
                            <label>Received or paid out</label>
                            <div class="ak-seg" role="group" aria-label="Direction of the money">
                                <button type="button" @click="pick('credit')" :class="direction === 'credit' && 'is-on'" :aria-pressed="direction === 'credit'">＋ Received</button>
                                <button type="button" @click="pick('debit')" :class="direction === 'debit' && 'is-on'" :aria-pressed="direction === 'debit'">− Paid out</button>
                            </div>
                        </div>
                        <div class="uf-field">
                            <label for="amount">Amount (PKR) <span class="uf-req">*</span></label>
                            <input id="amount" name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" required autofocus value="{{ $defaults['amount'] }}" placeholder="0.00" style="font-size:20px; font-weight:700">
                        </div>
                        <div class="uf-field">
                            <label for="bank_account_id">Bank / cash account <span class="uf-req">*</span></label>
                            <select data-allow-clear="false" id="bank_account_id" name="bank_account_id" class="select2 bank-select" required>
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((int) $defaults['bank_account_id'] === $account->id)>{{ $account->account_name }} ({{ $account->account_number }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="uf-field">
                            <label for="posting_date">Date <span class="uf-req">*</span></label>
                            <input id="posting_date" name="posting_date" type="date" max="{{ now()->toDateString() }}" required value="{{ $defaults['posting_date'] }}">
                        </div>
                        <div class="uf-field">
                            <label for="party_name_in_statement">Party name</label>
                            <input id="party_name_in_statement" name="party_name_in_statement" type="text" dir="auto" maxlength="191" value="{{ old('party_name_in_statement') }}" placeholder="Who paid, or who was paid">
                        </div>
                        <div class="uf-field">
                            <label for="bank_entry_category_id">Category</label>
                            <select data-placeholder="— decide later —" id="bank_entry_category_id" name="bank_entry_category_id" x-model="category" class="select2 bank-select">
                                <option value="">— decide later —</option>
                                <template x-for="option in options" :key="option.id">
                                    <option :value="option.id" x-text="option.name" :selected="String(option.id) === String(category)"></option>
                                </template>
                            </select>
                            <p class="ak-muted" style="margin:4px 0 0">Needed to post the entry.</p>
                        </div>
                        <div class="uf-field">
                            <label for="company_id">Company / principal</label>
                            <select data-placeholder="—" id="company_id" name="company_id" class="select2 bank-select">
                                <option value="">—</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected((int) old('company_id') === $company->id)>{{ $company->supplier_name }}</option>
                                @endforeach
                            </select>
                            <p class="ak-muted" style="margin:4px 0 0">Filled in automatically when the party name is already known.</p>
                        </div>
                        @can('bank-statement-entry-attach')
                            @unless ($scan)
                                <div class="uf-field">
                                    <label for="files">Receipt, slip or cheque copy <span class="ak-muted" style="font-weight:500">(can be added later)</span></label>
                                    <input id="files" type="file" name="files[]" data-compress multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" style="height:auto; padding:8px">
                                </div>
                            @endunless
                        @endcan
                    </div>
                </div>
            </section>

            <details class="uf-card bk-more" @if ($moreOpen) open @endif>
                <summary>More details <span class="ak-muted" style="font-weight:500">(reference, narration, note, value date)</span></summary>
                <div class="uf-body">
                    <div class="uf-fields">
                        <div class="uf-field">
                            <label for="reference">Reference / cheque no.</label>
                            <input id="reference" name="reference" type="text" maxlength="191" value="{{ old('reference') }}" placeholder="CHQ Cr. Online 00000264">
                        </div>
                        <div class="uf-field">
                            <label for="value_date">Value date</label>
                            <input id="value_date" name="value_date" type="date" value="{{ old('value_date') }}">
                        </div>
                        <div class="uf-field" style="grid-column:1/-1">
                            <label for="narration">Narration</label>
                            <input id="narration" name="narration" type="text" dir="auto" maxlength="2000" value="{{ old('narration') }}" placeholder="As printed on the statement">
                        </div>
                        <div class="uf-field" style="grid-column:1/-1">
                            <label for="description">Note</label>
                            <textarea id="description" name="description" rows="2" dir="auto" placeholder="Anything the auditor should know" style="width:100%; padding:10px 12px; border:1px solid var(--ak-border-strong); border-radius:8px; font-size:14px">{{ $defaults['description'] }}</textarea>
                        </div>
                        @can('bank-statement-entry-attach')
                            @unless ($scan)
                                <div class="uf-field"><label for="label">Kind of file</label>
                                    <select data-allow-clear="false" id="label" name="label" class="select2 bank-select">
                                        @foreach (\App\Models\Attachment::LABELS as $value => $label)<option value="{{ $value }}" @selected($value === 'receipt')>{{ $label }}</option>@endforeach
                                    </select></div>
                                <div class="uf-field"><label for="attachment_description">Description of the file</label><input id="attachment_description" name="attachment_description" maxlength="500" dir="auto" value="{{ old('attachment_description') }}"></div>
                            @endunless
                        @endcan
                    </div>
                </div>
            </details>

            <div class="bk-row" style="justify-content:space-between">
                <a href="{{ route('bank-statement-entries.index') }}" class="ak-btn ak-btn-ghost">Cancel</a>
                <div class="bk-row">
                    <button type="submit" @click="$refs.after.value = 'save'" :class="busy && 'is-busy'" class="ak-btn ak-btn-outline">Save</button>
                    <button type="submit" @click="$refs.after.value = 'another'" :class="busy && 'is-busy'" class="ak-btn ak-btn-primary">Save &amp; add another</button>
                    @can('bank-statement-entry-submit')
                        <button type="submit" @click="$refs.after.value = 'submit'" :class="busy && 'is-busy'" class="ak-btn ak-btn-success" title="Needs a category. Small amounts are posted straight away, large ones go to a checker.">Save &amp; submit</button>
                    @endcan
                </div>
            </div>
        </form>
    </div>
</x-app-layout>

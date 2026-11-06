<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('expense-vendor-payments.index') }}">Vendor payments</a><span aria-hidden="true">›</span><span>New</span></nav>
                <h1 class="ak-title">New vendor payment <span class="ev-ur" lang="ur" dir="rtl">شاپ کو ادائیگی</span></h1>
                <p class="ak-sub">Pick the shop, tick the vouchers you are paying, and send it to a checker.</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('expense-vendor-payments.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')
    <style>
        .vp-table { width:100%; border-collapse:collapse; font-size:13.5px; } .vp-table th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--ak-muted); padding:8px; border-bottom:1px solid var(--ak-border); } .vp-table td { padding:8px; border-bottom:1px solid var(--ak-line); } .vp-table .n { text-align:right; font-variant-numeric:tabular-nums; }
        .vp-table input[type=number] { width:130px; height:34px; border:1px solid var(--ak-border); border-radius:6px; padding:0 8px; text-align:right; } .vp-table tr.is-off { opacity:.45; }
        .vp-total { position:sticky; bottom:0; background:#fff; border-top:2px solid var(--ak-border); padding:12px 16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
    </style>
    <div class="ak-page" x-data="vendorPayment()" x-init="init()">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('expense-vendor-payments.store') }}" class="ak-card ev-form">
            @csrf
            <div class="ev-form-grid">
                <div class="ak-field ev-span-2">
                    <label for="expense_vendor_id">Shop</label>
                    <select id="expense_vendor_id" name="expense_vendor_id" data-search-url="{{ route('expense-vendors.search', ['owing' => 1]) }}" data-placeholder="Type to search a shop we owe money to" required>
                        @if($vendor)<option value="{{ $vendor->id }}" data-uuid="{{ $vendor->uuid }}" selected>{{ $vendor->name }}{{ $vendor->city ? ' - '.$vendor->city : '' }}</option>@endif
                    </select>
                </div>
                <div class="ak-field"><label for="payment_date">Payment date</label><input id="payment_date" type="date" name="payment_date" required max="{{ now()->toDateString() }}" value="{{ old('payment_date', now()->toDateString()) }}"></div>
                <div class="ak-field">
                    <label>Paid by</label>
                    <div class="ev-seg"><label :class="{ 'is-on': method === 'cash' }"><input type="radio" name="method" value="cash" x-model="method"> Cash</label><label :class="{ 'is-on': method === 'bank' }"><input type="radio" name="method" value="bank" x-model="method"> Bank</label></div>
                </div>
                <div class="ak-field" x-show="method === 'bank'" x-cloak>
                    <label for="bank_account_id">Bank account</label>
                    <select id="bank_account_id" name="bank_account_id" :required="method === 'bank'">
                        <option value="">Select account</option>
                        @foreach($bankAccounts as $bank)<option value="{{ $bank->id }}" @selected((int) old('bank_account_id') === $bank->id)>{{ $bank->bank_name }} - {{ $bank->account_number }}</option>@endforeach
                    </select>
                </div>
                <div class="ak-field"><label for="reference">Cheque / transfer no. (optional)</label><input id="reference" type="text" name="reference" maxlength="120" value="{{ old('reference') }}"></div>
                <div class="ak-field ev-span-3"><label for="narration">Narration</label><textarea id="narration" name="narration" rows="2" maxlength="1000">{{ old('narration') }}</textarea></div>
            </div>

            <div style="margin-top:20px">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap">
                    <h2 style="margin:0; font-size:15px; font-weight:700">Credit vouchers still owed</h2>
                    <button type="button" class="ak-btn ak-btn-outline" x-show="rows.length" x-cloak @click="payAll()">Pay everything shown</button>
                </div>
                <p class="ak-hint" x-show="! shopId">Choose a shop to see what we owe it.</p>
                <p class="ak-hint" x-show="shopId && loading">Loading…</p>
                <p class="ak-hint" x-show="shopId && ! loading && ! rows.length" x-cloak>This shop has no unpaid credit vouchers that you can see.</p>
                <p class="ev-note" x-show="ownerLocked" x-cloak>One payment settles one company's vouchers. Vouchers of other companies are greyed out; pay them in a separate payment.</p>
                <div style="overflow-x:auto" x-show="rows.length" x-cloak>
                    <table class="vp-table">
                        <thead><tr><th></th><th>Voucher</th><th>Date</th><th>Company</th><th>Bill</th><th class="n">Voucher amount</th><th class="n">Still owed</th><th class="n">Pay now</th></tr></thead>
                        <tbody>
                            <template x-for="row in rows" :key="row.id">
                                <tr :class="{ 'is-off': isOff(row) }">
                                    <td><input type="checkbox" :checked="row.pay > 0" :disabled="isOff(row)" @change="toggle(row, $event.target.checked)"></td>
                                    <td><a :href="'{{ url('expense-vouchers') }}/' + row.uuid" target="_blank" x-text="row.number"></a></td>
                                    <td x-text="row.date"></td><td x-text="row.owner"></td><td x-text="row.bill || '—'"></td>
                                    <td class="n" x-text="fmt(row.amount)"></td><td class="n" x-text="fmt(row.outstanding)"></td>
                                    <td class="n"><input type="number" step="0.01" min="0" :max="row.outstanding" :name="row.pay > 0 ? 'amounts[' + row.id + ']' : null" x-model.number="row.pay" :disabled="isOff(row)" @input="clamp(row)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="vp-total">
                <div>Total to pay: <b style="font-size:20px" x-text="'Rs ' + fmt(total())"></b></div>
                <div style="display:flex; gap:8px"><a href="{{ route('expense-vendor-payments.index') }}" class="ak-btn ak-btn-outline">Cancel</a><button type="submit" class="ak-btn ak-btn-primary" :disabled="total() <= 0">Send for approval</button></div>
            </div>
        </form>
    </div>
    @push('scripts')
        <script>
            function vendorPayment() {
                return {
                    method: @js(old('method', 'cash')), shopId: @js($vendor?->id), shopUuid: @js($vendor?->uuid), rows: [], loading: false,
                    init() {
                        const $shop = $('#expense_vendor_id');
                        $shop.select2({ width: '100%', allowClear: true, placeholder: $shop.data('placeholder'), ajax: { url: $shop.data('search-url'), dataType: 'json', delay: 250, data: (p) => ({ q: p.term || '' }), processResults: (d) => d } });
                        $shop.on('select2:select', (event) => { this.shopId = event.params.data.id; this.shopUuid = event.params.data.uuid; this.load(); });
                        $shop.on('select2:clear', () => { this.shopId = null; this.shopUuid = null; this.rows = []; });
                        if (this.shopId) { this.load(); }
                    },
                    async load() {
                        this.rows = [];
                        if (! this.shopId) { return; }
                        this.loading = true;
                        const response = await fetch(@js(url('expense-vendor-payments/vendors')) + '/' + this.shopUuid + '/vouchers', { headers: { 'Accept': 'application/json' } });
                        this.rows = (await response.json()).vouchers.map(v => ({ ...v, pay: 0 }));
                        this.loading = false;
                    },
                    get owner() { const first = this.rows.find(r => r.pay > 0); return first ? first.owner_key : null; },
                    get ownerLocked() { return this.owner !== null && this.rows.some(r => r.owner_key !== this.owner); },
                    isOff(row) { return this.owner !== null && row.owner_key !== this.owner; },
                    toggle(row, on) { row.pay = on ? row.outstanding : 0; },
                    clamp(row) { if (row.pay > row.outstanding) { row.pay = row.outstanding; } if (row.pay < 0 || ! row.pay) { row.pay = 0; } },
                    payAll() { const owner = this.owner ?? this.rows[0]?.owner_key; this.rows.forEach(r => r.pay = r.owner_key === owner ? r.outstanding : 0); },
                    total() { return this.rows.reduce((sum, r) => sum + (Number(r.pay) || 0), 0); },
                    fmt(value) { return Number(value || 0).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                };
            }
        </script>
    @endpush
</x-app-layout>

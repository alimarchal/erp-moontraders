@php
    $currency = $supplier->defaultCurrency;
    $bankAccount = $supplier->defaultBankAccount;
    $dash = fn ($v) => filled($v) ? $v : '—';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('suppliers.index') }}">Suppliers</a><span aria-hidden="true">›</span><span>{{ $supplier->supplier_name }}</span>
                </nav>
                <h1 class="ak-title" style="margin:0">{{ $supplier->supplier_name }}</h1>
                <p class="ak-sub">{{ $dash($supplier->supplier_group) }} &middot; {{ $dash($supplier->country) }} &middot;
                    <span class="ak-status {{ $supplier->disabled ? 'ak-status-slate' : 'ak-status-green' }}"><i aria-hidden="true"></i>{{ $supplier->disabled ? 'Disabled' : 'Active' }}</span></p>
            </div>
            <div class="ak-head-actions no-print">
                <a href="{{ route('suppliers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to suppliers</a>
                <a href="{{ route('suppliers.help') }}" class="ak-btn ak-btn-outline">How it works <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:16px">طریقہ کار</span></a>
                @can('supplier-edit')
                    <a href="{{ route('suppliers.edit', $supplier) }}" class="ak-btn ak-btn-primary">Edit <span class="ev-ur" lang="ur" dir="rtl" style="color:#fff; font-size:15px">ترمیم</span></a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .ev-ur { font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Geeza Pro', serif; font-size: 14px; font-weight: 600; color: var(--ak-navy); text-transform: none; letter-spacing: 0; margin-inline-start: 8px; }
        .ev-grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
        @media (min-width: 900px) { .ev-grid { grid-template-columns: 3fr 2fr; } }
        .ev-dl { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; margin: 0; }
        .ev-dl dt { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); }
        .ev-dl dd { margin: 2px 0 0; font-weight: 600; word-break: break-word; }
        .ev-pad { padding: 18px 20px; }
        .sp-stat { font-size: 28px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .sp-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .sp-pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #f1f5f9; color: #475569; margin-right: 4px; }
        .sp-pill-amber { background: #fef3c7; color: #b45309; } .sp-pill-blue { background: #dbeafe; color: #1d4ed8; } .sp-pill-purple { background: #ede9fe; color: #6d28d9; }
        .ak-status-slate { background: #f1f5f9; color: #475569; } .ak-status-slate i { background: #64748b; }
    </style>

    <div class="ak-page">
        <x-status-message />
        <div class="ev-grid">
            <section class="ak-card ev-pad" aria-label="Supplier details">
                <dl class="ev-dl">
                    <div><dt>Supplier name <span class="ev-ur" lang="ur" dir="rtl">سپلائر کا نام</span></dt><dd>{{ $supplier->supplier_name }}</dd></div>
                    <div><dt>Short name <span class="ev-ur" lang="ur" dir="rtl">مختصر نام</span></dt><dd style="text-transform:uppercase">{{ $dash($supplier->short_name) }}</dd></div>
                    <div><dt>Group <span class="ev-ur" lang="ur" dir="rtl">گروپ</span></dt><dd>{{ $dash($supplier->supplier_group) }}</dd></div>
                    <div><dt>Type <span class="ev-ur" lang="ur" dir="rtl">قسم</span></dt><dd>{{ $dash($supplier->supplier_type) }}</dd></div>
                    <div><dt>Country <span class="ev-ur" lang="ur" dir="rtl">ملک</span></dt><dd>{{ $dash($supplier->country) }}</dd></div>
                    <div><dt>Default price list <span class="ev-ur" lang="ur" dir="rtl">پرائس لسٹ</span></dt><dd>{{ $dash($supplier->default_price_list) }}</dd></div>
                    <div><dt>Default currency <span class="ev-ur" lang="ur" dir="rtl">کرنسی</span></dt><dd>{{ $currency ? $currency->currency_code.' · '.$currency->currency_name : '—' }}</dd></div>
                    <div><dt>Default bank account <span class="ev-ur" lang="ur" dir="rtl">بینک اکاؤنٹ</span></dt><dd>{{ $bankAccount ? $bankAccount->account_code.' · '.$bankAccount->account_name : '—' }}</dd></div>
                    <div><dt>Tax ID / NTN <span class="ev-ur" lang="ur" dir="rtl">ٹیکس آئی ڈی</span></dt><dd>{{ $dash($supplier->tax_id) }}</dd></div>
                    <div><dt>PAN number <span class="ev-ur" lang="ur" dir="rtl">پین نمبر</span></dt><dd>{{ $dash($supplier->pan_number) }}</dd></div>
                    <div><dt>Website <span class="ev-ur" lang="ur" dir="rtl">ویب سائٹ</span></dt><dd>{{ $dash($supplier->website) }}</dd></div>
                    <div><dt>Print language <span class="ev-ur" lang="ur" dir="rtl">پرنٹ کی زبان</span></dt><dd>{{ $dash($supplier->print_language) }}</dd></div>
                    <div style="grid-column: 1 / -1"><dt>Primary address <span class="ev-ur" lang="ur" dir="rtl">پتہ</span></dt><dd>{{ $dash($supplier->supplier_primary_address) }}</dd></div>
                    <div style="grid-column: 1 / -1"><dt>Primary contact <span class="ev-ur" lang="ur" dir="rtl">رابطہ</span></dt><dd>{{ $dash($supplier->supplier_primary_contact) }}</dd></div>
                    <div style="grid-column: 1 / -1"><dt>Additional details <span class="ev-ur" lang="ur" dir="rtl">اضافی تفصیل</span></dt><dd>{{ $dash($supplier->supplier_details) }}</dd></div>
                    <div><dt>Created <span class="ev-ur" lang="ur" dir="rtl">بنایا گیا</span></dt><dd>{{ $supplier->created_at?->format('d M Y h:i A') ?? '—' }}</dd></div>
                    <div><dt>Last updated <span class="ev-ur" lang="ur" dir="rtl">آخری تبدیلی</span></dt><dd>{{ $supplier->updated_at?->format('d M Y h:i A') ?? '—' }}</dd></div>
                </dl>
            </section>

            <div style="display:flex; flex-direction:column; gap:16px">
                <section class="ak-card ev-pad" aria-label="GRN entry">
                    <h2 style="margin:0 0 6px; font-size:15px; font-weight:700">GRN quantity entry <span class="ev-ur" lang="ur" dir="rtl">جی آر این اندراج</span></h2>
                    <p style="margin:0 0 10px"><span class="sp-pill {{ $supplier->usesCartonEntry() ? 'sp-pill-amber' : '' }}">{{ $supplier->usesCartonEntry() ? 'Carton' : 'Pieces' }}</span></p>
                    <p class="ak-muted" style="margin:0; font-size:13px">{{ $supplier->usesCartonEntry() ? 'GRNs take cartons + loose pieces and a rate per TH. Stock is saved in pieces.' : 'GRNs take quantity directly, as always.' }}</p>
                </section>
                <section class="ak-card ev-pad" aria-label="Usage">
                    <div class="sp-stats">
                        <div><div class="ak-muted" style="font-size:12px; text-transform:uppercase">Products</div><div class="sp-stat">{{ number_format($productCount) }}</div></div>
                        <div><div class="ak-muted" style="font-size:12px; text-transform:uppercase">GRNs</div><div class="sp-stat">{{ number_format($grnCount) }}</div></div>
                    </div>
                </section>
                <section class="ak-card ev-pad" aria-label="Flags">
                    <h2 style="margin:0 0 8px; font-size:15px; font-weight:700">Flags <span class="ev-ur" lang="ur" dir="rtl">نشانات</span></h2>
                    <span class="sp-pill {{ $supplier->is_transporter ? 'sp-pill-blue' : '' }}">Transporter: {{ $supplier->is_transporter ? 'Yes' : 'No' }}</span>
                    <span class="sp-pill {{ $supplier->is_internal_supplier ? 'sp-pill-purple' : '' }}">Internal: {{ $supplier->is_internal_supplier ? 'Yes' : 'No' }}</span>
                    <span class="sp-pill {{ $supplier->is_fmr_allowed ? 'sp-pill-amber' : '' }}">FMR allowed: {{ $supplier->is_fmr_allowed ? 'Yes' : 'No' }}</span>
                    <span class="sp-pill {{ $supplier->is_advance_tax_income ? 'sp-pill-amber' : '' }}">Adv. tax income: {{ $supplier->is_advance_tax_income ? 'Yes' : 'No' }}</span>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>

{{--
    Suppliers -> How it works / طریقہ کار (/settings/suppliers/help): English and Urdu side by side.
--}}
@php
    $sections = [
        [
            'id' => 'sp-what', 'en_h' => '1. What a supplier controls', 'ur_h' => '۱۔ سپلائر کیا کنٹرول کرتا ہے',
            'en' => '<ul><li>Which products belong to the company and who can raise GRNs for it.</li><li>Defaults: currency, bank account, price list, tax IDs.</li><li><b>Quantity entry</b>: how quantities are typed on a GRN (Pieces or Carton).</li><li>Flags: transporter, internal supplier, FMR allowed, advance tax income.</li><li><b>Disabled</b> suppliers disappear from new GRNs and dropdowns; their old documents stay untouched.</li></ul>',
            'ur' => '<ul><li>کون سی مصنوعات کمپنی کی ہیں اور کون اس کی جی آر این بنا سکتا ہے۔</li><li>ڈیفالٹس: کرنسی، بینک اکاؤنٹ، پرائس لسٹ، ٹیکس آئی ڈی۔</li><li><b>مقدار کا اندراج</b>: جی آر این میں مقدار کیسے لکھی جائے (پیسز یا کارٹن)۔</li><li>نشانات: ٹرانسپورٹر، اندرونی سپلائر، ایف ایم آر کی اجازت، ایڈوانس ٹیکس آمدنی۔</li><li><b>غیر فعال</b> سپلائر نئی جی آر این اور ڈراپ ڈاؤن میں نظر نہیں آتا؛ پرانے کاغذات جیسے ہیں ویسے رہتے ہیں۔</li></ul>',
        ],
        [
            'id' => 'sp-carton', 'en_h' => '2. Carton entry for Pakistan Tobacco Company', 'ur_h' => '۲۔ پاکستان ٹوبیکو کمپنی کے لیے کارٹن اندراج',
            'en' => '<ol><li>Open the supplier and set <b>Quantity entry</b> to <b>Carton</b>.</li><li>On every VELO product set <b>Pieces per carton</b> (VELO = 120) and <b>Pouches per piece</b> (15 or 20).</li><li>On a new GRN choose the supplier. Each row now takes <b>Cartons</b>, <b>Loose pieces</b> and <b>Rate per TH</b> exactly as printed on the PTC invoice.</li><li>The row shows <b>pieces | TH | line total</b> at once. Excise, sales tax and advance tax are typed from the invoice into the normal tax columns.</li><li>To turn it off, set Quantity entry back to <b>Pieces</b>. Old GRNs are not changed.</li></ol><div class="gh-note">Example (invoice 200893117, row 1): 2 cartons × 120 = 240 pieces; 240 × 15 ÷ 1000 = 3.600 TH; 3.600 × 10,163.58 = 36,588.89; unit cost = 36,588.89 ÷ 240 per piece.</div>',
            'ur' => '<ol><li>سپلائر کھول کر <b>مقدار کا اندراج</b> کو <b>کارٹن</b> کریں۔</li><li>ہر ویلو پروڈکٹ میں <b>فی کارٹن پیسز</b> (ویلو = 120) اور <b>فی پیس پاؤچ</b> (15 یا 20) بھریں۔</li><li>نئی جی آر این میں سپلائر منتخب کریں۔ اب ہر قطار میں <b>کارٹن</b>، <b>کھلے پیسز</b> اور <b>ریٹ فی TH</b> وہی لکھیں جو PTC کے انوائس پر چھپا ہے۔</li><li>قطار میں فوراً <b>پیسز | TH | لائن ٹوٹل</b> نظر آتا ہے۔ ایکسائز، سیلز ٹیکس اور ایڈوانس ٹیکس انوائس سے عام ٹیکس خانوں میں لکھیں۔</li><li>بند کرنے کے لیے مقدار کا اندراج واپس <b>پیسز</b> کر دیں۔ پرانی جی آر این نہیں بدلتیں۔</li></ol><div class="gh-note">مثال (انوائس 200893117، پہلی قطار): 2 کارٹن × 120 = 240 پیسز؛ 240 × 15 ÷ 1000 = 3.600 TH؛ 3.600 × 10,163.58 = 36,588.89؛ فی پیس لاگت = 36,588.89 ÷ 240۔</div>',
        ],
        [
            'id' => 'sp-stock', 'en_h' => '3. What reaches stock and the inventory ledger', 'ur_h' => '۳۔ اسٹاک اور انوینٹری لیجر تک کیا پہنچتا ہے',
            'en' => '<ul><li>Stock is <b>always in pieces</b>. Carton entry only helps you type; the saved quantity is the piece count.</li><li>Posting a GRN writes <b>exactly one inventory ledger entry per GRN line</b>: quantity in = pieces, running balance per product, value = pieces × unit cost.</li><li>Posting the same GRN twice never adds a second entry. Reversing a GRN writes the opposite entries so the balance returns to what it was.</li><li>Goods Issue and Sales Settlement keep working in pieces; the “1 ctn + 19 pcs” hint is display only.</li></ul>',
            'ur' => '<ul><li>اسٹاک <b>ہمیشہ پیسز میں</b> رہتا ہے۔ کارٹن اندراج صرف لکھنے میں مدد کرتا ہے؛ محفوظ مقدار پیسز کی گنتی ہوتی ہے۔</li><li>جی آر این پوسٹ کرنے پر <b>ہر لائن کی صرف ایک انوینٹری لیجر انٹری</b> بنتی ہے: آمد = پیسز، ہر پروڈکٹ کا رننگ بیلنس، ویلیو = پیسز × فی پیس لاگت۔</li><li>ایک ہی جی آر این دوبارہ پوسٹ کرنے سے دوسری انٹری نہیں بنتی۔ جی آر این ریورس کرنے پر الٹی انٹریاں لکھی جاتی ہیں اور بیلنس پہلے جیسا ہو جاتا ہے۔</li><li>گڈز ایشو اور سیلز سیٹلمنٹ پیسز ہی میں چلتے ہیں؛ "1 ctn + 19 pcs" صرف دکھانے کے لیے ہے۔</li></ul>',
        ],
        [
            'id' => 'sp-errors', 'en_h' => '4. If something is blocked', 'ur_h' => '۴۔ اگر کچھ رک جائے',
            'en' => '<table class="gh-table"><thead><tr><th>Message</th><th>Fix</th></tr></thead><tbody><tr><td>Product needs Pieces per carton / Pouches per piece</td><td>Open the product and fill both fields, then save the GRN again.</td></tr><tr><td>Supplier name already exists</td><td>Names are unique; edit the existing supplier instead.</td></tr><tr><td>Supplier missing in GRN dropdown</td><td>It is disabled, or your user is tied to another supplier.</td></tr></tbody></table>',
            'ur' => '<table class="gh-table"><thead><tr><th>پیغام</th><th>حل</th></tr></thead><tbody><tr><td>پروڈکٹ میں فی کارٹن پیسز / فی پیس پاؤچ درکار ہے</td><td>پروڈکٹ کھول کر دونوں خانے بھریں، پھر جی آر این دوبارہ محفوظ کریں۔</td></tr><tr><td>سپلائر کا نام پہلے سے موجود ہے</td><td>نام منفرد ہوتے ہیں؛ موجود سپلائر میں ترمیم کریں۔</td></tr><tr><td>جی آر این کے ڈراپ ڈاؤن میں سپلائر نہیں</td><td>وہ غیر فعال ہے، یا آپ کا صارف کسی اور سپلائر سے منسلک ہے۔</td></tr></tbody></table>',
        ],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('suppliers.index') }}">Suppliers</a><span aria-hidden="true">›</span><span>How it works</span>
                </nav>
                <h1 class="ak-title">How suppliers work <span class="ev-ur-title" lang="ur" dir="rtl">سپلائرز کیسے کام کرتے ہیں</span></h1>
                <p class="ak-sub">Setup, carton entry for Pakistan Tobacco Company, and what reaches stock. &middot; <span lang="ur" dir="rtl" class="ev-ur-title" style="font-size:15px">سیٹ اپ، پاکستان ٹوبیکو کمپنی کا کارٹن اندراج، اور اسٹاک تک پہنچنے والا ڈیٹا۔</span></p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('suppliers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to suppliers</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .gh-wrap { display: flex; flex-direction: column; gap: 20px; }
        .gh-card p, .gh-card li { font-size: 14px; line-height: 1.55; color: var(--ak-text); }
        .gh-card ul, .gh-card ol { margin: 6px 0 0; padding-left: 22px; }
        .gh-card ul { list-style: disc; } .gh-card ol { list-style: decimal; }
        .gh-card li { margin: 3px 0; }
        .gh-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
        .gh-table th { text-align: left; background: var(--ak-slate-bg); color: var(--ak-muted); font-weight: 600; padding: 7px 10px; border-bottom: 1px solid var(--ak-border); }
        .gh-table td { padding: 7px 10px; border-bottom: 1px solid var(--ak-line); vertical-align: top; }
        .gh-note { margin-top: 10px; padding: 9px 12px; border-left: 3px solid var(--ak-amber); background: var(--ak-amber-bg); border-radius: 6px; font-size: 13px; }
        .gh-rtl { direction: rtl; text-align: right; font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Geeza Pro', serif; }
        .gh-rtl p, .gh-rtl li { font-size: 17px !important; line-height: 2 !important; }
        .gh-rtl ul, .gh-rtl ol { padding-right: 26px !important; padding-left: 0 !important; }
        .gh-rtl .gh-table { font-size: 15.5px; line-height: 1.9; } .gh-rtl .gh-table th, .gh-rtl .gh-table td { text-align: right; }
        .gh-rtl .gh-note { border-left: 0; border-right: 3px solid var(--ak-amber); font-size: 16px; line-height: 1.9; }
        .ev-bihead { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .ev-cols { display: grid; grid-template-columns: 1fr; gap: 28px; }
        @media (min-width: 1100px) { .ev-cols { grid-template-columns: 1fr 1fr; gap: 0; } .ev-en { padding-right: 24px; border-right: 1px solid var(--ak-border); } .ev-ur-col { padding-left: 24px; } }
        .ev-ur-title { font-family: 'Noto Naskh Arabic', 'Geeza Pro', serif; font-weight: 700; color: var(--ak-navy); margin-inline-start: 12px; font-size: 22px; }
        @media print { .gh-card { break-inside: avoid; box-shadow: none; } }
    </style>

    <div class="ak-page">
        <div class="gh-wrap">
            @foreach ($sections as $s)
                <section class="uf-card gh-card" aria-labelledby="{{ $s['id'] }}">
                    <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="{{ $s['id'] }}">{{ $s['en_h'] }}</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">{{ $s['ur_h'] }}</h2></header>
                    <div class="uf-body">
                        <div class="ev-cols">
                            <div class="ev-en" lang="en">{!! $s['en'] !!}</div>
                            <div class="ev-ur-col gh-rtl" dir="rtl" lang="ur">{!! $s['ur'] !!}</div>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>

{{--
    Products -> How it works / طریقہ کار (/settings/products/help): English and Urdu side by side.
--}}
@php
    $sections = [
        [
            'id' => 'pr-what', 'en_h' => '1. What a product holds', 'ur_h' => '۱۔ پروڈکٹ میں کیا ہوتا ہے',
            'en' => '<ul><li><b>Details</b>: code (SKU), name, brand, category and preferred supplier.</li><li><b>Units</b>: base UOM (how stock is counted), sales UOM and conversion factor, valuation method, pack size, weight.</li><li><b>Pricing</b>: cost price, selling price, expiry price, reorder level. A new selling price applies to batches that still have stock.</li><li><b>Status</b>: inactive products stay on record but are hidden from new transactions.</li></ul>',
            'ur' => '<ul><li><b>تفصیل</b>: کوڈ (SKU)، نام، برانڈ، کیٹیگری اور ترجیحی سپلائر۔</li><li><b>یونٹس</b>: بنیادی UOM (اسٹاک کیسے گنا جائے)، سیلز UOM اور کنورژن فیکٹر، ویلیویشن میتھڈ، پیک سائز، وزن۔</li><li><b>قیمتیں</b>: لاگت، فروخت کی قیمت، ایکسپائری قیمت، ری آرڈر لیول۔ نئی فروخت قیمت صرف ان بیچز پر لگتی ہے جن میں اسٹاک موجود ہے۔</li><li><b>حالت</b>: غیر فعال پروڈکٹ ریکارڈ میں رہتی ہے مگر نئی ٹرانزیکشنز میں نظر نہیں آتی۔</li></ul>',
        ],
        [
            'id' => 'pr-add', 'en_h' => '2. Adding and finding products', 'ur_h' => '۲۔ پروڈکٹ شامل کرنا اور تلاش کرنا',
            'en' => '<ol><li>Use <b>Add product</b> for one product, or <b>Import Excel</b> for many (download the template first).</li><li>On the list use the Active / Inactive tabs, the search box and the filter chips; click a column heading to sort.</li><li><b>Export Excel</b> downloads exactly what the filters show.</li><li>Saving an edit first shows a <b>Confirm Update</b> list of every changed field.</li></ol>',
            'ur' => '<ol><li>ایک پروڈکٹ کے لیے <b>Add product</b>، اور بہت سی کے لیے <b>Import Excel</b> استعمال کریں (پہلے ٹیمپلیٹ ڈاؤن لوڈ کریں)۔</li><li>فہرست میں فعال / غیر فعال ٹیبز، سرچ باکس اور فلٹر چپس استعمال کریں؛ ترتیب کے لیے کالم کے عنوان پر کلک کریں۔</li><li><b>Export Excel</b> وہی ڈاؤن لوڈ کرتا ہے جو فلٹر میں نظر آ رہا ہو۔</li><li>ترمیم محفوظ کرنے سے پہلے <b>Confirm Update</b> میں ہر بدلا ہوا خانہ دکھایا جاتا ہے۔</li></ol>',
        ],
        [
            'id' => 'pr-carton', 'en_h' => '3. Carton entry fields (Pakistan Tobacco Company)', 'ur_h' => '۳۔ کارٹن اندراج کے خانے (پاکستان ٹوبیکو کمپنی)',
            'en' => '<ul><li><b>Pieces per carton</b>: how many pieces make one carton (VELO = 120).</li><li><b>Pouches per piece</b>: pouches in one piece (15 or 20). Together they give the TH used for the invoice rate.</li><li>They are used only when the supplier is set to <b>Carton</b> quantity entry. They are separate from <b>Conversion factor</b>, which is the older Case/Box setting.</li><li>If a carton-mode supplier&#39;s product has either field empty, the GRN is <b>blocked</b> with a clear message. Pieces-mode suppliers are never affected.</li></ul><div class="gh-note">Example: 120 pieces per carton and 15 pouches per piece show &ldquo;1 ctn = 120 pcs = 1.800 TH&rdquo; under the fields.</div>',
            'ur' => '<ul><li><b>فی کارٹن پیسز</b>: ایک کارٹن میں کتنے پیسز (ویلو = 120)۔</li><li><b>فی پیس پاؤچ</b>: ایک پیس میں پاؤچ (15 یا 20)۔ دونوں مل کر انوائس ریٹ کے لیے TH بناتے ہیں۔</li><li>یہ صرف تب استعمال ہوتے ہیں جب سپلائر کا اندراج <b>کارٹن</b> ہو۔ یہ <b>کنورژن فیکٹر</b> سے الگ ہیں، جو پرانی کیس/باکس کی ترتیب ہے۔</li><li>اگر کارٹن موڈ والے سپلائر کی پروڈکٹ میں کوئی خانہ خالی ہو تو جی آر این واضح پیغام کے ساتھ <b>رک جاتی ہے</b>۔ پیسز موڈ والے سپلائر پر کوئی اثر نہیں پڑتا۔</li></ul><div class="gh-note">مثال: 120 پیسز فی کارٹن اور 15 پاؤچ فی پیس ہوں تو خانوں کے نیچے "1 ctn = 120 pcs = 1.800 TH" نظر آتا ہے۔</div>',
        ],
        [
            'id' => 'pr-errors', 'en_h' => '4. If something is blocked', 'ur_h' => '۴۔ اگر کچھ رک جائے',
            'en' => '<table class="gh-table"><thead><tr><th>Message</th><th>Fix</th></tr></thead><tbody><tr><td>Product needs Pieces per carton / Pouches per piece</td><td>Edit the product, fill both fields, save the GRN again.</td></tr><tr><td>Product code already exists</td><td>Codes are unique; edit the existing product.</td></tr><tr><td>Product missing in a GRN</td><td>It is inactive or belongs to another supplier.</td></tr></tbody></table>',
            'ur' => '<table class="gh-table"><thead><tr><th>پیغام</th><th>حل</th></tr></thead><tbody><tr><td>پروڈکٹ میں فی کارٹن پیسز / فی پیس پاؤچ درکار ہے</td><td>پروڈکٹ میں ترمیم کر کے دونوں خانے بھریں، پھر جی آر این دوبارہ محفوظ کریں۔</td></tr><tr><td>پروڈکٹ کوڈ پہلے سے موجود ہے</td><td>کوڈ منفرد ہوتے ہیں؛ موجود پروڈکٹ میں ترمیم کریں۔</td></tr><tr><td>جی آر این میں پروڈکٹ نظر نہیں آتی</td><td>وہ غیر فعال ہے یا کسی اور سپلائر کی ہے۔</td></tr></tbody></table>',
        ],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('products.index') }}">Products</a><span aria-hidden="true">›</span><span>How it works</span>
                </nav>
                <h1 class="ak-title">How products work <span class="ev-ur-title" lang="ur" dir="rtl">پروڈکٹس کیسے کام کرتی ہیں</span></h1>
                <p class="ak-sub">Details, units, pricing and the carton entry fields. &middot; <span lang="ur" dir="rtl" class="ev-ur-title" style="font-size:15px">تفصیل، یونٹس، قیمتیں اور کارٹن اندراج کے خانے۔</span></p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('products.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to products</a>
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

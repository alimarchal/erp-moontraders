{{--
    Stock Adjustments -> How it works / طریقہ کار (/stock-adjustments/help): English and Urdu side by side.
    Static page: no data is read.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('stock-adjustments.index') }}">Stock Adjustments</a><span aria-hidden="true">›</span><span>How it works</span>
                </nav>
                <h1 class="ak-title">How stock adjustments work <span class="sa-ur-title" lang="ur" dir="rtl">اسٹاک ایڈجسٹمنٹ کیسے کام کرتی ہے</span></h1>
                <p class="ak-sub">Counts, losses and cost corrections, what each step writes, and how a ticket does the same job. &middot; <span lang="ur" dir="rtl" class="sa-ur-title" style="font-size:15px">گنتی، نقصان اور لاگت کی درستگی، ہر مرحلے پر ڈیٹا بیس میں کیا لکھا جاتا ہے، اور ٹکٹ کے ذریعے وہی کام۔</span></p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('stock-adjustments.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to adjustments</a>
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
        .gh-tip { margin-top: 10px; padding: 9px 12px; border-left: 3px solid var(--ak-green); background: var(--ak-green-bg); border-radius: 6px; font-size: 13px; }
        .gh-scroll { overflow-x: auto; }
        .gh-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; background: var(--ak-slate-bg); padding: 1px 5px; border-radius: 4px; }
        .gh-rtl { direction: rtl; text-align: right; font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Geeza Pro', serif; }
        .gh-rtl .gh-card p, .gh-rtl .gh-card li { font-size: 17px; line-height: 2; }
        .gh-rtl .gh-card ul, .gh-rtl .gh-card ol { padding-right: 26px; padding-left: 0; }
        .gh-rtl .uf-card-title { font-size: 19px; font-weight: 700; }
        .gh-rtl .gh-table { font-size: 15.5px; line-height: 1.9; }
        .gh-rtl .gh-table th, .gh-rtl .gh-table td { text-align: right; }
        .gh-rtl .gh-note, .gh-rtl .gh-tip { border-left: 0; font-size: 16px; line-height: 1.9; }
        .gh-rtl .gh-note { border-right: 3px solid var(--ak-amber); } .gh-rtl .gh-tip { border-right: 3px solid var(--ak-green); }
        .gh-rtl .gh-code, .gh-rtl bdi { direction: ltr; unicode-bidi: isolate; display: inline-block; }
        .sa-bihead { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .sa-cols { display: grid; grid-template-columns: 1fr; gap: 28px; }
        @media (min-width: 1100px) { .sa-cols { grid-template-columns: 1fr 1fr; gap: 0; } .sa-en { padding-right: 24px; border-right: 1px solid var(--ak-border); } .sa-ur-col { padding-left: 24px; } }
        .sa-ur-title { font-family: 'Noto Naskh Arabic', 'Geeza Pro', serif; font-weight: 700; color: var(--ak-navy); margin-inline-start: 12px; font-size: 22px; }
        .fc-box { fill: #fff; stroke: #172554; stroke-width: 1.4; } .fc-box-green { fill: #dcfce7; stroke: #15803d; stroke-width: 1.4; }
        .fc-box-amber { fill: #fef3c7; stroke: #b45309; stroke-width: 1.4; } .fc-box-red { fill: #fee2e2; stroke: #b91c1c; stroke-width: 1.4; }
        .fc-text { font-size: 12.5px; fill: #0f172a; font-family: ui-sans-serif, system-ui, sans-serif; } .fc-bold { font-weight: 700; }
        .fc-small { font-size: 11px; fill: #475569; font-family: ui-sans-serif, system-ui, sans-serif; } .fc-line { stroke: #475569; stroke-width: 1.4; fill: none; }
        @media print { .gh-card { break-inside: avoid; box-shadow: none; } }
    </style>

    <div class="ak-page">
        <div class="gh-wrap">

            <section class="uf-card gh-card" aria-labelledby="sa-flow">
                <header class="uf-card-head sa-bihead"><h2 class="uf-card-title" id="sa-flow">1. The flow</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۱۔ پورا طریقہ کار</h2></header>
                <div class="uf-body">
                    <div class="gh-scroll" style="margin-bottom:14px">
                        <svg viewBox="0 0 880 150" width="100%" role="img" aria-label="Stock adjustment flow" style="min-width:760px">
                            <defs><marker id="sa-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0 L10 5 L0 10 z" fill="#475569"/></marker></defs>
                            <rect class="fc-box" x="10" y="30" width="190" height="70" rx="8"/><text class="fc-text fc-bold" x="105" y="58" text-anchor="middle">Draft</text><text class="fc-small" x="105" y="78" text-anchor="middle">supplier, warehouse, lines</text>
                            <rect class="fc-box-amber" x="250" y="30" width="190" height="70" rx="8"/><text class="fc-text fc-bold" x="345" y="58" text-anchor="middle">Confirm</text><text class="fc-small" x="345" y="78" text-anchor="middle">cost change: tick + password</text>
                            <rect class="fc-box-green" x="490" y="30" width="190" height="70" rx="8"/><text class="fc-text fc-bold" x="585" y="58" text-anchor="middle">Post</text><text class="fc-small" x="585" y="78" text-anchor="middle">stock, ledger, valuation, journal</text>
                            <rect class="fc-box" x="730" y="30" width="140" height="70" rx="8"/><text class="fc-text fc-bold" x="800" y="58" text-anchor="middle">Reports</text><text class="fc-small" x="800" y="78" text-anchor="middle">stock + GL pick it up</text>
                            <path class="fc-line" d="M200 65 H248" marker-end="url(#sa-arrow)"/><path class="fc-line" d="M440 65 H488" marker-end="url(#sa-arrow)"/><path class="fc-line" d="M680 65 H728" marker-end="url(#sa-arrow)"/>
                        </svg>
                    </div>
                    <div class="sa-cols">
                        <div class="sa-en gh-scroll" lang="en">
                            <ol>
                                <li><b>New adjustment</b>: pick the date, company (supplier), warehouse and type (damage, theft, count variance, expiry, recall, other) and write the reason. For each line choose the product and the <b>batch</b>, then type the counted quantity. The difference and its value appear by themselves. Save: the adjustment is a <b>Draft</b>, nothing has moved yet.</li>
                                <li><b>Unit cost</b> (what the batch was received at) is filled from the batch. Change it only to correct a wrong cost: tick <i>"I changed a unit cost on purpose"</i>. Without the tick a mistyped cost is refused, so a batch is never revalued by accident.</li>
                                <li><b>Post</b> with your password. Stock, the ledgers, the valuation layers and the journal entry are written in one go; if anything fails, everything is rolled back.</li>
                                <li>A posted adjustment cannot be edited or deleted. A draft can.</li>
                            </ol>
                            <div class="gh-note">Quantity only counts what the batch holds in the chosen warehouse. If a batch is also in another warehouse, or part of it is still on a van, its unit cost cannot be changed; the system says why.</div>
                        </div>
                        <div class="sa-ur-col gh-rtl gh-scroll" dir="rtl" lang="ur">
                            <ol>
                                <li><b>نئی ایڈجسٹمنٹ</b> میں تاریخ، کمپنی (سپلائر)، گودام اور قسم (نقصان، چوری، گنتی کا فرق، ایکسپائری، ریکال، دیگر) چنیں اور وجہ لکھیں۔ ہر لائن میں پروڈکٹ اور <b>بیچ</b> چنیں اور گنتی کی مقدار لکھیں۔ فرق اور اس کی قدر خود نکل آتی ہے۔ محفوظ کرنے پر ایڈجسٹمنٹ <b>مسودہ (Draft)</b> رہتی ہے، ابھی کچھ نہیں بدلتا۔</li>
                                <li><b>یونٹ کاسٹ</b> (جس لاگت پر بیچ آیا تھا) بیچ سے خود بھر جاتی ہے۔ اسے صرف غلط لاگت درست کرنے کے لیے بدلیں اور <span dir="ltr">"I changed a unit cost on purpose"</span> پر ٹک لگائیں۔ ٹک کے بغیر غلط لکھی ہوئی لاگت قبول نہیں ہوتی، اس لیے بیچ کی ویلیو غلطی سے نہیں بدلتی۔</li>
                                <li>اپنا <b>پاس ورڈ</b> لکھ کر <b>پوسٹ</b> کریں۔ اسٹاک، لیجر، ویلیوایشن اور جرنل انٹری ایک ساتھ لکھے جاتے ہیں؛ کوئی بھی مرحلہ ناکام ہو تو سب کچھ واپس ہو جاتا ہے۔</li>
                                <li>پوسٹ شدہ ایڈجسٹمنٹ میں ترمیم یا حذف نہیں ہو سکتا، مسودے میں ہو سکتا ہے۔</li>
                            </ol>
                            <div class="gh-note">مقدار صرف وہی گنی جاتی ہے جو بیچ منتخب گودام میں رکھتا ہے۔ اگر بیچ کسی دوسرے گودام میں بھی ہو یا کچھ مال ابھی وین پر ہو تو یونٹ کاسٹ نہیں بدلی جا سکتی اور سسٹم وجہ بتا دیتا ہے۔</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="sa-tables">
                <header class="uf-card-head sa-bihead"><h2 class="uf-card-title" id="sa-tables">2. What posting writes to the database</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۲۔ پوسٹ کرنے پر ڈیٹا بیس میں کیا لکھا جاتا ہے</h2></header>
                <div class="uf-body">
                    <div class="sa-cols">
                        <div class="sa-en gh-scroll" lang="en">
                            <table class="gh-table">
                                <thead><tr><th>Table</th><th>What changes</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="gh-code">stock_adjustments</span>, <span class="gh-code">stock_adjustment_items</span></td><td>Header and lines (system qty, counted qty, difference, unit cost, value). Status becomes posted, with who and when.</td></tr>
                                    <tr><td><span class="gh-code">stock_movements</span></td><td>One movement per line (type adjustment, signed quantity). A cost correction adds a zero-quantity movement.</td></tr>
                                    <tr><td><span class="gh-code">inventory_ledger_entries</span>, <span class="gh-code">stock_ledger_entries</span></td><td>A debit (found) or credit (lost) row per line with the running balance. A cost-only line is a row with zero quantity carrying the revaluation value.</td></tr>
                                    <tr><td><span class="gh-code">current_stock_by_batch</span>, <span class="gh-code">current_stock</span></td><td>Quantity on hand and value of the batch in that warehouse; a batch emptied by a loss becomes depleted (a recall: recalled) and a batch found again becomes active.</td></tr>
                                    <tr><td><span class="gh-code">stock_valuation_layers</span></td><td>A shortage takes quantity from the oldest layer first; a surplus goes back into the batch's own layer. A cost correction restates every layer of the batch.</td></tr>
                                    <tr><td><span class="gh-code">stock_batches</span></td><td>New <span class="gh-code">unit_cost</span> after a revaluation; status when emptied or found again.</td></tr>
                                    <tr><td><span class="gh-code">journal_entries</span>, <span class="gh-code">journal_entry_details</span></td><td>Loss: Stock Loss account debit, Stock In Hand credit. Found or revalued up: the reverse. The total includes the revaluation. Nothing is written when the total is zero.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="sa-ur-col gh-rtl gh-scroll" dir="rtl" lang="ur">
                            <table class="gh-table">
                                <thead><tr><th>ٹیبل</th><th>کیا بدلتا ہے</th></tr></thead>
                                <tbody>
                                    <tr><td><bdi class="gh-code">stock_adjustments</bdi> اور <bdi class="gh-code">stock_adjustment_items</bdi></td><td>ہیڈر اور لائنیں (سسٹم مقدار، گنتی، فرق، یونٹ کاسٹ، قدر)۔ اسٹیٹس posted ہو جاتا ہے، کس نے اور کب۔</td></tr>
                                    <tr><td><bdi class="gh-code">stock_movements</bdi></td><td>ہر لائن کی ایک حرکت (adjustment، مثبت یا منفی مقدار)۔ لاگت کی درستگی پر صفر مقدار کی ایک اضافی حرکت بنتی ہے۔</td></tr>
                                    <tr><td><bdi class="gh-code">inventory_ledger_entries</bdi> اور <bdi class="gh-code">stock_ledger_entries</bdi></td><td>ہر لائن کی ڈیبٹ (ملا) یا کریڈٹ (کم ہوا) قطار اور چلتا بیلنس۔ صرف لاگت بدلنے والی لائن صفر مقدار کی قطار ہوتی ہے جس میں ری ویلیوایشن کی رقم لکھی ہوتی ہے۔</td></tr>
                                    <tr><td><bdi class="gh-code">current_stock_by_batch</bdi> اور <bdi class="gh-code">current_stock</bdi></td><td>اس گودام میں بیچ کی مقدار اور قدر؛ نقصان سے خالی ہونے والا بیچ depleted (ریکال پر recalled) اور دوبارہ مال ملنے پر active ہو جاتا ہے۔</td></tr>
                                    <tr><td><bdi class="gh-code">stock_valuation_layers</bdi></td><td>کمی پر مقدار سب سے پرانی لیئر سے نکلتی ہے؛ زیادتی بیچ کی اپنی لیئر میں واپس جاتی ہے۔ لاگت بدلنے پر بیچ کی ہر لیئر نئی لاگت پر آ جاتی ہے۔</td></tr>
                                    <tr><td><bdi class="gh-code">stock_batches</bdi></td><td>ری ویلیوایشن کے بعد نئی <bdi class="gh-code">unit_cost</bdi>؛ خالی یا دوبارہ ملنے پر اسٹیٹس۔</td></tr>
                                    <tr><td><bdi class="gh-code">journal_entries</bdi> اور <bdi class="gh-code">journal_entry_details</bdi></td><td>نقصان: Stock Loss ڈیبٹ، Stock In Hand کریڈٹ۔ زیادتی یا لاگت بڑھنے پر الٹ۔ کل رقم میں ری ویلیوایشن شامل ہوتی ہے۔ کل رقم صفر ہو تو کچھ نہیں لکھا جاتا۔</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="sa-ticket">
                <header class="uf-card-head sa-bihead"><h2 class="uf-card-title" id="sa-ticket">3. Doing it through a ticket</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۳۔ ٹکٹ کے ذریعے</h2></header>
                <div class="uf-body">
                    <div class="sa-cols">
                        <div class="sa-en gh-scroll" lang="en">
                            <ul>
                                <li>A company user raises a <b>Stock Adjustment</b> ticket; nothing changes until an admin approves it. Approval creates and posts the adjustment above, using the same code.</li>
                                <li>Each line has two prices: <b>Selling cost</b> (the price you sell the batch at) and <b>Unit cost</b> (what it was received at). Both are filled from the batch once you pick it.</li>
                                <li><b>Selling cost</b>: choose <i>This batch</i> or <i>All batches</i>. All batches updates every batch of the product that holds stock, the product's selling price for new receipts, the GRN lines still holding stock, and the price change log. Goods already on vans keep the price they were issued at. No password needed.</li>
                                <li><b>Unit cost</b>: changing it revalues the batch, so on Submit a confirmation opens in Urdu showing old and new cost. Tick it and enter your password; the admin enters the password again on approval. This is the audit trail.</li>
                            </ul>
                            <div class="gh-tip">The ticket help page explains the whole ticket flow, the approval e-mail and the history.</div>
                        </div>
                        <div class="sa-ur-col gh-rtl gh-scroll" dir="rtl" lang="ur">
                            <ul>
                                <li>کمپنی کا صارف <b>Stock Adjustment</b> ٹکٹ بناتا ہے؛ ایڈمن کی منظوری تک کچھ نہیں بدلتا۔ منظوری پر اوپر والی ایڈجسٹمنٹ وہی کوڈ استعمال کر کے بنتی اور پوسٹ ہو جاتی ہے۔</li>
                                <li>ہر لائن میں دو قیمتیں ہوتی ہیں: <b>Selling cost</b> (جس قیمت پر بیچ بیچنا ہے) اور <b>Unit cost</b> (جس لاگت پر آیا تھا)۔ بیچ چنتے ہی دونوں خود بھر جاتی ہیں۔</li>
                                <li><b>Selling cost</b>: <span dir="ltr">This batch</span> یا <span dir="ltr">All batches</span> چنیں۔ سب بیچز پر لگانے سے پروڈکٹ کے تمام اسٹاک والے بیچز، نئی رسیدوں کے لیے پروڈکٹ کی قیمت، اسٹاک والی جی آر این لائنیں اور پرائس چینج لاگ سب اپ ڈیٹ ہوتے ہیں۔ جو مال پہلے ہی وین پر جا چکا ہے وہ اپنی جاری شدہ قیمت پر رہتا ہے۔ پاس ورڈ کی ضرورت نہیں۔</li>
                                <li><b>Unit cost</b>: بدلنے سے بیچ کی ری ویلیوایشن ہوتی ہے، اس لیے Submit پر اردو میں تصدیقی ونڈو کھلتی ہے جس میں پرانی اور نئی لاگت لکھی ہوتی ہے۔ ٹک لگائیں اور اپنا پاس ورڈ لکھیں؛ منظوری پر ایڈمن بھی دوبارہ پاس ورڈ لکھتا ہے۔ یہی آڈٹ کا ریکارڈ ہے۔</li>
                            </ul>
                            <div class="gh-tip">ٹکٹ کا پورا طریقہ، منظوری کی ای میل اور ہسٹری ٹکٹس کے ہیلپ صفحے پر ہے۔</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

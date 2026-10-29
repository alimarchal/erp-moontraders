{{--
    Expense Vouchers -> How it works / طریقہ کار (/expense-vouchers/help): English and Urdu side by side.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('expense-vouchers.index') }}">Expense Vouchers</a><span aria-hidden="true">›</span><span>How it works</span>
                </nav>
                <h1 class="ak-title">How expense vouchers work <span class="ev-ur-title" lang="ur" dir="rtl">اخراجاتی واؤچر کیسے کام کرتا ہے</span></h1>
                <p class="ak-sub">The maker and checker steps, what is saved at each step, and what reaches the ledger and the reports. &middot; <span lang="ur" dir="rtl" class="ev-ur-title" style="font-size:15px">میکر اور چیکر کے مراحل، ہر مرحلے پر محفوظ ہونے والا ڈیٹا، اور لیجر و رپورٹس تک پہنچنے والی معلومات۔</span></p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('expense-vouchers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to vouchers</a>
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
        .gh-card ul { list-style: disc; }
        .gh-card ol { list-style: decimal; }
        .gh-card li { margin: 3px 0; }
        .gh-card h3 { margin: 14px 0 4px; font-size: 14px; font-weight: 700; color: var(--ak-navy); }
        .gh-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
        .gh-table th { text-align: left; background: var(--ak-slate-bg); color: var(--ak-muted); font-weight: 600; padding: 7px 10px; border-bottom: 1px solid var(--ak-border); }
        .gh-table td { padding: 7px 10px; border-bottom: 1px solid var(--ak-line); vertical-align: top; }
        .gh-note { margin-top: 10px; padding: 9px 12px; border-left: 3px solid var(--ak-amber); background: var(--ak-amber-bg); border-radius: 6px; font-size: 13px; }
        .gh-scroll { overflow-x: auto; }
        .gh-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; background: var(--ak-slate-bg); padding: 1px 5px; border-radius: 4px; }
        .fc-box { fill: #fff; stroke: #172554; stroke-width: 1.4; }
        .fc-box-green { fill: #dcfce7; stroke: #15803d; stroke-width: 1.4; }
        .fc-box-amber { fill: #fef3c7; stroke: #b45309; stroke-width: 1.4; }
        .fc-box-red { fill: #fee2e2; stroke: #b91c1c; stroke-width: 1.4; }
        .fc-box-slate { fill: #f1f5f9; stroke: #64748b; stroke-width: 1.4; }
        .fc-text { font-size: 12.5px; fill: #0f172a; font-family: ui-sans-serif, system-ui, sans-serif; }
        .fc-bold { font-weight: 700; }
        .fc-small { font-size: 11px; fill: #475569; font-family: ui-sans-serif, system-ui, sans-serif; }
        .fc-line { stroke: #475569; stroke-width: 1.4; fill: none; }
        @media print { .gh-card { break-inside: avoid; box-shadow: none; } }
            .gh-rtl { direction: rtl; text-align: right; font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Geeza Pro', serif; }
        .gh-rtl .gh-card p, .gh-rtl .gh-card li { font-size: 17px; line-height: 2; }
        .gh-rtl .gh-card ul, .gh-rtl .gh-card ol { padding-right: 26px; padding-left: 0; }
        .gh-rtl .gh-card h3 { font-size: 17px; }
        .gh-rtl .uf-card-title { font-size: 19px; font-weight: 700; }
        .gh-rtl .gh-table { font-size: 15.5px; line-height: 1.9; }
        .gh-rtl .gh-table th, .gh-rtl .gh-table td { text-align: right; }
        .gh-rtl .gh-note { border-left: 0; border-right: 3px solid var(--ak-amber); font-size: 16px; line-height: 1.9; }
        .gh-rtl .gh-code, .gh-rtl bdi { direction: ltr; unicode-bidi: isolate; display: inline-block; }
        .gh-rtl svg text { font-family: 'Noto Naskh Arabic', 'Geeza Pro', serif; }
        .gh-rtl .fc-text { font-size: 14px; } .gh-rtl .fc-small { font-size: 12.5px; }
        .ak-head .gh-rtl h1 { font-size: 26px; } .ak-head .gh-rtl .ak-sub { font-size: 16px; line-height: 1.8; } .ak-head .gh-rtl .ak-crumbs { font-size: 14px; }
    
        .ev-bihead { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .ev-cols { display: grid; grid-template-columns: 1fr; gap: 28px; }
        @media (min-width: 1100px) { .ev-cols { grid-template-columns: 1fr 1fr; gap: 0; } .ev-en { padding-right: 24px; border-right: 1px solid var(--ak-border); } .ev-ur-col { padding-left: 24px; } }
        .ev-chart { margin-bottom: 18px; } .ev-chart-label { margin: 0 0 4px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--ak-muted); }
        .ev-charts { margin-bottom: 8px; }
        .gh-rtl.ev-ur-col .gh-table { font-size: 15px; }
    </style>
    <style>.ev-ur-title { font-family: 'Noto Naskh Arabic', 'Geeza Pro', serif; font-weight: 700; color: var(--ak-navy); margin-inline-start: 12px; font-size: 22px; }</style>

    <div class="ak-page">
        <div class="gh-wrap">

            <section class="uf-card gh-card" aria-labelledby="ev-flow">
                <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="ev-flow">1. The flow</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۱۔ پورا طریقہ کار</h2></header>
                <div class="uf-body">
                    <div class="ev-charts gh-scroll"><div class="ev-chart"><p class="ev-chart-label">English</p><svg viewBox="0 0 880 330" width="100%" role="img" aria-label="Expense voucher flow" style="min-width:760px"><defs><marker id="fc-arrow-v" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="10" y="20" width="170" height="60" rx="9" /><text class="fc-text fc-bold" x="95.0" y="38.0" text-anchor="middle">1. Maker submits</text><text class="fc-small" x="95.0" y="54.0" text-anchor="middle">status Pending</text><text class="fc-small" x="95.0" y="70.0" text-anchor="middle">expense_vouchers: new row</text><rect class="fc-box-amber" x="230" y="20" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="325.0" y="38.0" text-anchor="middle">2. Waiting for checker</text><text class="fc-small" x="325.0" y="54.0" text-anchor="middle">status Pending</text><text class="fc-small" x="325.0" y="70.0" text-anchor="middle">expense_vouchers: updated</text><rect class="fc-box-amber" x="470" y="20" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="565.0" y="46.0" text-anchor="middle">3. Checker (another user)</text><text class="fc-small" x="565.0" y="62.0" text-anchor="middle">approve or reject?</text><rect class="fc-box-green" x="710" y="20" width="160" height="60" rx="9" /><text class="fc-text fc-bold" x="790.0" y="46.0" text-anchor="middle">4. Approved</text><text class="fc-small" x="790.0" y="62.0" text-anchor="middle">ledger entry posted</text><rect class="fc-box-red" x="470" y="140" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="565.0" y="166.0" text-anchor="middle">Rejected</text><text class="fc-small" x="565.0" y="182.0" text-anchor="middle">reason saved, no ledger entry</text><rect class="fc-box-slate" x="230" y="140" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="325.0" y="166.0" text-anchor="middle">Maker corrects</text><text class="fc-small" x="325.0" y="182.0" text-anchor="middle">edit, then send again</text><rect class="fc-box-green" x="710" y="140" width="160" height="60" rx="9" /><text class="fc-text fc-bold" x="790.0" y="166.0" text-anchor="middle">5. Print 80mm slip</text><text class="fc-small" x="790.0" y="182.0" text-anchor="middle">thermal printer</text><rect class="fc-box-green" x="710" y="250" width="160" height="60" rx="9" /><text class="fc-text fc-bold" x="790.0" y="268.0" text-anchor="middle">Reports</text><text class="fc-small" x="790.0" y="284.0" text-anchor="middle">Cash Collection Distributor</text><text class="fc-small" x="790.0" y="300.0" text-anchor="middle">Expenses table</text><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M180 50 H228" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M420 50 H468" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M660 50 H708" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M565 80 V138" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M468 170 H422" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M325 140 V82" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M790 80 V138" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M790 200 V248" /><text class="fc-small" x="668" y="42">yes</text><text class="fc-small" x="572" y="114">no</text></svg></div><div class="ev-chart" dir="rtl" lang="ur"><p class="ev-chart-label gh-rtl">اردو</p><svg viewBox="0 0 880 346" width="100%" role="img" aria-label="واؤچر کا طریقہ کار" style="min-width:760px"><defs><marker id="fc-arrow-u" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="700" y="20" width="170" height="60" rx="9" /><text class="fc-text fc-bold" x="785.0" y="38.0" text-anchor="middle" direction="rtl">۱۔ میکر جمع کراتا ہے</text><text class="fc-small" x="785.0" y="54.0" text-anchor="middle" direction="rtl">اسٹیٹس: منظوری کا منتظر</text><rect class="fc-box-amber" x="460" y="20" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="555.0" y="46.0" text-anchor="middle" direction="rtl">۲۔ چیکر کے پاس</text><text class="fc-small" x="555.0" y="62.0" text-anchor="middle" direction="rtl">دوسرا صارف دیکھتا ہے</text><rect class="fc-box-amber" x="220" y="20" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="315.0" y="46.0" text-anchor="middle" direction="rtl">۳۔ چیکر فیصلہ کرتا ہے</text><text class="fc-small" x="315.0" y="62.0" text-anchor="middle" direction="rtl">منظور یا مسترد؟</text><rect class="fc-box-green" x="10" y="20" width="160" height="60" rx="9" /><text class="fc-text fc-bold" x="90.0" y="46.0" text-anchor="middle" direction="rtl">۴۔ منظور</text><text class="fc-small" x="90.0" y="62.0" text-anchor="middle" direction="rtl">لیجر میں اندراج ہو گیا</text><rect class="fc-box-red" x="220" y="140" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="315.0" y="166.0" text-anchor="middle" direction="rtl">مسترد</text><text class="fc-small" x="315.0" y="182.0" text-anchor="middle" direction="rtl">وجہ درج، لیجر میں کچھ نہیں</text><rect class="fc-box-slate" x="460" y="140" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="555.0" y="166.0" text-anchor="middle" direction="rtl">میکر درست کرتا ہے</text><text class="fc-small" x="555.0" y="182.0" text-anchor="middle" direction="rtl">ترمیم کر کے دوبارہ جمع</text><rect class="fc-box-green" x="10" y="140" width="160" height="60" rx="9" /><text class="fc-text fc-bold" x="90.0" y="166.0" text-anchor="middle" direction="rtl">۵۔ تھرمل سلپ</text><text class="fc-small" x="90.0" y="182.0" text-anchor="middle" direction="rtl">80mm پرنٹر</text><rect class="fc-box-green" x="10" y="250" width="160" height="84" rx="9" /><text class="fc-text fc-bold" x="90" y="274" text-anchor="middle" direction="rtl">رپورٹس</text><text class="fc-small" x="90" y="292" text-anchor="middle" direction="rtl">Cash Collection</text><text class="fc-small" x="90" y="308" text-anchor="middle" direction="rtl">Distributor</text><text class="fc-small" x="90" y="324" text-anchor="middle" direction="rtl">اخراجات کی جدول</text><path class="fc-line" style="marker-end:url(#fc-arrow-u)" d="M700 50 H648" /><path class="fc-line" style="marker-end:url(#fc-arrow-u)" d="M460 50 H408" /><path class="fc-line" style="marker-end:url(#fc-arrow-u)" d="M220 50 H168" /><path class="fc-line" style="marker-end:url(#fc-arrow-u)" d="M315 80 V138" /><path class="fc-line" style="marker-end:url(#fc-arrow-u)" d="M410 170 H458" /><path class="fc-line" style="marker-end:url(#fc-arrow-u)" d="M555 140 V82" /><path class="fc-line" style="marker-end:url(#fc-arrow-u)" d="M90 80 V138" /><path class="fc-line" style="marker-end:url(#fc-arrow-u)" d="M90 200 V248" /><text class="fc-small" x="194" y="40" text-anchor="middle" direction="rtl">ہاں</text><text class="fc-small" x="320" y="114" text-anchor="end" direction="rtl">نہیں</text></svg></div></div>
                    <div class="ev-cols">
                        <div class="ev-en gh-scroll" lang="en">
                    
                    <ol>
                        <li><b>Maker</b> opens New voucher: date, company, party name, expense account, amount, narration, and presses <b>Submit for approval</b>. The voucher gets a number such as EV-2026-0001 and is now <b>Pending approval</b>; it cannot be changed. (A voucher saved with Save changes on the edit page stays a <b>Draft</b>; a draft or rejected voucher can be edited, deleted or submitted again.)</li>
                        <li>A <b>different user</b> with the approve permission opens it. <b>Approve</b> posts the ledger entry; <b>Reject</b> needs a reason and sends it back to the maker, who corrects and sends it again. The person who made the voucher can never approve it, not even a super admin.</li>
                        <li>Only an <b>approved</b> voucher can be printed: an 80 mm thermal slip with the party, account, amount and the maker and checker.</li>
                    </ol>
                    <div class="gh-note">Most expenses belong to Moon Traders itself. An admin leaves the company empty (general expense). A user tied to one company always raises vouchers for that company.</div>
                </div>
                        <div class="ev-ur-col gh-rtl gh-scroll" dir="rtl" lang="ur">
                    
                    <ol>
                        <li><b>میکر</b> (واؤچر بنانے والا) "New voucher" کھول کر تاریخ، کمپنی، پارٹی کا نام، اخراجات کا کھاتہ، رقم اور تفصیل بھرتا ہے اور <b>"منظوری کے لیے جمع کرائیں" (Submit for approval)</b> دباتا ہے۔ واؤچر کو نمبر مل جاتا ہے، جیسے <bdi class="gh-code">EV-2026-0001</bdi>، اور وہ <b>منظوری کا منتظر (Pending)</b> ہو جاتا ہے۔ اب اس میں تبدیلی نہیں ہو سکتی۔</li>
                        <li>اگر میکر ترمیم والے صفحے پر صرف "تبدیلیاں محفوظ کریں" (Save changes) دبائے تو واؤچر <b>مسودہ (Draft)</b> ہی رہتا ہے۔ مسودہ یا مسترد شدہ واؤچر میں ترمیم، حذف، یا دوبارہ جمع کرانا ممکن ہے۔</li>
                        <li><b>چیکر</b> (جانچنے والا) کوئی <b>دوسرا صارف</b> ہوتا ہے جس کے پاس منظوری کی اجازت ہو۔ وہ واؤچر کھول کر <b>منظور (Approve)</b> کرتا ہے تو لیجر میں اندراج ہو جاتا ہے، یا <b>مسترد (Reject)</b> کرتا ہے تو وجہ لکھنا لازمی ہے اور واؤچر میکر کو واپس چلا جاتا ہے۔ جس نے واؤچر بنایا ہے وہ اسے کبھی منظور نہیں کر سکتا، سپر ایڈمن بھی نہیں۔</li>
                        <li>صرف <b>منظور شدہ</b> واؤچر کی 80mm تھرمل سلپ پرنٹ ہو سکتی ہے، جس میں پارٹی، کھاتہ، رقم اور میکر و چیکر کے نام ہوتے ہیں۔</li>
                    </ol>
                    <div class="gh-note">زیادہ تر اخراجات موون ٹریڈرز (Moon Traders) کے اپنے ہوتے ہیں۔ ایڈمن کمپنی کے خانے میں "Moon Traders (general expense)" رکھتا ہے۔ جس صارف کا اکاؤنٹ کسی ایک کمپنی سے جڑا ہو وہ ہمیشہ اسی کمپنی کے واؤچر بناتا ہے۔ ایسے عمومی خرچ کا کھاتہ پہلے سے <bdi class="gh-code">5201 Moon Traders General Expenses</bdi> منتخب ہوتا ہے۔</div>
                </div>
                    </div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="ev-tables">
                <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="ev-tables">2. What each step writes to the database</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۲۔ ہر مرحلے پر ڈیٹا بیس میں کیا لکھا جاتا ہے</h2></header>
                <div class="uf-body">
                    
                    <div class="ev-cols">
                        <div class="ev-en gh-scroll" lang="en">
                    <table class="gh-table">
                        <thead><tr><th>Step</th><th>Tables written</th><th>Accounting effect</th></tr></thead>
                        <tbody>
                            <tr><td>Submit for approval (or save as draft)</td><td><span class="gh-code">expense_vouchers</span> gets a new row: voucher number, maker, status pending (draft if only saved).</td><td>None.</td></tr>
                            <tr><td>Edit or delete a draft</td><td><span class="gh-code">expense_vouchers</span> row is updated, or soft-deleted.</td><td>None.</td></tr>
                            <tr><td>Send to checker</td><td><span class="gh-code">expense_vouchers</span>: status pending, sent by, sent at.</td><td>None.</td></tr>
                            <tr><td>Reject</td><td><span class="gh-code">expense_vouchers</span>: status rejected, reason, checker, time.</td><td>None.</td></tr>
                            <tr><td><b>Approve</b></td><td><span class="gh-code">journal_entries</span> gets one entry (reference = voucher number, posted); <span class="gh-code">journal_entry_details</span> gets two lines; <span class="gh-code">expense_vouchers</span>: status approved, checker, time, journal entry id. All in one transaction: if posting fails nothing is saved.</td><td><b>Debit</b> the expense account, <b>credit</b> Cash (1121), for the voucher amount, dated on the voucher date.</td></tr>
                            <tr><td>Print slip</td><td><span class="gh-code">expense_vouchers</span>: printed at (first print only).</td><td>None.</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note"><b>Is it a transaction?</b> Only an approved voucher is: it creates a posted journal entry, so General Ledger, Trial Balance, Income Statement and Balance Sheet pick it up through their normal views. There is no separate balance table to update, and a posted entry cannot be changed afterwards. Drafts, pending and rejected vouchers are records only.</div>
                </div>
                        <div class="ev-ur-col gh-rtl gh-scroll" dir="rtl" lang="ur">
                    <table class="gh-table">
                        <thead><tr><th>مرحلہ</th><th>کون سی ٹیبل (Table) میں کیا ہوتا ہے</th><th>حسابی اثر</th></tr></thead>
                        <tbody>
                            <tr><td>منظوری کے لیے جمع کرانا</td><td><bdi class="gh-code">expense_vouchers</bdi> میں نئی قطار: واؤچر نمبر، میکر، اسٹیٹس pending۔ (اگر صرف محفوظ کیا ہو تو اسٹیٹس draft۔)</td><td>کوئی نہیں۔</td></tr>
                            <tr><td>مسودے میں ترمیم یا حذف</td><td><bdi class="gh-code">expense_vouchers</bdi> کی قطار اپ ڈیٹ ہوتی ہے یا soft-delete۔</td><td>کوئی نہیں۔</td></tr>
                            <tr><td>مسترد کرنا</td><td><bdi class="gh-code">expense_vouchers</bdi>: اسٹیٹس rejected، وجہ، چیکر اور وقت۔</td><td>کوئی نہیں۔</td></tr>
                            <tr><td><b>منظور کرنا</b></td><td>ایک ہی ٹرانزیکشن میں: <bdi class="gh-code">journal_entries</bdi> میں ایک posted اندراج (حوالہ = واؤچر نمبر)، <bdi class="gh-code">journal_entry_details</bdi> میں دو لائنیں، اور <bdi class="gh-code">expense_vouchers</bdi> میں اسٹیٹس approved، چیکر، وقت اور journal entry کی آئی ڈی۔ اگر پوسٹنگ ناکام ہو جائے تو کچھ بھی محفوظ نہیں ہوتا۔</td><td><b>ڈیبٹ (Debit)</b> اخراجات کا کھاتہ، <b>کریڈٹ (Credit)</b> کیش <bdi class="gh-code">1121</bdi>، رقم واؤچر کے برابر اور تاریخ واؤچر والی۔</td></tr>
                            <tr><td>سلپ پرنٹ کرنا</td><td><bdi class="gh-code">expense_vouchers.printed_at</bdi> صرف پہلی بار۔</td><td>کوئی نہیں۔</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note"><b>کیا یہ ٹرانزیکشن ہے؟</b> صرف منظور شدہ واؤچر ٹرانزیکشن بنتا ہے، کیونکہ اسی سے ایک posted journal entry بنتی ہے۔ General Ledger، Trial Balance، Income Statement اور Balance Sheet اسی اندراج سے خود بخود بنتے ہیں؛ الگ بیلنس ٹیبل کو اپ ڈیٹ نہیں کرنا پڑتا۔ posted اندراج بعد میں تبدیل نہیں ہوتا۔ مسودہ، منظوری کا منتظر اور مسترد واؤچر صرف ریکارڈ ہیں، ان سے کوئی حسابی اثر نہیں پڑتا۔</div>
                </div>
                    </div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="ev-reports">
                <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="ev-reports">3. Where approved vouchers show up</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۳۔ منظور شدہ واؤچر کہاں نظر آتے ہیں</h2></header>
                <div class="uf-body">
                    
                    <div class="ev-cols">
                        <div class="ev-en gh-scroll" lang="en">
                    <ul>
                        <li><b>Cash Collection Distributor</b> report: the Expenses table lists approved vouchers of the day by party. A single company shows its own; All Companies also shows the general ones. The Cash Summary subtracts them from the cash received to give the cash in hand.</li>
                        <li><b>General Ledger / Trial Balance / Income Statement</b>: through the journal entry, under the expense account chosen on the voucher.</li>
                    </ul>
                </div>
                        <div class="ev-ur-col gh-rtl gh-scroll" dir="rtl" lang="ur">
                    <ul>
                        <li><b>Cash Collection Distributor</b> رپورٹ: "Expenses" جدول میں اس دن کے منظور شدہ واؤچر پارٹی کے حساب سے آتے ہیں۔ ایک کمپنی منتخب ہو تو صرف اسی کے واؤچر، اور All Companies میں عمومی (Moon Traders) واؤچر بھی۔ "Cash Summary" میں یہ رقم وصول شدہ کیش سے منہا ہو کر "Cash in hand" بنتا ہے۔</li>
                        <li><b>General Ledger / Trial Balance / Income Statement</b>: journal entry کے ذریعے، واؤچر پر منتخب اخراجات کے کھاتے کے تحت۔</li>
                    </ul>
                </div>
                    </div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="ev-perm">
                <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="ev-perm">4. Permissions</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۴۔ اجازتیں (Permissions)</h2></header>
                <div class="uf-body">
                    
                    <div class="ev-cols">
                        <div class="ev-en gh-scroll" lang="en">
                    <table class="gh-table">
                        <thead><tr><th>Permission</th><th>Allows</th></tr></thead>
                        <tbody>
                            <tr><td><span class="gh-code">expense-voucher-list</span></td><td>See the list, open a voucher and this guide.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-create</span></td><td>Make a voucher and send it to the checker.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-edit</span> / <span class="gh-code">-delete</span></td><td>Change or delete a draft or rejected voucher.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-approve</span></td><td>Act as checker: approve or reject.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-print</span></td><td>Print the thermal slip of an approved voucher.</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note">Admins and super admins receive all of them automatically. Give the others only what their job needs, and keep the maker and the checker as different people.</div>
                </div>
                        <div class="ev-ur-col gh-rtl gh-scroll" dir="rtl" lang="ur">
                    <table class="gh-table">
                        <thead><tr><th>اجازت</th><th>کیا کر سکتا ہے</th></tr></thead>
                        <tbody>
                            <tr><td><bdi class="gh-code">expense-voucher-list</bdi></td><td>فہرست دیکھنا، واؤچر کھولنا اور یہ رہنما صفحہ دیکھنا۔</td></tr>
                            <tr><td><bdi class="gh-code">expense-voucher-create</bdi></td><td>واؤچر بنانا اور منظوری کے لیے جمع کرانا۔</td></tr>
                            <tr><td><bdi class="gh-code">expense-voucher-edit</bdi> / <bdi class="gh-code">expense-voucher-delete</bdi></td><td>مسودہ یا مسترد واؤچر میں ترمیم یا اسے حذف کرنا۔</td></tr>
                            <tr><td><bdi class="gh-code">expense-voucher-approve</bdi></td><td>چیکر کا کام: منظور یا مسترد کرنا۔</td></tr>
                            <tr><td><bdi class="gh-code">expense-voucher-print</bdi></td><td>منظور شدہ واؤچر کی تھرمل سلپ پرنٹ کرنا۔</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note">ایڈمن اور سپر ایڈمن کو یہ تمام اجازتیں سسٹم اپ ڈیٹ ہوتے ہی خود مل جاتی ہیں۔ باقی صارفین کو صرف وہی اجازتیں دیں جو ان کے کام کے لیے ضروری ہوں، اور میکر اور چیکر ہمیشہ الگ افراد رکھیں۔</div>
                </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

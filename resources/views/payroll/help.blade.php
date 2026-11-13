{{-- Payroll -> How it works / طریقہ کار (/payroll/help): static page, English and Urdu. --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('payroll.index') }}">Payroll</a><span aria-hidden="true">›</span><span>How it works</span></nav>
                <h1 class="ak-title">How payroll works <span class="ev-ur-title" lang="ur" dir="rtl">تنخواہ کا نظام</span></h1>
                <p class="ak-sub">From the salary set-up to the salary slip, advances and the ledger.</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('payroll.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to payroll</a><button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .gh-wrap { display:flex; flex-direction:column; gap:20px; } .gh-card p, .gh-card li { font-size:14px; line-height:1.55; color:var(--ak-text); } .gh-card ul, .gh-card ol { margin:6px 0 0; padding-left:22px; } .gh-card ul { list-style:disc; } .gh-card ol { list-style:decimal; } .gh-card li { margin:3px 0; } .gh-card h3 { margin:14px 0 4px; font-size:14px; font-weight:700; color:var(--ak-navy); }
        .gh-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:8px; } .gh-table th { text-align:left; background:var(--ak-slate-bg); color:var(--ak-muted); font-weight:600; padding:7px 10px; border-bottom:1px solid var(--ak-border); } .gh-table td { padding:7px 10px; border-bottom:1px solid var(--ak-line); vertical-align:top; } .gh-table .num { text-align:right; font-variant-numeric:tabular-nums; }
        .gh-note { margin-top:10px; padding:9px 12px; border-left:3px solid var(--ak-amber); background:var(--ak-amber-bg); border-radius:6px; font-size:13px; } .gh-scroll { overflow-x:auto; } .gh-code { font-family:ui-monospace, Menlo, monospace; font-size:12.5px; background:var(--ak-slate-bg); padding:1px 5px; border-radius:4px; }
        .fc-box { fill:#fff; stroke:#172554; stroke-width:1.4; } .fc-box-green { fill:#dcfce7; stroke:#15803d; stroke-width:1.4; } .fc-box-amber { fill:#fef3c7; stroke:#b45309; stroke-width:1.4; } .fc-box-red { fill:#fee2e2; stroke:#b91c1c; stroke-width:1.4; } .fc-box-slate { fill:#f1f5f9; stroke:#64748b; stroke-width:1.4; }
        .fc-text { font-size:12.5px; fill:#0f172a; font-family:ui-sans-serif, system-ui, sans-serif; } .fc-bold { font-weight:700; } .fc-small { font-size:11px; fill:#475569; font-family:ui-sans-serif, system-ui, sans-serif; } .fc-line { stroke:#475569; stroke-width:1.4; fill:none; }
        .gh-rtl { direction:rtl; text-align:right; font-family:'Noto Naskh Arabic','Jameel Noori Nastaleeq','Geeza Pro',serif; } .gh-rtl .gh-card p, .gh-rtl li, .gh-rtl p { font-size:17px; line-height:2; } .gh-rtl ul, .gh-rtl ol { padding-right:26px; padding-left:0; } .gh-rtl h3 { font-size:17px; } .gh-rtl .gh-table { font-size:15.5px; } .gh-rtl .gh-table th, .gh-rtl .gh-table td { text-align:right; } .gh-rtl .gh-note { border-left:0; border-right:3px solid var(--ak-amber); font-size:16px; line-height:1.9; } .gh-rtl .gh-code, .gh-rtl bdi { direction:ltr; unicode-bidi:isolate; display:inline-block; }
        .ev-bihead { display:flex; justify-content:space-between; align-items:center; gap:16px; } .ev-cols { display:grid; grid-template-columns:1fr; gap:28px; } @media (min-width:1100px){ .ev-cols { grid-template-columns:1fr 1fr; gap:0; } .ev-en { padding-right:24px; border-right:1px solid var(--ak-border); } .ev-ur-col { padding-left:24px; } }
        .ev-ur-title { font-family:'Noto Naskh Arabic','Geeza Pro',serif; font-weight:700; color:var(--ak-navy); margin-inline-start:12px; font-size:22px; } @media print { .gh-card { break-inside:avoid; box-shadow:none; } }
    </style>
    <div class="ak-page">
        <div class="gh-wrap">
            <section class="uf-card gh-card" aria-labelledby="pr-flow">
                <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="pr-flow">1. The monthly cycle</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۱۔ ماہانہ طریقہ کار</h2></header>
                <div class="uf-body">
                    <div class="gh-scroll">
                        <svg viewBox="0 0 900 330" width="100%" role="img" aria-label="Payroll cycle" style="min-width:760px">
                            <defs><marker id="pr-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs>
                            <rect class="fc-box-slate" x="10" y="20" width="190" height="64" rx="10" /><text class="fc-text fc-bold" x="105" y="46" text-anchor="middle">Set up once</text><text class="fc-small" x="105" y="64" text-anchor="middle">salary, allowances</text>
                            <rect class="fc-box" x="240" y="20" width="190" height="64" rx="10" /><text class="fc-text fc-bold" x="335" y="46" text-anchor="middle">Run payroll</text><text class="fc-small" x="335" y="64" text-anchor="middle">one button builds all slips</text>
                            <rect class="fc-box" x="470" y="20" width="190" height="64" rx="10" /><text class="fc-text fc-bold" x="565" y="46" text-anchor="middle">Adjust</text><text class="fc-small" x="565" y="64" text-anchor="middle">absent days, overtime, fine</text>
                            <rect class="fc-box-amber" x="700" y="20" width="190" height="64" rx="10" /><text class="fc-text fc-bold" x="795" y="46" text-anchor="middle">Pending</text><text class="fc-small" x="795" y="64" text-anchor="middle">sent to a checker</text>
                            <path class="fc-line" d="M200 52 H238" marker-end="url(#pr-arrow)" /><path class="fc-line" d="M430 52 H468" marker-end="url(#pr-arrow)" /><path class="fc-line" d="M660 52 H698" marker-end="url(#pr-arrow)" />
                            <path class="fc-line" d="M795 84 V130 H565 V150" marker-end="url(#pr-arrow)" />
                            <text class="fc-small" x="680" y="124">approve</text>
                            <path class="fc-line" d="M795 130 H105 V150" marker-end="url(#pr-arrow)" /><text class="fc-small" x="300" y="124">reject: back to the maker</text>
                            <rect class="fc-box-red" x="10" y="152" width="190" height="64" rx="10" /><text class="fc-text fc-bold" x="105" y="178" text-anchor="middle">Rejected</text><text class="fc-small" x="105" y="196" text-anchor="middle">fix and send again</text>
                            <rect class="fc-box-green" x="470" y="152" width="190" height="72" rx="10" /><text class="fc-text fc-bold" x="565" y="176" text-anchor="middle">Approved and posted</text><text class="fc-small" x="565" y="194" text-anchor="middle">ledger entry made</text><text class="fc-small" x="565" y="210" text-anchor="middle">advances recorded as recovered</text>
                            <path class="fc-line" d="M660 188 H698" marker-end="url(#pr-arrow)" />
                            <rect class="fc-box-green" x="700" y="152" width="190" height="72" rx="10" /><text class="fc-text fc-bold" x="795" y="176" text-anchor="middle">Paid</text><text class="fc-small" x="795" y="194" text-anchor="middle">cash or bank</text><text class="fc-small" x="795" y="210" text-anchor="middle">Dr 2112 / Cr Cash or Bank</text>
                            <rect class="fc-box" x="240" y="262" width="650" height="44" rx="10" /><text class="fc-small" x="565" y="289" text-anchor="middle">Every slip can be printed: earnings on the left, deductions on the right, net pay at the bottom</text>
                        </svg>
                    </div>
                    <div class="ev-cols" style="margin-top:12px">
                        <div class="ev-en" lang="en"><ol>
                            <li><b>Employee salaries</b>: for each employee set the salary type, the monthly amount and the allowances once.</li>
                            <li><b>Run payroll</b>: choose the month. Leave the company on <b>Moon Traders (all employees)</b> to pay everyone in one payroll, shown supplier by supplier with a subtotal each, or choose one supplier to pay only its staff. Every active employee with a salary gets a slip. One employee can never be in two payrolls of the same month. Basic pay, allowances, the absent-day cut and the advance instalment are worked out for you.</li>
                            <li>Open an employee to <b>adjust</b>: enter absent days, or add overtime, incentive, bonus, a fine or tax for this month only.</li>
                            <li><b>Send for approval</b>. A different user approves; that posts the ledger entry. Then <b>Mark salaries as paid</b> by cash or bank.</li>
                        </ol></div>
                        <div class="ev-ur-col gh-rtl" dir="rtl" lang="ur"><ol>
                            <li><b>Employee salaries</b>: ہر ملازم کی تنخواہ کی قسم، ماہانہ رقم اور الاؤنسز ایک بار طے کریں۔</li>
                            <li><b>Run payroll</b>: مہینہ چنیں۔ کمپنی <b>"Moon Traders (all employees)"</b> پر رہنے دیں تو سب ملازمین کی تنخواہ ایک ہی payroll میں بنتی ہے (سپلائر کے حساب سے الگ الگ اور ہر ایک کا ذیلی کل)، یا ایک سپلائر چنیں تو صرف اسی کا عملہ۔ تنخواہ والے ہر فعال ملازم کی سلپ بن جاتی ہے۔ ایک ملازم ایک مہینے میں دو payroll میں نہیں آ سکتا۔ بنیادی تنخواہ، الاؤنس، غیر حاضری کی کٹوتی اور ایڈوانس کی قسط خود حساب ہو جاتی ہے۔</li>
                            <li>ملازم کھول کر <b>تبدیلی</b> کریں: غیر حاضر دن لکھیں، یا اس مہینے کے لیے اوور ٹائم، انسینٹو، بونس، جرمانہ یا ٹیکس شامل کریں۔</li>
                            <li><b>منظوری کے لیے بھیجیں</b>۔ کوئی دوسرا صارف منظور کرتا ہے تو لیجر میں اندراج ہو جاتا ہے۔ پھر نقد یا بینک سے <b>"تنخواہیں ادا"</b> کا نشان لگائیں۔</li>
                        </ol></div>
                    </div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="pr-slip">
                <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="pr-slip">2. How a salary slip is worked out</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۲۔ سلپ کا حساب کیسے بنتا ہے</h2></header>
                <div class="uf-body"><div class="ev-cols">
                    <div class="ev-en" lang="en">
                        <table class="gh-table"><thead><tr><th>Line</th><th>Side</th><th>How</th></tr></thead><tbody>
                            <tr><td>Basic salary</td><td>Earning</td><td>The monthly amount set for the employee.</td></tr>
                            <tr><td>Allowances</td><td>Earning</td><td>Set per employee as a fixed amount, a percent of basic, or a percent of another allowance (house rent, utility, medical, conveyance...). New categories can be added any time.</td></tr>
                            <tr><td>Overtime, incentive, bonus</td><td>Earning</td><td>Added by hand in the month they happen.</td></tr>
                            <tr><td>Absent days</td><td>Deduction</td><td><b>Basic</b> type only: basic ÷ days in the month × absent days. <b>Fixed</b> type is never cut automatically.</td></tr>
                            <tr><td>Advance</td><td>Deduction</td><td>Taken automatically from every open advance: the instalment, or everything left. Never more than what remains of the pay.</td></tr>
                            <tr><td>Settlement short</td><td>Deduction</td><td>Taken automatically: what the employee's posted settlements of the month booked to <b>Short Amount (5293)</b>. Can be waived on one payslip. A cash <b>excess</b> (4250) is the employee's own money and is paid as an earning.</td></tr>
                            <tr><td>Death insurance, tax, loan, fine</td><td>Deduction</td><td>Standing deductions are set in the salary window; one-off ones (fine, tax) under Bonus &amp; deductions.</td></tr>
                        </tbody></table>
                        <div class="gh-note"><b>Net pay = total earnings − total deductions.</b> A negative net pay cannot be sent for approval. Press <b>Recalculate all</b> after changing a salary or an advance; lines you added yourself are kept.</div>
                    </div>
                    <div class="ev-ur-col gh-rtl" dir="rtl" lang="ur">
                        <table class="gh-table"><thead><tr><th>مد</th><th>طرف</th><th>کیسے</th></tr></thead><tbody>
                            <tr><td>بنیادی تنخواہ</td><td>آمدن</td><td>ملازم کے لیے طے شدہ ماہانہ رقم۔</td></tr>
                            <tr><td>الاؤنسز</td><td>آمدن</td><td>ہر ملازم کے لیے مقررہ رقم، بنیادی تنخواہ کا فیصد، یا کسی اور الاؤنس کا فیصد (مکان کرایہ، یوٹیلیٹی، میڈیکل، سفری وغیرہ)۔ نئی مد کبھی بھی شامل کی جا سکتی ہے۔</td></tr>
                            <tr><td>اوور ٹائم، انسینٹو، بونس</td><td>آمدن</td><td>جس مہینے ہو اسی مہینے ہاتھ سے شامل کریں۔</td></tr>
                            <tr><td>غیر حاضری</td><td>کٹوتی</td><td>صرف <b>Basic</b> قسم میں: بنیادی تنخواہ ÷ مہینے کے دن × غیر حاضر دن۔ <b>Fixed</b> قسم میں خود کوئی کٹوتی نہیں ہوتی۔</td></tr>
                            <tr><td>ایڈوانس</td><td>کٹوتی</td><td>ہر کھلے ایڈوانس سے خود کٹتا ہے: قسط، یا جو باقی ہو۔ بچی ہوئی تنخواہ سے زیادہ کبھی نہیں۔</td></tr>
                            <tr><td>سیٹلمنٹ شارٹ</td><td>کٹوتی</td><td>خود کٹتا ہے: مہینے کی پوسٹ شدہ سیٹلمنٹس میں جو رقم <b>Short Amount (5293)</b> میں گئی۔ کسی ایک سلپ پر معاف کی جا سکتی ہے۔ نقد <b>ایکسس</b> (4250) ملازم کی اپنی رقم ہے اور تنخواہ میں آمدن کے طور پر ملتی ہے۔</td></tr>
                            <tr><td>ڈیتھ انشورنس، ٹیکس، قرض، جرمانہ</td><td>کٹوتی</td><td>مستقل کٹوتیاں تنخواہ والی ونڈو میں؛ ایک بار والی (جرمانہ، ٹیکس) Bonus &amp; deductions میں۔</td></tr>
                        </tbody></table>
                        <div class="gh-note"><b>خالص تنخواہ = کل آمدن − کل کٹوتی۔</b> منفی خالص تنخواہ والی سلپ منظوری کے لیے نہیں بھیجی جا سکتی۔ تنخواہ یا ایڈوانس بدلنے کے بعد <b>Recalculate all</b> دبائیں؛ آپ کی ہاتھ سے لگائی ہوئی مدیں قائم رہتی ہیں۔</div>
                    </div>
                </div></div>
            </section>


            <section class="uf-card gh-card" aria-labelledby="pr-bonus">
                <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="pr-bonus">3a. Bonus, commission and new employees</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۳الف۔ بونس، کمیشن اور نئے ملازمین</h2></header>
                <div class="uf-body"><div class="ev-cols">
                    <div class="ev-en" lang="en"><ol>
                        <li>Open <b>Payroll → Bonus &amp; commission</b> and press <b>＋ Add bonus or commission</b>.</li>
                        <li>Pick the <b>month</b>, the <b>kind</b> (bonus, commission, incentive, overtime, fine...), the <b>amount</b> and one or many <b>employees</b>. Save.</li>
                        <li>When that month's payroll is built, the amount is on the employee's slip by itself. If the payroll is still a draft it appears at once.</li>
                        <li>Once the payroll is approved, the entry shows "In PR-..." and cannot be removed. Before that you can remove it.</li>
                        <li>Someone joined, or got a salary, after the payroll was built? Open the draft payroll and press <b>Add them to this payroll</b>: they get their slip with allowances, advances and any bonus waiting for the month.</li>
                    </ol>
                    <div class="gh-note">Anything for one employee only this once can also be typed directly on the slip with <b>Add this month</b>.</div></div>
                    <div class="ev-ur-col gh-rtl" dir="rtl" lang="ur"><ol>
                        <li><b>Payroll ← Bonus &amp; commission</b> کھولیں اور <b>"＋ Add bonus or commission"</b> دبائیں۔</li>
                        <li><b>مہینہ</b>، <b>قسم</b> (بونس، کمیشن، انسینٹو، اوور ٹائم، جرمانہ...)، <b>رقم</b> اور ایک یا کئی <b>ملازمین</b> چنیں، پھر محفوظ کریں۔</li>
                        <li>اس مہینے کا payroll بنتے ہی رقم ملازم کی سلپ میں خود شامل ہو جاتی ہے۔ اگر payroll ابھی مسودہ ہے تو فوراً نظر آ جاتی ہے۔</li>
                        <li>payroll منظور ہو جائے تو یہ اندراج "In PR-..." دکھاتا ہے اور ہٹایا نہیں جا سکتا۔ اس سے پہلے ہٹا سکتے ہیں۔</li>
                        <li>payroll بننے کے بعد کوئی نیا ملازم آیا یا کسی کی تنخواہ طے ہوئی؟ مسودہ payroll کھول کر <b>"Add them to this payroll"</b> دبائیں: اسے الاؤنس، ایڈوانس اور مہینے کے بونس سمیت سلپ مل جاتی ہے۔</li>
                    </ol>
                    <div class="gh-note">صرف ایک ملازم کو ایک بار کچھ دینا ہو تو سلپ پر <b>"Add this month"</b> سے بھی براہ راست لکھ سکتے ہیں۔</div></div>
                </div></div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="pr-ledger">
                <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="pr-ledger">3. What reaches the ledger</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۳۔ لیجر میں کیا جاتا ہے</h2></header>
                <div class="uf-body"><div class="ev-cols">
                    <div class="ev-en" lang="en">
                        <table class="gh-table"><thead><tr><th>Step</th><th>Debit</th><th>Credit</th></tr></thead><tbody>
                            <tr><td>Advance voucher approved</td><td>1131 Employee Advances</td><td>1121 Cash</td></tr>
                            <tr><td>Payroll approved</td><td>5281 Salary (all earnings)</td><td>1131 (advances recovered), 5293 (settlement short); Dr 4250 (settlement excess paid), 5281 (absent, fines), 2120 (tax), 1132 (loan), <b>2112 Payroll Payable (net)</b></td></tr>
                            <tr><td>Salaries paid</td><td>2112 Payroll Payable</td><td>1121 Cash or the bank account</td></tr>
                        </tbody></table>
                        <div class="gh-note">A cash salary payment shows in that day's <b>Cash Collection</b> report under "Less: salaries paid (cash)". A bank payment does not touch cash.</div>
                    </div>
                    <div class="ev-ur-col gh-rtl" dir="rtl" lang="ur">
                        <table class="gh-table"><thead><tr><th>مرحلہ</th><th>ڈیبٹ</th><th>کریڈٹ</th></tr></thead><tbody>
                            <tr><td>ایڈوانس واؤچر منظور</td><td><bdi class="gh-code">1131</bdi> Employee Advances</td><td><bdi class="gh-code">1121</bdi> Cash</td></tr>
                            <tr><td>Payroll منظور</td><td><bdi class="gh-code">5281</bdi> Salary (کل آمدن)</td><td><bdi class="gh-code">1131</bdi> (واپس ہوا ایڈوانس)، <bdi class="gh-code">5281</bdi> (غیر حاضری، جرمانہ)، <bdi class="gh-code">5293</bdi> (سیٹلمنٹ شارٹ)، <bdi class="gh-code">2120</bdi> (ٹیکس)، <bdi class="gh-code">1132</bdi> (قرض)، <b><bdi class="gh-code">2112</bdi> Payroll Payable (خالص)</b></td></tr>
                            <tr><td>تنخواہیں ادا</td><td><bdi class="gh-code">2112</bdi> Payroll Payable</td><td><bdi class="gh-code">1121</bdi> Cash یا بینک اکاؤنٹ</td></tr>
                        </tbody></table>
                        <div class="gh-note">نقد تنخواہ کی ادائیگی اسی دن کی <b>Cash Collection</b> رپورٹ میں "Less: salaries paid (cash)" کے تحت آتی ہے۔ بینک ادائیگی کیش کو نہیں چھوتی۔</div>
                    </div>
                </div></div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="pr-perm">
                <header class="uf-card-head ev-bihead"><h2 class="uf-card-title" id="pr-perm">4. Permissions</h2><h2 class="uf-card-title gh-rtl" dir="rtl" lang="ur">۴۔ اجازتیں</h2></header>
                <div class="uf-body"><div class="ev-cols">
                    <div class="ev-en" lang="en"><table class="gh-table"><tbody>
                        <tr><td><span class="gh-code">payroll-salary-manage</span></td><td>Set salaries, allowances and deductions.</td></tr>
                        <tr><td><span class="gh-code">payroll-list</span></td><td>See payroll runs and slips.</td></tr>
                        <tr><td><span class="gh-code">payroll-create</span></td><td>Build, adjust and send a run for approval (the maker).</td></tr>
                        <tr><td><span class="gh-code">payroll-approve</span></td><td>Approve or reject (a different user from the one who sent it).</td></tr>
                        <tr><td><span class="gh-code">payroll-adjustment-manage</span></td><td>Plan bonus, commission and other extra pay for a month.</td></tr>
                        <tr><td><span class="gh-code">payroll-pay</span></td><td>Mark salaries as paid.</td></tr>
                        <tr><td><span class="gh-code">payroll-view-all / payroll-view-company</span></td><td>See every company's payroll, or only the user's own company's. Without either, a user sees only the runs they made. Admins see all.</td></tr>
                    </tbody></table></div>
                    <div class="ev-ur-col gh-rtl" dir="rtl" lang="ur"><table class="gh-table"><tbody>
                        <tr><td><bdi class="gh-code">payroll-salary-manage</bdi></td><td>تنخواہ، الاؤنس اور کٹوتیاں طے کرنا۔</td></tr>
                        <tr><td><bdi class="gh-code">payroll-list</bdi></td><td>payroll اور سلپیں دیکھنا۔</td></tr>
                        <tr><td><bdi class="gh-code">payroll-create</bdi></td><td>payroll بنانا، تبدیل کرنا اور منظوری کے لیے بھیجنا (میکر)۔</td></tr>
                        <tr><td><bdi class="gh-code">payroll-approve</bdi></td><td>منظور یا مسترد کرنا (بھیجنے والے سے مختلف صارف)۔</td></tr>
                        <tr><td><bdi class="gh-code">payroll-adjustment-manage</bdi></td><td>مہینے کے لیے بونس، کمیشن اور اضافی رقم طے کرنا۔</td></tr>
                        <tr><td><bdi class="gh-code">payroll-pay</bdi></td><td>تنخواہیں ادا ہونے کا نشان لگانا۔</td></tr>
                        <tr><td><bdi class="gh-code">payroll-view-all / payroll-view-company</bdi></td><td>سب کمپنیوں کا یا صرف اپنی کمپنی کا payroll دیکھنا۔ دونوں نہ ہوں تو صارف صرف اپنے بنائے ہوئے دیکھتا ہے۔ ایڈمن کو سب نظر آتا ہے۔</td></tr>
                    </tbody></table></div>
                </div></div>
            </section>
        </div>
    </div>
</x-app-layout>

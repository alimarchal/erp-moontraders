<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بینک سٹیٹمنٹ رجسٹر — استعمال کی رہنمائی</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600&family=Noto+Naskh+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #1e2a5a; --line: #e2e8f0; --soft: #f8fafc; --text: #0f172a; --muted: #475569; --green: #166534; }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 70px; }
        body { margin: 0; background: #f1f5f9; color: var(--text); font-family: 'Noto Naskh Arabic', 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', Tahoma, serif; font-size: 18px; line-height: 2; }
        .bar { position: sticky; top: 0; z-index: 5; display: flex; gap: 10px; justify-content: space-between; align-items: center; padding: 10px 24px; background: #fff; border-bottom: 1px solid var(--line); }
        .bar h1 { margin: 0; font-family: 'Noto Nastaliq Urdu', 'Noto Naskh Arabic', serif; font-size: 22px; color: var(--navy); }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 18px; border: 1px solid var(--navy); border-radius: 8px; background: var(--navy); color: #fff; font: inherit; font-size: 16px; cursor: pointer; text-decoration: none; }
        .btn.light { background: #fff; color: var(--navy); }
        main { max-width: 940px; margin: 24px auto; padding: 0 16px 48px; }
        section { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 18px 24px; margin-bottom: 18px; }
        h2 { margin: 0 0 8px; font-size: 22px; color: var(--navy); font-family: 'Noto Nastaliq Urdu', 'Noto Naskh Arabic', serif; line-height: 2.2; }
        h3 { margin: 14px 0 4px; font-size: 19px; color: var(--green); }
        p { margin: 6px 0; }
        ol, ul { margin: 6px 0; padding-inline-start: 26px; }
        li { margin: 4px 0; }
        .note { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px; padding: 8px 14px; margin: 10px 0; font-size: 17px; }
        .tip { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 8px 14px; margin: 10px 0; font-size: 17px; }
        .warn { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 8px 14px; margin: 10px 0; font-size: 17px; }
        figure { margin: 12px 0; }
        figure img { width: 100%; height: auto; border: 1px solid var(--line); border-radius: 10px; display: block; }
        figcaption { margin-top: 4px; font-size: 15px; color: var(--muted); text-align: center; }
        table { width: 100%; border-collapse: collapse; font-size: 16px; line-height: 1.9; margin: 8px 0; }
        th, td { border: 1px solid var(--line); padding: 6px 10px; text-align: right; vertical-align: top; }
        th { background: var(--soft); }
        .en { direction: ltr; unicode-bidi: isolate; font-family: Inter, Arial, sans-serif; font-size: 0.85em; background: #eef2ff; border-radius: 6px; padding: 0 6px; white-space: nowrap; }
        code.en { background: #f1f5f9; }
        .badge { display: inline-block; padding: 0 12px; border-radius: 999px; font-size: 15px; }
        .b-gray { background: #f3f4f6; color: #374151; } .b-blue { background: #dbeafe; color: #1e40af; } .b-amber { background: #fef3c7; color: #92400e; }
        .b-red { background: #fee2e2; color: #991b1b; } .b-green { background: #dcfce7; color: #166534; } .b-teal { background: #ccfbf1; color: #115e59; } .b-purple { background: #f3e8ff; color: #6b21a8; }
        .flow { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 10px 0; }
        .flow span.step { background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 10px; padding: 2px 12px; font-size: 16px; }
        .toc a { color: var(--navy); text-decoration: none; } .toc a:hover { text-decoration: underline; }
        .toc ol { columns: 2; }
        @media (max-width: 640px) { .toc ol { columns: 1; } body { font-size: 17px; } }
        @media print { .bar .btn { display: none; } body { background: #fff; font-size: 15px; } section { break-inside: avoid; border-color: #cbd5e1; } .bar { position: static; } }
    </style>
</head>
<body>
    <div class="bar">
        <h1>بینک سٹیٹمنٹ رجسٹر — استعمال کی رہنمائی</h1>
        <div style="display:flex; gap:8px">
            <button type="button" class="btn" onclick="window.print()">🖨 پرنٹ کریں</button>
            <button type="button" class="btn light" onclick="window.close()">بند کریں</button>
        </div>
    </div>

    <main>
        <section>
            <h2>بینک سٹیٹمنٹ رجسٹر کیا ہے؟</h2>
            <p>بینک کی سٹیٹمنٹ کی ہر انٹری (ہر جمع اور ہر نکلوائی گئی رقم) کو یہاں لایا جاتا ہے، اس پر <b>کمپنی اور کیٹیگری</b> لگائی جاتی ہے، <b>رسید اور اٹیچمنٹ</b> محفوظ ہوتی ہے، اور <b>ڈبل انٹری</b> کے ذریعے کھاتوں میں پوسٹ ہوتی ہے۔ آخر میں مہینے کا بینک ریکنسیلی ایشن کر کے مدت <b>لاک</b> کر دی جاتی ہے۔ پرانی ایکسل شیٹ کا سارا کام اب نظام کے اندر ہوتا ہے اور کسی بھی تاریخ، کمپنی، رقم یا لفظ سے تلاش ہو سکتا ہے۔</p>
            <div class="flow">
                <span class="step">امپورٹ</span> ←
                <span class="step">کمپنی / کیٹیگری</span> ←
                <span class="step">رسید + اٹیچمنٹ</span> ←
                <span class="step">میکر بھیجے (<span class="en">Submit</span>)</span> ←
                <span class="step">چیکر منظور کرے</span> ←
                <span class="step">پوسٹنگ (ڈبل انٹری)</span> ←
                <span class="step">ریکنسیلی ایشن + لاک</span>
            </div>
            <div class="toc">
                <b>فہرست:</b>
                <ol>
                    <li><a href="#roles">کون کیا کرتا ہے</a></li>
                    <li><a href="#daily">روزانہ کا آسان طریقہ</a></li>
                    <li><a href="#import">سٹیٹمنٹ امپورٹ کرنا</a></li>
                    <li><a href="#manual">ہاتھ سے انٹری شامل کرنا</a></li>
                    <li><a href="#scans">رسیدوں کا اسکین ان باکس</a></li>
                    <li><a href="#register">رجسٹر اور تلاش</a></li>
                    <li><a href="#classify">کمپنی اور کیٹیگری لگانا</a></li>
                    <li><a href="#attach">رسید اور اٹیچمنٹ</a></li>
                    <li><a href="#maker">میکر: منظوری کے لیے بھیجنا</a></li>
                    <li><a href="#checker">چیکر: منظور یا مسترد</a></li>
                    <li><a href="#posting">پوسٹنگ، ڈبل انٹری اور رجسٹر کے اکاؤنٹس</a></li>
                    <li><a href="#reverse">غلطی ہو جائے تو ریورس</a></li>
                    <li><a href="#recon">بینک ریکنسیلی ایشن</a></li>
                    <li><a href="#masters">سیٹنگز: کیٹیگری، الیاس، رولز، چیک</a></li>
                    <li><a href="#reports">رپورٹس</a></li>
                    <li><a href="#print">پرنٹ کرنا</a></li>
                    <li><a href="#faq">عام سوالات اور مسائل</a></li>
                </ol>
            </div>
            <figure><img src="{{ asset('icons-images/bank-register-help/21-settings-menu.jpg') }}" alt="مینو" loading="lazy"><figcaption>سیٹنگز کے صفحے پر "Bank Register" کا حصہ؛ اوپر مینو میں <span class="en">Bank Register</span> کا بٹن بھی ہے</figcaption></figure>
        </section>

        <section id="roles">
            <h2>کون کیا کرتا ہے</h2>
            <table>
                <tr><th>کردار</th><th>کیا کر سکتا ہے</th></tr>
                <tr><td>میکر / اکاؤنٹنٹ <span class="en">accountant</span></td><td>سٹیٹمنٹ امپورٹ، <b>ہاتھ سے انٹری شامل کرنا</b>، رسیدیں اسکین ان باکس میں ڈالنا اور انٹری سے جوڑنا، کمپنی/کیٹیگری لگانا، فائلیں لگانا، منظوری کے لیے بھیجنا۔ حد سے کم رقم والی انٹری خود پوسٹ کر سکتا ہے۔ تمام رپورٹیں دیکھ سکتا ہے۔</td></tr>
                <tr><td>چیکر <span class="en">bank-checker</span></td><td>بھیجی گئی انٹری منظور یا مسترد کرنا، ریورس کرنا، ریکنسیلی ایشن مکمل کرنا۔ منظوری سے پہلے انٹری کی <b>رسید اور اٹیچمنٹ کھول کر دیکھ سکتا ہے</b> (ان باکس میں نام پر کلک کریں)۔ تمام رپورٹیں دیکھ سکتا ہے۔</td></tr>
                <tr><td>سینئر منظور کنندہ <span class="en">bank-senior-approver</span></td><td>بہت بڑی رقم کی دوسری منظوری؛ رسید دیکھ سکتا ہے۔ منظوری، لیجر، ٹاپ پارٹیز، کیش فلو اور اکاؤنٹس کی رپورٹیں دیکھ سکتا ہے۔</td></tr>
                <tr><td>آڈیٹر <span class="en">auditor</span></td><td>سب کچھ دیکھنا (رسیدیں سمیت)، ایکسپورٹ اور تمام رپورٹیں۔ کچھ بدل نہیں سکتا۔</td></tr>
                <tr><td>ایڈمن</td><td>سب کچھ، اور لوگوں کو اجازتیں دینا (<span class="en">Settings › Roles</span>)۔</td></tr>
            </table>
            <div class="note"><b>اصول:</b> جس نے انٹری بھیجی (میکر)، وہ اسی کو خود منظور نہیں کر سکتا۔ اور ایک ہی آدمی دونوں منظوریاں نہیں دے سکتا۔</div>
        </section>


        <section id="daily">
            <h2>روزانہ کا آسان طریقہ</h2>
            <p>آپ کے ہاں کئی اکاؤنٹس ہیں، رقم آتی جاتی رہتی ہے، رسیدیں کاغذ پر آتی ہیں اور اکثر گم ہو جاتی ہیں۔ اس لیے سارا کام <b>اسی دن</b> کریں:</p>
            <ol>
                <li><b>صبح / شام:</b> دن بھر کی رسیدیں، پرچیاں اور چیک کی کاپیاں موبائل سے فوٹو یا اسکین کر کے <span class="en">Scan inbox</span> میں ڈال دیں۔ ایک ساتھ بیسیوں فائلیں ڈالی جا سکتی ہیں۔</li>
                <li><b>انٹری بنائیں:</b> ہر رقم کے لیے <span class="en">Add entry</span> دبائیں (یا ان باکس کی رسید پر <span class="en">Create entry from this scan</span>)۔ رقم، تاریخ، پارٹی لکھیں۔ <span class="en">Save &amp; add another</span> سے اگلی انٹری فوراً کھل جاتی ہے۔</li>
                <li><b>جب سٹیٹمنٹ آئے:</b> امپورٹ کریں۔ جو انٹریاں آپ پہلے ہاتھ سے ڈال چکے ہیں انہیں ریکنسیلی ایشن میں سٹیٹمنٹ کی لائنوں سے ملایا جاتا ہے۔</li>
                <li><b>رسید رہ گئی ہو؟</b> رجسٹر پر پیلا بٹن <span class="en">📎 No receipt</span> ایسی تمام انٹریاں دکھاتا ہے۔</li>
                <li><b>ہفتے کے آخر میں:</b> رپورٹ <span class="en">All accounts at a glance</span> سے ہر اکاؤنٹ کی حالت دیکھیں اور <span class="en">Scanned receipts not yet linked</span> سے وہ رسیدیں جو ابھی کسی انٹری سے نہیں جڑیں۔</li>
            </ol>
            <div class="tip">اکاؤنٹ بینک کا ہو، کیش کا یا موبائل والٹ کا؛ <span class="en">Settings › Bank Accounts</span> میں اسے بنا لیں تو وہ رجسٹر میں اپنے بٹن کے ساتھ آ جاتا ہے۔ ہر اکاؤنٹ کا ریکارڈ، رپورٹ اور ریکنسیلی ایشن الگ ہوتا ہے۔</div>
        </section>

        <section id="import">
            <h2>سٹیٹمنٹ امپورٹ کرنا</h2>
            <ol>
                <li>اوپر <span class="en">Bank Register</span> کھولیں اور <span class="en">Import statement</span> دبائیں۔</li>
                <li><span class="en">Bank account</span> چنیں اور ایکسل یا <span class="en">CSV</span> فائل لگائیں۔ فائل میں کم از کم <span class="en">Posting date</span> اور <span class="en">Debit / Credit</span> کے کالم ہونے چاہییں۔ ترتیب کوئی بھی ہو سکتی ہے۔</li>
                <li>پرنٹ شدہ سٹیٹمنٹ کے اعداد لکھ دیں: <span class="en">Opening / Closing balance</span> اور کل <span class="en">Debits / Credits</span>۔ <b>یہ ضرور لکھیں</b>، کیونکہ نظام فائل کا جوڑ نکال کر ان سے ملاتا ہے۔</li>
                <li><span class="en">Read file &amp; show summary</span> دبائیں۔ اب خلاصہ نظر آئے گا: کتنی قطاریں نئی ہیں، کتنی پہلے سے موجود ہیں، کتنی میں غلطی ہے۔</li>
                <li>سبز <span class="badge b-green">Tallies</span> ہو تو <span class="en">Confirm import</span> دبائیں۔ اگر سرخ <span class="badge b-red">Does not tally</span> ہو تو امپورٹ بند رہتی ہے؛ فائل یا اعداد درست کر کے دوبارہ لگائیں۔</li>
            </ol>
            <figure><img src="{{ asset('icons-images/bank-register-help/04-import-form.jpg') }}" alt="امپورٹ فارم" loading="lazy"><figcaption>پہلا قدم: فائل اور سٹیٹمنٹ کے اعداد</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/05-import-preview.jpg') }}" alt="امپورٹ کا خلاصہ" loading="lazy"><figcaption>دوسرا قدم: خلاصہ اور جوڑ کی جانچ</figcaption></figure>
            <div class="tip">ایک ہی فائل دوبارہ لگ جائے تو پرانی انٹریاں دوبارہ نہیں بنتیں؛ انہیں <span class="en">Already in system</span> میں گن کر چھوڑ دیا جاتا ہے۔ زرد قطاریں وہ ہیں جن کا نام نامعلوم (<span class="en">Unidentified</span>) تھا اور جائزہ لینا باقی ہے۔</div>
            <figure><img src="{{ asset('icons-images/bank-register-help/23-import-list.jpg') }}" alt="امپورٹس کی فہرست" loading="lazy"><figcaption>پرانی امپورٹس کی فہرست</figcaption></figure>
        </section>


        <section id="manual">
            <h2>۲۔ ہاتھ سے انٹری شامل کرنا</h2>
            <p>اگر سٹیٹمنٹ فائل نہیں ہے، یا رقم آج ہی لکھنی ہے، تو انٹری ہاتھ سے بن سکتی ہے۔ رجسٹر کے اوپر سبز بٹن <span class="en">＋ Add entry</span> دبائیں۔</p>
            <ol>
                <li><b>رقم آئی یا گئی:</b> دو بڑے بٹن <span class="en">＋ Received</span> اور <span class="en">− Paid out</span> میں سے ایک دبائیں اور رقم لکھیں۔ رقم والا خانہ پہلے ہی سے چنا ہوتا ہے، فوراً لکھنا شروع کریں۔</li>
                <li>بینک اکاؤنٹ اور تاریخ چنیں (آج کی تاریخ پہلے سے لگی ہوتی ہے، آنے والی تاریخ نہیں چلتی)۔</li>
                <li>پارٹی کا نام اور ریفرنس / چیک نمبر لکھیں۔ اگر اس نام کا الیاس بنا ہوا ہو تو کمپنی خود لگ جاتی ہے۔</li>
                <li>چاہیں تو کمپنی اور کیٹیگری ابھی چن لیں، ورنہ <span class="en">decide later</span> رہنے دیں۔ کیٹیگریاں رقم کی سمت کے حساب سے خود چھن جاتی ہیں۔</li>
                <li>رسید فوراً لگانا چاہیں تو اسی فارم میں فائل چنیں۔</li>
                <li>تین بٹن: <span class="en">Save</span>، <span class="en">Save &amp; add another</span> (اگلی انٹری، وہی اکاؤنٹ اور تاریخ اور رقم کی سمت کے ساتھ) اور <span class="en">Save &amp; submit</span> (فوراً منظوری کے لیے بھیج دے، چھوٹی رقم ہو تو پوسٹ بھی کر دے)۔</li>
            </ol>
            <figure><img src="{{ asset('icons-images/bank-register-help/24-add-entry.jpg') }}" alt="نئی انٹری" loading="lazy"><figcaption>ہاتھ سے نئی انٹری</figcaption></figure>
            <div class="note"><b>دوبارہ تو نہیں ڈل گئی؟</b> اگر اسی اکاؤنٹ، تاریخ اور رقم کی انٹری پہلے سے ہو تو نظام پیلا انتباہ دکھاتا ہے اور موجودہ انٹری کا لنک دیتا ہے۔ اگر واقعی الگ لین دین ہو تو "<span class="en">save it anyway</span>" پر ٹک لگا کر محفوظ کریں۔</div>
            <ul>
                <li>غلطی ہو جائے تو پوسٹ ہونے سے پہلے <span class="en">Edit</span> میں تاریخ، رقم، سمت، پارٹی اور ریفرنس درست ہو سکتے ہیں۔ رقم یا کیٹیگری بدلنے پر منظوری دوبارہ لینی پڑتی ہے۔</li>
                <li>سٹیٹمنٹ سے امپورٹ ہونے والی انٹری کی رقم، تاریخ اور ریفرنس نہیں بدلتے (وہی جو بینک نے چھاپا)؛ صرف کمپنی، کیٹیگری اور نوٹ بدلتے ہیں۔</li>
                <li>پوسٹ ہو چکی انٹری ریورس ہوتی ہے، حذف نہیں۔ جو ابھی پوسٹ نہیں ہوئی اسے حذف کیا جا سکتا ہے۔</li>
                <li>امپورٹ کے لیے ایکسل / <span class="en">CSV</span> میں <span class="en">Debit / Credit</span> کے دو کالم ہوں، یا ایک <span class="en">Amount</span> کالم جس کے ساتھ <span class="en">Dr/Cr</span> لکھا ہو یا منفی رقم؛ تاریخ <span class="en">15/10/2026</span> یا <span class="en">15-Oct-2026</span> دونوں چلتی ہیں۔</li>
            </ul>
        </section>

        <section id="scans">
            <h2>۳۔ رسیدوں کا اسکین ان باکس</h2>
            <p>رسید ملتے ہی فوٹو/اسکین ان باکس میں ڈال دیں، چاہے بینک لائن ابھی نہ بنی ہو۔ اس طرح رسید کبھی گم نہیں ہوتی۔</p>
            <ol>
                <li><span class="en">Bank Register › Scan inbox</span> کھولیں۔</li>
                <li>فائلیں خانے میں گھسیٹ کر چھوڑیں (یا کلک کر کے چنیں)، رسید کی تاریخ لکھیں، اکاؤنٹ اور نوٹ چاہیں تو لکھیں۔ <b>ایک ہی فائل ہو تو رقم اور سمت بھی لکھ دیں</b>، اس سے نظام خود ملتی جلتی انٹری ڈھونڈ لیتا ہے۔</li>
                <li><span class="en">Save to inbox</span> دبائیں۔ ہر فائل کا اپنا کارڈ بنتا ہے۔</li>
                <li>جب انٹری مل جائے: ہرے ڈبے میں <span class="en">Looks like …</span> نظر آئے تو <span class="en">Attach here</span> دبائیں؛ نہ ملے تو <span class="en">Create entry from this scan</span> (انٹری بن جائے گی اور رسید خود لگ جائے گی) یا انٹری نمبر لکھ کر <span class="en">Link</span>۔</li>
            </ol>
            <figure><img src="{{ asset('icons-images/bank-register-help/25-scan-inbox.jpg') }}" alt="اسکین ان باکس" loading="lazy"><figcaption>اسکین ان باکس: بائیں طرف فائلیں ڈالنے کا خانہ، نیچے رسیدوں کے کارڈ</figcaption></figure>
            <div class="tip">جڑنے کے بعد فائل انٹری کی عام اٹیچمنٹ بن جاتی ہے (قسم "رسید")، اور انٹری کی <span class="en">History</span> میں لکھا جاتا ہے۔ ریکنسائل شدہ انٹری میں رسید نہیں جوڑی جا سکتی۔</div>
        </section>

        <section id="register">
            <h2>۴۔ رجسٹر اور تلاش</h2>
            <p>ہر صفحے کے اوپر ایک پٹی ہے: <span class="en">Register · Approvals · Receipt scans · Reconciliation · Reports · Ledger accounts · Categories &amp; rules</span>۔ موجودہ صفحہ گہرے نیلے رنگ میں ہوتا ہے، اور <span class="en">Approvals</span> / <span class="en">Receipt scans</span> کے ساتھ باقی کام کی گنتی۔ وہی حصے نظر آتے ہیں جن کی آپ کو اجازت ہے۔</p>
            <p><b>رجسٹر کی اسکرین:</b> اوپر چار خانے کام کا راستہ دکھاتے ہیں: ۱ درجہ بندی باقی ← ۲ منظوری کے انتظار میں ← ۳ پوسٹ شدہ ← ۴ ریکنسائل شدہ۔ ہر خانے میں تعداد اور رقم ہے؛ اس پر کلک کریں تو اسی حالت کی انٹریاں کھلتی ہیں، دوبارہ کلک کریں تو فلٹر ہٹ جاتا ہے۔ ٹیبل کے اوپر موجودہ فلٹر کے کل: انٹریاں، وصولی، ادائیگی اور نیٹ، اور فوری <span class="en">Search</span> کا خانہ۔ ٹیبل کی عنوان کی قطار نیچے سکرول کرنے پر بھی اوپر رہتی ہے۔ <b>Files</b> کے خانے میں 📎 کے ساتھ فائلوں کی گنتی ہے، اور <b>پیلا نقطہ</b> کا مطلب ہے کہ اس انٹری کی رسید ابھی نہیں لگی۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/34-subnav.jpg') }}" alt="اوپر کی پٹی" loading="lazy"><figcaption>ہر صفحے پر اوپر کی پٹی</figcaption></figure>
            <p>رجسٹر کی سکرین پر سب سے اوپر چار ڈبے ہیں: <b>درجہ بندی باقی</b>، <b>منظوری کے انتظار میں</b>، <b>پوسٹ مگر ریکنسائل نہیں</b>، اور <b>ریکنسائل</b>۔ ہر ڈبے پر کلک کریں تو صرف وہی انٹریاں دکھتی ہیں۔ ان کے نیچے حالت کی گولیاں (<span class="en">chips</span>) ہیں جن پر تعداد اور رقم لکھی ہے۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/01-register.jpg') }}" alt="رجسٹر" loading="lazy"><figcaption>رجسٹر: ڈبے، حالت کی گولیاں اور انٹریوں کی فہرست</figcaption></figure>
            <h3>تلاش کے فلٹر</h3>
            <p><span class="en">Filters</span> بٹن دبائیں۔ آپ ان سے تلاش کر سکتے ہیں:</p>
            <ul>
                <li>کوئی بھی لفظ: پارٹی، نریشن، ریفرنس، رسید نمبر، نوٹ، یا اٹیچمنٹ کا نام (اردو بھی چلتی ہے)</li>
                <li>تاریخ سے تاریخ تک، بینک اکاؤنٹ، <b>کمپنی</b>، کیٹیگری</li>
                <li>رقم سے رقم تک، جمع یا نکلوائی، چیک نمبر، رسید نمبر</li>
                <li>اٹیچمنٹ ہے یا نہیں، جائزے کا نشان، کس میکر نے بھیجی</li>
            </ul>
            <figure><img src="{{ asset('icons-images/bank-register-help/02-filters.jpg') }}" alt="فلٹر" loading="lazy"><figcaption>فلٹر کا خانہ</figcaption></figure>
            <p>کسی بھی قطار پر کلک کریں تو دائیں طرف <b>تفصیل کی پٹی</b> کھلتی ہے جس میں انٹری کی ہر بات، منظوریاں، اٹیچمنٹ اور تاریخچہ ہے۔ <span class="en">Esc</span> سے بند ہوتی ہے۔ <span class="en">Export</span> سے موجودہ فلٹر والی انٹریاں ایکسل میں آ جاتی ہیں۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/03-drawer.jpg') }}" alt="تفصیل کی پٹی" loading="lazy"><figcaption>تفصیل کی پٹی</figcaption></figure>
        </section>

        <section id="classify">
            <h2>۵۔ کمپنی اور کیٹیگری لگانا</h2>
            <div class="tip"><b>کمپنی</b> سے مراد پرنسپل / سپلائر (مثلاً یونی لیور، ڈالڈا، اینگرو) ہے۔ فہرست سسٹم کی <span class="en">Suppliers</span> سے آتی ہے، اس لیے وہی نام ہر جگہ چلتا ہے۔ ہر <b>ڈراپ ڈاؤن</b> میں تلاش کا خانہ ہے: ڈبے پر کلک کریں، چند حروف ٹائپ کریں، اور نتیجے پر کلک کریں۔ خانہ خالی کرنے کے لیے ✕ دبائیں۔</div>
            <figure><img src="{{ asset('icons-images/bank-register-help/31-select2-dropdown.jpg') }}" alt="ڈراپ ڈاؤن میں تلاش" loading="lazy"><figcaption>ڈراپ ڈاؤن میں ٹائپ کر کے تلاش</figcaption></figure>
            <h3>ایک ایک کر کے</h3>
            <p>تفصیل کی پٹی میں <span class="en">Edit / classify</span> دبائیں، کمپنی اور کیٹیگری چنیں، چاہیں تو نوٹ لکھیں اور <span class="en">Save</span> دبائیں۔ کیٹیگری لگتے ہی انٹری <span class="badge b-blue">Classified</span> ہو جاتی ہے۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/07-edit-entry.jpg') }}" alt="انٹری کی درجہ بندی" loading="lazy"><figcaption>انٹری کی درجہ بندی</figcaption></figure>
            <h3>بہت سی انٹریاں ایک ساتھ</h3>
            <ol>
                <li>بائیں طرف کے خانوں میں ٹک لگائیں (سب کے لیے سب سے اوپر والا خانہ)۔</li>
                <li>اوپر نیلی پٹی آئے گی۔ کمپنی اور/یا کیٹیگری چنیں۔</li>
                <li><span class="en">Remember for next time</span> پر ٹک لگائیں تو نظام اس پارٹی کا <b>الیاس اور رول</b> خود بنا لیتا ہے، اگلی بار یہی نام خود بخود لگ جائے گا۔</li>
                <li><span class="en">Apply</span> دبائیں۔</li>
            </ol>
            <figure><img src="{{ asset('icons-images/bank-register-help/06-classify-bulk.jpg') }}" alt="اکٹھی درجہ بندی" loading="lazy"><figcaption>ٹک لگا کر اکٹھی درجہ بندی</figcaption></figure>
            <div class="tip"><span class="en">⚡ Auto-classify unclassified</span> دبائیں تو نظام الیاس، رولز اور چیک رجسٹر تینوں کو تمام باقی انٹریوں پر چلا دیتا ہے۔ چیک نمبر اور رقم ملنے پر چیک خود <span class="en">Cleared</span> ہو جاتا ہے۔</div>
        </section>

        <section id="attach">
            <h2>۶۔ رسید اور اٹیچمنٹ</h2>
            <p>تفصیل کی پٹی کے نیچے <span class="en">Receipt &amp; attachments</span> میں فائل (پی ڈی ایف، تصویر، ایکسل) چنیں، قسم (<span class="en">Receipt / Cheque / Deposit slip / Statement</span>) اور وضاحت لکھ کر <span class="en">Attach</span> دبائیں۔ ایک ساتھ دس تک فائلیں لگ سکتی ہیں۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/08-attachments.jpg') }}" alt="اٹیچمنٹ" loading="lazy"><figcaption>فائلیں اور وضاحت</figcaption></figure>
            <ul>
                <li>فائلیں نجی ہیں؛ صرف لاگ ان صارف جسے رجسٹر کی اجازت ہو وہی کھول سکتا ہے۔</li>
                <li>پوسٹ ہونے پر انٹری کو <b>رسید نمبر</b> ملتا ہے: <span class="en">BR-2024-25-000001</span> (رقم آئی) یا <span class="en">BP-2024-25-000001</span> (رقم گئی)۔ مالی سال جولائی سے جون ہے۔</li>
                <li>ریکنسائل ہو جانے کے بعد اٹیچمنٹ ہٹائی نہیں جا سکتی۔</li>
                <li>"<span class="en">Entries without receipt / attachment</span>" رپورٹ بتاتی ہے کہ کن انٹریوں کی فائل رہ گئی ہے۔</li>
            </ul>
        </section>

        <section id="maker">
            <h2>۷۔ میکر: منظوری کے لیے بھیجنا</h2>
            <p>درجہ بندی کے بعد <span class="en">Submit for approval</span> دبائیں (یا بہت سی ٹک کر کے <span class="en">Submit selected</span>)۔ آگے کیا ہوتا ہے، یہ رقم پر منحصر ہے:</p>
            <table>
                <tr><th>رقم</th><th>کیا ہوتا ہے</th></tr>
                <tr><td>۱۰ لاکھ سے کم <span class="en">(Level 1 سے کم)</span></td><td>فوراً پوسٹ، بٹن پر <span class="en">Post now</span> لکھا ہوتا ہے۔ کسی منظوری کی ضرورت نہیں۔</td></tr>
                <tr><td>۱۰ لاکھ یا زیادہ</td><td>چیکر کی منظوری ضروری ہے۔ حالت <span class="badge b-amber">Pending approval</span> ہو جاتی ہے۔</td></tr>
                <tr><td>۱ کروڑ یا زیادہ</td><td>چیکر کے بعد سینئر منظور کنندہ کی دوسری منظوری بھی ضروری ہے۔</td></tr>
            </table>
            <div class="note"><b>حدیں بدلنے کے لیے:</b> یہ اعداد کوڈ میں نہیں، سرور کی <span class="en">.env</span> فائل میں ہیں۔ ایڈمن بدل کر <span class="en">php artisan config:clear</span> چلا دے:
                <br><code class="en">BANK_APPROVAL_LEVEL1_THRESHOLD=1000000</code>
                <br><code class="en">BANK_APPROVAL_LEVEL2_THRESHOLD=10000000</code>
                <br><code class="en">BANK_MAKER_CHECKER_ENABLED=true</code> ← <span class="en">false</span> کریں تو سب انٹریاں میکر خود پوسٹ کرے گا
                <br><code class="en">BANK_ALLOW_SELF_APPROVAL=false</code> ← میکر اپنی انٹری منظور نہ کر سکے
                <br><code class="en">BANK_APPROVAL_APPLIES_TO=both</code> ← <span class="en">debit / credit / both</span>
            </div>
            <div class="warn">منظوری کے انتظار میں انٹری کی کمپنی یا کیٹیگری بدلی جائے تو پہلی منظوری ختم ہو جاتی ہے اور میکر کو دوبارہ بھیجنا پڑتا ہے۔</div>
        </section>

        <section id="checker">
            <h2>۸۔ چیکر: منظور یا مسترد</h2>
            <ol>
                <li>اوپر <span class="en">Approvals</span> کھولیں؛ سب سے بڑی رقم سب سے اوپر ہوتی ہے۔</li>
                <li>ہر کارڈ پر پارٹی، رقم، کس نے بھیجا، کتنی فائلیں لگی ہیں اور منظوریوں کی حالت (<span class="en">Checker / Senior</span>) نظر آتی ہے۔</li>
                <li><b>رسید دیکھیں:</b> کارڈ پر فائلوں کے نام (اور تصویروں کی چھوٹی جھلک) ہیں؛ ان پر کلک کریں تو رسید نئے ٹیب میں کھل جاتی ہے۔ اگر لکھا ہو "<span class="en">No receipt or file attached</span>" تو منظوری سے پہلے رسید مانگیں۔</li>
                <li>ٹھیک ہو تو <span class="en">Approve</span>۔ آخری ضروری منظوری کے ساتھ ہی انٹری خود پوسٹ ہو جاتی ہے اور رسید نمبر بن جاتا ہے۔</li>
                <li>غلط ہو تو وجہ لکھ کر <span class="en">Reject</span>۔ انٹری میکر کو واپس جاتی ہے <span class="badge b-red">Rejected</span> کے ساتھ وجہ دکھتی ہے۔</li>
                <li>بہت سی ایک ساتھ منظور کرنے کے لیے ٹک لگائیں اور <span class="en">Approve selected</span> دبائیں (ایک بار میں زیادہ سے زیادہ ۱۰۰)۔</li>
            </ol>
            <figure><img src="{{ asset('icons-images/bank-register-help/10-approvals.jpg') }}" alt="منظوری کا ان باکس" loading="lazy"><figcaption>چیکر کا ان باکس</figcaption></figure>
            <div class="tip">اپنی بھیجی ہوئی انٹری کے سامنے بٹن نہیں آتے؛ وہاں لکھا ہوتا ہے کہ کوئی اور منظور کرے گا۔</div>
        </section>

        <section id="posting">
            <h2>۹۔ پوسٹنگ، ڈبل انٹری اور رجسٹر کے اپنے اکاؤنٹس</h2>
            <p>پوسٹ ہوتے ہی نظام ایک متوازن <span class="en">Journal Entry</span> بناتا ہے (ڈیبٹ = کریڈٹ)، یعنی وہی جرنل سسٹم جو باقی ماڈیول استعمال کرتے ہیں۔ <b>لیکن رجسٹر کا حساب بالکل الگ ہے:</b> یہ صرف اپنے نئے اکاؤنٹس (کوڈ <span class="en">6000</span> سے شروع) استعمال کرتا ہے۔ جی آر این، سیٹلمنٹ، گڈز ایشو، کسٹمر/سپلائر یا بینک کے لائیو اکاؤنٹس کو نہ چھوا جاتا ہے نہ ان کا بیلنس بدلتا ہے۔</p>
            <div class="tip">رجسٹر کے اکاؤنٹس کی رپورٹ ٹائپ <span class="en">BankRegister</span> ہے، اس لیے <span class="en">Balance Sheet</span> اور <span class="en">Income Statement</span> میں شامل نہیں ہوتے۔ <span class="en">Account Balances</span>، <span class="en">Trial Balance</span> اور <span class="en">General Ledger</span> میں یہ اپنے الگ گروپ میں نظر آتے ہیں۔ اکاؤنٹس کی فہرست: <span class="en">Settings › Register Ledger Accounts</span>۔</div>
            <table>
                <tr><th>کوڈ</th><th>اکاؤنٹ</th><th>کب استعمال ہوتا ہے</th></tr>
                <tr><td class="en">6100+</td><td>ہر بینک / کیش / والٹ کے لیے الگ اکاؤنٹ (<span class="en">BR - HBL ...</span>)</td><td>بینک کی طرف ہمیشہ یہی؛ ریکنسیلی ایشن کا کتابی بیلنس بھی اسی سے</td></tr>
                <tr><td class="en">6300+</td><td>ہر کمپنی کا اپنا اکاؤنٹ (<span class="en">BR - کمپنی کا نام</span>)</td><td>کسٹمر رسید، پرنسپل/سپلائر ادائیگی اور <span class="en">Other</span> میں جب انٹری پر کمپنی لگی ہو</td></tr>
                <tr><td class="en">6201 / 6202</td><td>کسٹمر رسیدیں / کیش جمع</td><td>کمپنی نہ ہو تو</td></tr>
                <tr><td class="en">6203 / 6210</td><td>نامعلوم آمد / نامعلوم ادائیگی (<span class="en">suspense</span>)</td><td>جہاں اکاؤنٹ یا کمپنی ابھی طے نہیں؛ بعد میں درست کمپنی لگا کر ریورس اور دوبارہ انٹری</td></tr>
                <tr><td class="en">6204 – 6209</td><td>مالک، سپلائر کلیئرنگ، تنخواہ، بینک چارجز، ٹیکس، منتقلی</td><td>متعلقہ کیٹیگری</td></tr>
            </table>
            <table>
                <tr><th>معاملہ</th><th>ڈیبٹ</th><th>کریڈٹ</th></tr>
                <tr><td>کسٹمر/ریٹیلر سے رقم آئی</td><td>بینک کا رجسٹر اکاؤنٹ</td><td>کمپنی کا رجسٹر اکاؤنٹ (یا کسٹمر رسیدیں کلیئرنگ)</td></tr>
                <tr><td>پرنسپل / سپلائر کو ادائیگی</td><td>کمپنی کا رجسٹر اکاؤنٹ (یا سپلائر کلیئرنگ)</td><td>بینک کا رجسٹر اکاؤنٹ</td></tr>
                <tr><td>کیش جمع کروایا</td><td>بینک</td><td>کیش جمع کلیئرنگ</td></tr>
                <tr><td>بینک چارجز / ٹیکس / تنخواہ</td><td>متعلقہ رجسٹر اکاؤنٹ</td><td>بینک</td></tr>
                <tr><td>دو بینکوں کے بیچ منتقلی</td><td>وصول کرنے والا بینک</td><td>دینے والا بینک</td></tr>
                <tr><td>کیٹیگری نہ ہو یا کمپنی نامعلوم</td><td>بینک / نامعلوم ادائیگی</td><td>نامعلوم آمد / بینک</td></tr>
            </table>
            <p><b>بیان (narration):</b> جرنل کی تفصیل میں خود بخود لکھا جاتا ہے: رسید نمبر | کمپنی/پارٹی | بینک ریفرنس | سٹیٹمنٹ کی عبارت۔ اس سے ہر لائن کو رپورٹ میں پہچانا اور الگ نکالا جا سکتا ہے۔ جس اکاؤنٹ کی کیٹیگری یا کمپنی موجود نہ ہو وہاں انٹری رکی نہیں رہتی، سسپنس اکاؤنٹ میں جاتی ہے اور بیان اسے واضح کرتا ہے۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/28-register-ledger-accounts.jpg') }}" alt="رجسٹر کے اکاؤنٹس" loading="lazy"><figcaption>رجسٹر کے اپنے اکاؤنٹس اور ان کے بیلنس</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/11-posted.jpg') }}" alt="پوسٹ شدہ انٹری" loading="lazy"><figcaption>پوسٹ شدہ انٹری: رسید نمبر اور جرنل انٹری کا لنک</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/30-account-balances.jpg') }}" alt="اکاؤنٹ بیلنسز" loading="lazy"><figcaption>اکاؤنٹ بیلنسز میں رجسٹر کے اکاؤنٹس الگ گروپ میں</figcaption></figure>
            <p>پوسٹ شدہ انٹری مالی ریکارڈ کا حصہ ہے؛ اسے بدلا نہیں جا سکتا، صرف ریورس کیا جا سکتا ہے۔</p>
        </section>

        <section id="reverse">
            <h2>۱۰۔ غلطی ہو جائے تو ریورس</h2>
            <ol>
                <li>پوسٹ شدہ انٹری کی تفصیل کھولیں۔</li>
                <li>وجہ لکھ کر <span class="en">Reverse</span> دبائیں (صرف چیکر / جس کے پاس <span class="en">reverse</span> کی اجازت ہو)۔</li>
                <li>نظام الٹی جرنل انٹری پوسٹ کر دیتا ہے اور انٹری <span class="badge b-purple">Reversed</span> ہو جاتی ہے۔ اب اسے درست کر کے دوبارہ بھیجا جا سکتا ہے؛ رسید نمبر وہی رہتا ہے۔</li>
            </ol>
            <p>ریکنسائل شدہ انٹری ریورس نہیں ہو سکتی؛ پہلے ریکنسیلی ایشن واپس کرنی ہوگی۔ ہر قدم تفصیل کی پٹی کی <span class="en">History</span> میں لکھا جاتا ہے۔</p>
        </section>

        <section id="recon">
            <h2>۱۱۔ بینک ریکنسیلی ایشن</h2>
            <ol>
                <li><span class="en">Reconcile › New reconciliation</span> دبائیں۔ بینک اکاؤنٹ، مدت، اور سٹیٹمنٹ کی <span class="en">Opening / Closing balance</span> لکھیں۔</li>
                <li>جو انٹریاں رجسٹر سے پوسٹ ہوئی ہیں وہ خود جوڑ دی جاتی ہیں۔</li>
                <li>باقی رہ جانے والی سٹیٹمنٹ لائنیں بائیں طرف ہیں، اور کتابوں کی وہ لائنیں جو سٹیٹمنٹ میں نہیں (راستے میں جمع / بقایا ادائیگیاں) دائیں طرف۔ نظام اسی رقم اور تین دن کے اندر والی لائن تجویز کرتا ہے؛ <span class="en">Match</span> دبا دیں۔</li>
                <li>اوپر <span class="en">Difference</span> کا ڈبہ دیکھیں۔ سبز اور صفر ہو جائے اور کوئی لائن باقی نہ ہو تو <span class="en">Complete &amp; lock period</span> دبائیں۔</li>
            </ol>
            <figure><img src="{{ asset('icons-images/bank-register-help/13-reconciliation-create.jpg') }}" alt="نئی ریکنسیلی ایشن" loading="lazy"><figcaption>نئی ریکنسیلی ایشن شروع کرنا</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/12-reconciliation-show.jpg') }}" alt="ریکنسیلی ایشن کی سکرین" loading="lazy"><figcaption>دونوں طرف کی لائنیں اور فرق کا ڈبہ</figcaption></figure>
            <div class="note">مکمل ہونے پر سب انٹریاں <span class="badge b-teal">Reconciled</span> ہو کر لاک ہو جاتی ہیں اور جرنل لائنیں بھی ریکنسائل ہو جاتی ہیں۔ غلطی ملے تو <span class="en">Undo reconciliation</span> سے مدت کھل جاتی ہے۔</div>
        </section>

        <section id="masters">
            <h2>۱۲۔ سیٹنگز: کیٹیگری، الیاس، رولز، چیک</h2>
            <p><span class="en">Settings › Bank Register</span> میں یہ فہرستیں ہیں۔ ہر ایک میں نیا بنانا، بدلنا اور (اجازت ہو تو) حذف کرنا ہے۔</p>
            <h3>انٹری کیٹیگریاں <span class="en">Bank Entry Categories</span></h3>
            <p>جیسے <i>Customer Receipt</i>، <i>Principal Payment</i>، <i>Bank Charges</i>۔ ہر کیٹیگری کے لیے طے کریں کہ وہ رقم آنے کے لیے ہے یا جانے کے لیے، اور چاہیں تو اس کا اپنا اکاؤنٹ (<span class="en">Own ledger account</span>) لگائیں؛ نہ لگائیں تو رجسٹر کا طے شدہ اکاؤنٹ استعمال ہوگا۔ جو کیٹیگری انٹریوں میں استعمال ہو چکی ہو اسے حذف نہیں، <b>غیر فعال</b> کریں۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/14-categories.jpg') }}" alt="کیٹیگریاں" loading="lazy"><figcaption>کیٹیگریوں کی فہرست</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/22-category-form.jpg') }}" alt="کیٹیگری کا فارم" loading="lazy"><figcaption>نئی کیٹیگری</figcaption></figure>
            <h3>پارٹی الیاس <span class="en">Party Aliases</span></h3>
            <p>ایک ہی شخص کے نام کئی طرح لکھے آتے ہیں (<span class="en">JAMEEL AHMED / JAMEEL AHM</span>)۔ الیاس بنا دیں کہ یہ سب ایک ہی صحیح نام اور ایک ہی کمپنی ہیں۔ حروف کا چھوٹا بڑا ہونا اور فالتو جگہیں نظر انداز ہوتی ہیں۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/15-aliases.jpg') }}" alt="الیاس" loading="lazy"><figcaption>الیاس کی فہرست</figcaption></figure>
            <h3>خودکار رولز <span class="en">Auto-classify Rules</span></h3>
            <p>"نریشن میں <span class="en">Service Charges</span> ہو تو کیٹیگری <span class="en">Bank Charges</span>" جیسے اصول۔ <span class="en">Priority</span> میں چھوٹا نمبر پہلے چلتا ہے۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/16-rules.jpg') }}" alt="رولز" loading="lazy"><figcaption>رولز کی فہرست</figcaption></figure>
            <h3>چیک رجسٹر <span class="en">Bank Cheque Register</span></h3>
            <p>جاری کیے گئے اور وصول ہونے والے چیک لکھیں۔ سٹیٹمنٹ کے ریفرنس میں چیک نمبر اور وہی رقم آئے تو چیک خود <span class="en">Cleared</span> ہو جاتا ہے اور سٹیٹمنٹ لائن سے جُڑ جاتا ہے۔</p>
            <figure><img src="{{ asset('icons-images/bank-register-help/17-cheques.jpg') }}" alt="چیک رجسٹر" loading="lazy"><figcaption>چیک رجسٹر</figcaption></figure>
        </section>

        <section id="reports">
            <h2>۱۳۔ رپورٹس</h2>
            <p><span class="en">Reports › Bank Statement Reports</span> سے کھلتی ہیں۔ ہر رپورٹ میں تاریخ، بینک اکاؤنٹ، کمپنی کا فلٹر ہے اور ایکسل / پی ڈی ایف بٹن موجود ہیں۔</p>
            <table>
                <tr><th>رپورٹ</th><th>کام</th></tr>
                <tr><td><span class="en">Company-wise month-wise</span></td><td>ہر کمپنی/پارٹی کی ماہ بہ ماہ جمع یا نکلوائی؛ سٹیٹمنٹ کے کل اعداد سے فرق کے ساتھ (پرانی ایکسل کی شیٹ کی جگہ)</td></tr>
                <tr><td><span class="en">Category-wise month-wise</span></td><td>کیٹیگری کے حساب سے ماہ بہ ماہ</td></tr>
                <tr><td><span class="en">Company ledger</span></td><td>ایک کمپنی کا مکمل کھاتہ بیلنس کے ساتھ</td></tr>
                <tr><td><span class="en">Reconciliation statement</span></td><td>سٹیٹمنٹ بیلنس، بقایا چیزیں، کتابی بیلنس اور فرق</td></tr>
                <tr><td><span class="en">Unclassified / Unreconciled</span></td><td>جو انٹریاں باقی ہیں، بڑی رقم پہلے</td></tr>
                <tr><td><span class="en">Entries without attachment</span></td><td>جن کی رسید یا فائل نہیں لگی</td></tr>
                <tr><td><span class="en">Pending approvals</span></td><td>کتنے دن سے منظوری کے انتظار میں ہیں</td></tr>
                <tr><td><span class="en">Cash-flow, Charges &amp; tax, Top parties</span></td><td>خلاصے</td></tr>
                <tr><td><span class="en">All accounts at a glance</span></td><td>ہر اکاؤنٹ: انٹریاں، آمد، خرچ، غیر درجہ بند، منظوری کے انتظار میں، اور بغیر رسید</td></tr>
                <tr><td><span class="en">Daily book</span></td><td>ایک اکاؤنٹ دن بہ دن: آمد، خرچ اور ہر دن کے آخر کا بیلنس (ابتدائی بیلنس خود لکھ سکتے ہیں)</td></tr>
                <tr><td><span class="en">Scanned receipts not yet linked</span></td><td>ان باکس کی وہ رسیدیں جو کسی انٹری سے نہیں جڑیں، پرانی پہلے</td></tr>
                <tr><td><span class="en">Not yet in the live books</span></td><td>رجسٹر میں پوسٹ شدہ وہ رقمیں جو ابھی جی آر این/سیٹلمنٹ وغیرہ کے لائیو حساب میں نہیں آئیں؛ دونوں حساب کا فرق ایک جگہ</td></tr>
                <tr><td><span class="en">Audit log</span></td><td>کس نے کب کیا کیا</td></tr>
            </table>
            <figure><img src="{{ asset('icons-images/bank-register-help/18-reports-index.jpg') }}" alt="رپورٹس" loading="lazy"><figcaption>رپورٹس کی فہرست</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/19-report-company-month.jpg') }}" alt="کمپنی بہ ماہ رپورٹ" loading="lazy"><figcaption>کمپنی/پارٹی کے حساب سے ماہ بہ ماہ رپورٹ</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/20-report-ledger.jpg') }}" alt="کمپنی لیجر" loading="lazy"><figcaption>کمپنی کا لیجر</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/26-account-summary.jpg') }}" alt="تمام اکاؤنٹس" loading="lazy"><figcaption>تمام اکاؤنٹس ایک نظر میں</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/27-daily-book.jpg') }}" alt="روزانہ کھاتہ" loading="lazy"><figcaption>روزانہ کھاتہ: ہر دن کا بیلنس</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/29-live-gap.jpg') }}" alt="لائیو حساب میں نہیں" loading="lazy"><figcaption>رجسٹر اور لائیو حساب کا فرق</figcaption></figure>
            <p>ہر رپورٹ اسی کو نظر آتی ہے جسے اس کی اجازت ہو۔ میکر، چیکر اور آڈیٹر سب رپورٹیں دیکھتے ہیں؛ سینئر منظور کنندہ صرف فیصلے کے لیے ضروری رپورٹیں۔</p>
        </section>

        <section id="print">
            <h2>۱۴۔ پرنٹ کرنا</h2>
            <p>ہر صفحہ براؤزر کے <span class="en">Print</span> (<span class="en">Ctrl + P</span>) سے صاف کاغذ پر چھپتا ہے: مینو، بٹن، فلٹر اور ٹِک باکس خود چھپنے سے ہٹ جاتے ہیں، اوپر کمپنی کا نام، رپورٹ کا عنوان، پرنٹ کی تاریخ اور پرنٹ کرنے والے کا نام آ جاتا ہے، ٹیبل پورے صفحے پر اور ہر صفحے پر عنوان کی قطار دوبارہ آتی ہے، اور صفحہ <span class="en">A4 Landscape</span> میں سیٹ ہوتا ہے۔</p>
            <ul>
                <li><b>رجسٹر:</b> فلٹر لگا کر پرنٹ کریں تو وہی انٹریاں چھپتی ہیں جو اسکرین پر ہیں۔</li>
                <li><b>ایک انٹری:</b> انٹری کھول کر پرنٹ کریں؛ رسید نمبر، جرنل، منظوری اور ہسٹری سب شامل ہوتے ہیں۔</li>
                <li><b>رپورٹس:</b> ہر رپورٹ میں <span class="en">Excel</span> اور <span class="en">PDF</span> کے بٹن ہیں؛ سادہ پرنٹ کے لیے براؤزر کا پرنٹ بھی چلتا ہے۔</li>
                <li><b>یہ رہنمائی:</b> اوپر کے <span class="en">🖨 پرنٹ کریں</span> سے پوری گائیڈ چھپتی ہے۔</li>
            </ul>
            <figure><img src="{{ asset('icons-images/bank-register-help/32-print-register.jpg') }}" alt="پرنٹ کا منظر" loading="lazy"><figcaption>رجسٹر کا پرنٹ: صاف عنوان، پورا ٹیبل، بغیر بٹن</figcaption></figure>
            <figure><img src="{{ asset('icons-images/bank-register-help/33-print-entry.jpg') }}" alt="انٹری کا پرنٹ" loading="lazy"><figcaption>ایک انٹری کا پرنٹ</figcaption></figure>
        </section>

        <section id="faq">
            <h2>۱۵۔ عام سوالات اور مسائل</h2>
            <table>
                <tr><th>مسئلہ</th><th>حل</th></tr>
                <tr><td>امپورٹ "Does not tally" کہتی ہے</td><td>پرنٹ شدہ سٹیٹمنٹ کے کل ڈیبٹ/کریڈٹ دوبارہ دیکھیں، یا فائل میں کوئی قطار رہ گئی ہو۔ غلط قطاروں کی فہرست خلاصے میں نیچے ہوتی ہے۔</td></tr>
                <tr><td>"File layout was not recognised"</td><td>فائل میں سب سے اوپر کی قطار میں <span class="en">Posting date</span> اور <span class="en">Debit</span> / <span class="en">Credit</span> کے عنوان لکھیں۔</td></tr>
                <tr><td>Submit کا بٹن نہیں ہے</td><td>انٹری کی کیٹیگری لگی ہونی چاہیے اور حالت <span class="en">Classified / Rejected / Reversed</span> ہو۔</td></tr>
                <tr><td>"You submitted this entry, so a different person must approve it"</td><td>میکر اپنی انٹری منظور نہیں کر سکتا؛ چیکر سے کہیں۔</td></tr>
                <tr><td>"needs a senior approver"</td><td>رقم دوسرے درجے کی حد سے اوپر ہے؛ ایسا شخص منظور کرے جس کے پاس <span class="en">approve-high-value</span> کی اجازت ہو۔</td></tr>
                <tr><td>رجسٹر کی رقم باقی ماڈیولز کی رپورٹ میں کیوں نظر نہیں آتی</td><td>یہی اس کا ڈیزائن ہے: رجسٹر کا حساب الگ ہے۔ دونوں کا فرق <span class="en">Not yet in the live books</span> رپورٹ میں دیکھیں۔</td></tr>
                <tr><td>انٹری بدل نہیں سکتی</td><td>پوسٹ شدہ انٹری لاک ہے؛ پہلے ریورس کریں۔</td></tr>
                <tr><td>ریکنسیلی ایشن مکمل نہیں ہو رہی</td><td>دیکھیں کہ کوئی سٹیٹمنٹ لائن بغیر جوڑ کے تو نہیں، اور <span class="en">Difference</span> صفر ہے۔</td></tr>
                <tr><td>رسید کاغذ پر ہے، انٹری ابھی نہیں بنی</td><td>رسید کو اسکین ان باکس میں ڈال دیں؛ انٹری بننے پر جوڑ دیں۔</td></tr>
                <tr><td>انٹری دو بار بن گئی</td><td>ہاتھ سے بنائی ہوئی غیر پوسٹ انٹری حذف ہو سکتی ہے؛ پوسٹ ہو چکی ہو تو ریورس کریں۔</td></tr>
                <tr><td>سٹیٹمنٹ کا فارمیٹ مختلف ہے</td><td>عنوانوں میں <span class="en">Date</span> اور <span class="en">Debit/Credit</span> یا <span class="en">Amount (+ Dr/Cr)</span> ہو تو چل جاتا ہے؛ ورنہ ہاتھ سے انٹری شامل کریں۔</td></tr>
                <tr><td>"<span class="en">The date cannot be in the future</span>"</td><td>آنے والی تاریخ کی انٹری نہیں بن سکتی۔</td></tr>
                <tr><td>کوئی بٹن یا صفحہ نظر نہیں آتا</td><td>اجازت نہیں ہوگی؛ ایڈمن سے <span class="en">Settings › Roles</span> میں اجازت دلوائیں۔</td></tr>
            </table>
            <div class="tip">پہلی بار استعمال: ۱) کیٹیگریاں اور الیاس دیکھ لیں، ۲) سب سے بڑی رقم والی غیر درجہ بند انٹریاں پہلے نمٹائیں (فہرست کو رقم کے حساب سے فلٹر کریں)، ۳) جس نام کو بار بار لگانا پڑے اس پر "<span class="en">Remember for next time</span>" ضرور لگائیں۔</div>
        </section>
    </main>
</body>
</html>

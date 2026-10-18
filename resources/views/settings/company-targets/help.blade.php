<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>کمپنی ٹارگٹ — استعمال کی رہنمائی</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600&family=Noto+Naskh+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #1e2a5a; --line: #e2e8f0; --soft: #f8fafc; --text: #0f172a; --muted: #475569; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: var(--text); font-family: 'Noto Naskh Arabic', 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', Tahoma, serif; font-size: 18px; line-height: 2; }
        .bar { position: sticky; top: 0; z-index: 5; display: flex; gap: 10px; justify-content: space-between; align-items: center; padding: 10px 24px; background: #fff; border-bottom: 1px solid var(--line); }
        .bar h1 { margin: 0; font-family: 'Noto Nastaliq Urdu', 'Noto Naskh Arabic', serif; font-size: 22px; color: var(--navy); }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 18px; border: 1px solid var(--navy); border-radius: 8px; background: var(--navy); color: #fff; font: inherit; font-size: 16px; cursor: pointer; text-decoration: none; }
        .btn.light { background: #fff; color: var(--navy); }
        main { max-width: 900px; margin: 24px auto; padding: 0 16px 48px; }
        section { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 18px 24px; margin-bottom: 18px; }
        h2 { margin: 0 0 8px; font-size: 22px; color: var(--navy); font-family: 'Noto Nastaliq Urdu', 'Noto Naskh Arabic', serif; line-height: 2.2; }
        p { margin: 6px 0; }
        ol, ul { margin: 6px 0; padding-inline-start: 26px; }
        li { margin: 4px 0; }
        .note { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px; padding: 8px 14px; margin: 10px 0; font-size: 17px; }
        .tip { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 8px 14px; margin: 10px 0; font-size: 17px; }
        figure { margin: 12px 0; }
        figure img { width: 100%; height: auto; border: 1px solid var(--line); border-radius: 10px; display: block; }
        figcaption { margin-top: 4px; font-size: 15px; color: var(--muted); text-align: center; }
        table { width: 100%; border-collapse: collapse; font-size: 16px; line-height: 1.9; }
        th, td { border: 1px solid var(--line); padding: 6px 10px; text-align: right; vertical-align: top; }
        th { background: var(--soft); }
        .en { direction: ltr; unicode-bidi: isolate; font-family: Inter, Arial, sans-serif; font-size: 0.85em; background: #eef2ff; border-radius: 6px; padding: 0 6px; white-space: nowrap; }
        .badge { display: inline-block; padding: 0 12px; border-radius: 999px; font-size: 15px; }
        .b-pending { background: #fef3c7; color: #92400e; } .b-ok { background: #dcfce7; color: #166534; } .b-no { background: #fee2e2; color: #991b1b; }
        @media print { .bar .btn { display: none; } body { background: #fff; font-size: 15px; } section { break-inside: avoid; border-color: #cbd5e1; } .bar { position: static; } }
    </style>
</head>
<body>
    <div class="bar">
        <h1>کمپنی ٹارگٹ — استعمال کی رہنمائی</h1>
        <div style="display:flex; gap:8px">
            <button type="button" class="btn" onclick="window.print()">🖨 پرنٹ کریں</button>
            <a class="btn light" href="{{ route('company-targets.index') }}">واپس</a>
        </div>
    </div>

    <main>
        <section>
            <h2>یہ کیا ہے؟</h2>
            <p>ہر مہینے ہر کمپنی (مثلاً نیسلے) اپنے سیلز مین کو ایک ہدف دیتی ہے۔ <b>Company Targets</b> میں یہ ہدف درج ہوتا ہے، اور <b>Company Target Summary</b> رپورٹ میں ہدف کے مقابلے میں اصل سیل (جو سیٹلمنٹ سے خود نکلتی ہے) نظر آتی ہے۔</p>
            <div class="tip">ہر مہینے کی ۱ تاریخ کو سسٹم خود ہر کمپنی کا نیا شیٹ بنا دیتا ہے، سب سیلز مین کے ساتھ اور ٹارگٹ <span class="en">0</span> کے ساتھ۔ آپ کو صرف رقمیں بھرنی ہیں۔</div>
        </section>

        <section>
            <h2>۱۔ ٹارگٹ بنانا — <span class="en">/settings/company-targets</span></h2>
            <ol>
                <li><span class="en">Settings</span> میں <span class="en">Company Targets</span> کھولیں اور <span class="en">Add target</span> دبائیں۔</li>
                <li><span class="en">Company</span> چنیں۔ عام صارف کی اپنی کمپنی پہلے سے چنی ہوتی ہے اور بدلی نہیں جا سکتی۔</li>
                <li><span class="en">Start date</span> اور <span class="en">End date</span> پہلے سے مہینے کی شروع اور آخر کی تاریخ ہوتی ہیں۔ ضرورت ہو تو بدل لیں۔</li>
                <li><span class="en">Designation</span> میں <span class="en">Salesman</span> چنیں۔ سیو سے پہلے ہی نیچے سب سیلز مین کی قطاریں آ جائیں گی۔</li>
                <li>ہر قطار میں <span class="en">Target</span> کا متن (پہلے سے <span class="en">Corporate</span>) اور <span class="en">Total</span> کی رقم لکھیں۔ رقم <span class="en">0</span> سے کم نہیں ہو سکتی۔</li>
                <li>قطار ہٹانی ہو تو <span class="en">Remove</span>، اور نئی شامل کرنی ہو تو <span class="en">Add salesman</span> سے چنیں۔</li>
                <li><span class="en">Save target</span> دبائیں۔ کامیابی پر ہری پٹی اور آواز آئے گی، غلطی پر سرخ پٹی اور آواز۔</li>
            </ol>
            <div class="note">ایک کمپنی کے لیے ایک ہی تاریخوں کا دو بار شیٹ نہیں بن سکتا۔ پہلے والا <span class="en">Edit</span> کریں۔</div>
        </section>

        <section>
            <h2>۲۔ رپورٹ دیکھنا — <span class="en">/reports/company-target-summary</span></h2>
            <ol>
                <li><span class="en">Reports</span> میں <span class="en">Audit &amp; Product Reports</span> کے آخر میں <span class="en">Company Target Summary</span> کھولیں۔</li>
                <li>کمپنی چنیں۔ تاریخیں پہلے سے مہینے کے شروع سے آج تک ہوتی ہیں۔</li>
                <li><span class="en">Show report</span> دبائیں۔</li>
            </ol>
            <table>
                <tr><th>خانہ</th><th>مطلب</th></tr>
                <tr><td><span class="en">Total</span></td><td>سیلز مین کا ہدف</td></tr>
                <tr><td><span class="en">Achieved</span></td><td>ان تاریخوں میں اس سیلز مین کی <b>پوسٹ شدہ</b> سیٹلمنٹس کی کل سیل (خود بخود)</td></tr>
                <tr><td><span class="en">Remaining/Day</span></td><td>(Total − Achieved) ÷ آج سے آخری تاریخ تک باقی دن</td></tr>
                <tr><td><span class="en">%age</span></td><td>Achieved ÷ Total × 100 (Total صفر ہو تو 0%)</td></tr>
            </table>
            <p>اوپر <span class="en">Excel</span>، <span class="en">PDF</span> اور <span class="en">Print</span> کے بٹن ہیں۔ ان میں کمپنی کا نام، تاریخیں اور <span class="en">MOON TRADERS ERP</span> کی طرف سے تیار ہونے کی معلومات شامل ہوتی ہیں۔</p>
        </section>

        <section>
            <h2>۳۔ اجازتیں</h2>
            <ul>
                <li><span class="en">company-target-list / create / edit / delete</span> — ٹارگٹ کی سیٹنگ۔</li>
                <li><span class="en">report-audit-company-target-summary</span> — رپورٹ۔</li>
                <li>ایڈمن اور سپر ایڈمن سب کمپنیاں دیکھتے ہیں، باقی صارف صرف اپنی کمپنی۔</li>
            </ul>
        </section>
    </main>
</body>
</html>

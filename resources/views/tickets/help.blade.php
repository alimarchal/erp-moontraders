<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ٹکٹ — استعمال کی رہنمائی</title>
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
        <h1>ٹکٹ — استعمال کی رہنمائی</h1>
        <div style="display:flex; gap:8px">
            <button type="button" class="btn" onclick="window.print()">🖨 پرنٹ کریں</button>
            <button type="button" class="btn light" onclick="window.close()">بند کریں</button>
        </div>
    </div>

    <main>
        <section>
            <h2>ٹکٹ کیا ہے؟</h2>
            <p>ٹکٹ ایک درخواست ہے جو کمپنی کا صارف ایڈمن کو بھیجتا ہے، مثلاً قیمت بدلوانا یا نیا آئٹم شامل کروانا۔ ایڈمن جب تک <b>منظور</b> نہ کرے، نظام میں کچھ نہیں بدلتا۔ منظوری کے بعد وہی تبدیلی خود بخود ہو جاتی ہے جو عام اسکرین سے کرنے پر ہوتی۔</p>
        </section>

        <section>
            <h2>۱۔ ٹکٹ کیسے بنائیں (صارف کے لیے)</h2>
            <ol>
                <li>اوپر مینو میں <span class="en">Tickets</span> پر کلک کریں۔</li>
                <li>اوپر کے بٹن <span class="en">New ticket</span> کو دبائیں۔</li>
                <li><span class="en">Ticket type</span> میں سے ٹکٹ کی قسم چنیں۔</li>
                <li>عنوان لکھیں، اور چاہیں تو وجہ بھی لکھ دیں۔</li>
                <li>فارم بھریں (نیچے اقسام دیکھیں) اور <span class="en">Submit for approval</span> دبائیں۔</li>
            </ol>
            <figure><img src="{{ asset('icons-images/ticket-help/1-menu.jpg') }}" alt="ٹکٹ کی فہرست" loading="lazy"><figcaption>مینو میں Tickets اور نیا ٹکٹ بنانے کا بٹن</figcaption></figure>
            <figure><img src="{{ asset('icons-images/ticket-help/2-type.jpg') }}" alt="ٹکٹ کی قسم" loading="lazy"><figcaption>ٹکٹ کی قسم کی فہرست سے قسم چنیں</figcaption></figure>
            <div class="note"><b>خیال رکھیں:</b> <span class="en">Submit</span> بٹن صرف ایک بار دبائیں۔ دبانے کے بعد وہ خود بند ہو جاتا ہے، اس لیے ایک ٹکٹ دو بار نہیں بنتا۔ کامیابی پر پیغام آئے گا، <span class="en">OK</span> دبا دیں۔</div>
            <figure><img src="{{ asset('icons-images/ticket-help/4-success.jpg') }}" alt="کامیابی کا پیغام" loading="lazy"><figcaption>ٹکٹ بھیجنے کے بعد تصدیقی پیغام</figcaption></figure>
        </section>

        <section>
            <h2>۲۔ قیمت / ری آرڈر لیول بدلوانا</h2>
            <ol>
                <li>فہرست سے پروڈکٹ چنیں (نام یا کوڈ لکھ کر تلاش بھی ہو سکتی ہے)۔</li>
                <li>جو قیمت بدلنی ہے صرف وہی لکھیں؛ خالی خانے کا مطلب ہے "تبدیلی نہیں"۔ پرانی قیمت اور فرق (اضافہ یا کمی) ساتھ ہی نظر آتا ہے۔</li>
                <li><span class="en">Selling price applies to</span> میں چنیں: <span class="en">All batches</span> (جن بیچز میں اسٹاک ہے سب) یا <span class="en">Selected batches</span> (صرف اپنی چنی ہوئی بیچز)۔</li>
                <li>ایک سے زیادہ پروڈکٹ ہوں تو <span class="en">Add another product</span> دبائیں۔</li>
            </ol>
            <figure><img src="{{ asset('icons-images/ticket-help/3-form.jpg') }}" alt="قیمت کا فارم" loading="lazy"><figcaption>قیمت بدلنے کا فارم، پرانی قیمت اور فرق خود نظر آتا ہے</figcaption></figure>
            <div class="tip">جن بیچز کا اسٹاک ختم ہو چکا ہے ان کی قیمت نہیں بدلتی۔</div>
        </section>

        <section>
            <h2>۳۔ ٹکٹ کی اقسام</h2>
            <table>
                <thead><tr><th>قسم</th><th>کس کے لیے</th><th>منظوری پر کیا ہوتا ہے</th></tr></thead>
                <tbody>
                    <tr><td><span class="en">Price / Reorder Update</span></td><td>فروخت، لاگت یا ایکسپائری قیمت، یا ری آرڈر لیول بدلنا</td><td>پروڈکٹ اور چنی ہوئی بیچز کی قیمت بدل جاتی ہے اور <span class="en">Price Change Log</span> میں درج ہوتی ہے</td></tr>
                    <tr><td><span class="en">New SKU Add</span></td><td>نیا آئٹم شامل کروانا</td><td>آئٹم بن جاتا ہے</td></tr>
                    <tr><td><span class="en">Activate / Deactivate SKU</span></td><td>آئٹم کو فعال (<span class="en">Active</span>) یا غیر فعال (<span class="en">Inactive</span>) کروانا</td><td>آئٹم کی حالت بدل جاتی ہے: غیر فعال فعال ہو جاتا ہے اور فعال غیر فعال</td></tr>
                    <tr><td><span class="en">Stock Adjustment</span></td><td>نقصان، ایکسپائری، چوری یا گنتی کا فرق</td><td>ایڈجسٹمنٹ بن کر پوسٹ ہو جاتی ہے (اسٹاک اور جرنل انٹری)۔ صرف یونٹ کاسٹ بھی بدلی جا سکتی ہے</td></tr>
                    <tr><td><span class="en">Supplier Ledger Entry</span></td><td>سپلائر لیجر رجسٹر میں اندراج</td><td>صرف اندراج بنتا ہے، پوسٹ نہیں ہوتا</td></tr>
                    <tr><td><span class="en">Claim Register Entry</span></td><td>کلیم یا ریکوری کا اندراج</td><td>صرف اندراج بنتا ہے، پوسٹ نہیں ہوتا</td></tr>
                    <tr><td><span class="en">New Customer</span></td><td>نیا کسٹمر بنوانا</td><td>کسٹمر بن جاتا ہے</td></tr>
                </tbody>
            </table>
            <p style="margin-top:10px"><b>آئٹم فعال / غیر فعال:</b> فہرست میں آپ کی کمپنی کے تمام آئٹم نظر آتے ہیں اور ہر نام کے ساتھ اس کی موجودہ حالت (<span class="en">Active</span> یا <span class="en">Inactive</span>) لکھی ہوتی ہے۔ آئٹم چنتے ہی نئی حالت خود الٹ ہو جاتی ہے، یعنی غیر فعال آئٹم پر <span class="en">Active</span> اور فعال آئٹم پر <span class="en">Inactive</span>۔ اگر نئی حالت موجودہ حالت جیسی ہو تو ٹکٹ نہیں بنتا۔</p>
            <p style="margin-top:10px"><b>اسٹاک ایڈجسٹمنٹ:</b> گودام اور بیچ چنیں، پھر گنتی کی مقدار لکھیں۔ فرق اور اس کی مالیت خود نکل آتی ہے۔</p>
            <p style="margin-top:10px"><b>صرف یونٹ کاسٹ بدلنا:</b> بیچ چننے پر گنتی کی مقدار خود سسٹم کی مقدار کے برابر آ جاتی ہے۔ مقدار نہ بدلیں، صرف <span class="en">Unit cost</span> میں نئی لاگت لکھیں۔ لائن پر <span class="en">cost only</span> لکھا آ جاتا ہے اور قدر میں لاگت کا فرق شامل ہوتا ہے۔ منظوری پر بیچ کی لاگت بدل جاتی ہے اور جرنل انٹری بنتی ہے۔ اگر مقدار اور لاگت دونوں پہلے جیسی ہوں تو ٹکٹ نہیں بنتا۔ اگر اسی بیچ کا کچھ مال کسی دوسرے گودام میں ہو یا وین پر ہو تو منظوری رک جاتی ہے اور وجہ بتا دی جاتی ہے۔</p>
            <figure><img src="{{ asset('icons-images/ticket-help/5-adjustment.jpg') }}" alt="اسٹاک ایڈجسٹمنٹ" loading="lazy"><figcaption>اسٹاک ایڈجسٹمنٹ کا ٹکٹ</figcaption></figure>
        </section>

        <section>
            <h2>۴۔ ٹکٹ کی حالت</h2>
            <ul>
                <li><span class="badge b-pending">Pending</span> — فیصلے کا انتظار ہے۔ اس دوران آپ ٹکٹ <span class="en">Edit</span> یا <span class="en">Delete</span> کر سکتے ہیں۔</li>
                <li><span class="badge b-ok">Approved</span> — ایڈمن نے منظور کر دیا اور تبدیلی ہو گئی۔</li>
                <li><span class="badge b-no">Rejected</span> — مسترد ہوا، نظام میں کچھ نہیں بدلا۔ ایڈمن کی لکھی وجہ ٹکٹ کے صفحے پر دکھائی دیتی ہے۔</li>
            </ul>
            <p>ٹکٹ کے صفحے پر <span class="en">Ticket history</span> میں ہر قدم (کس نے، کب، کیا کیا) درج رہتا ہے۔</p>
        </section>

        <section>
            <h2>۵۔ ایڈمن کے لیے: منظوری یا رد</h2>
            <ol>
                <li>نیا ٹکٹ آنے پر ای میل آتی ہے اور مینو میں <span class="en">Tickets</span> کے ساتھ نارنجی نمبر نظر آتا ہے۔</li>
                <li>ٹکٹ کھولیں۔ پرانی اور نئی قیمت، فرق، بیچز اور وجہ سب ایک ہی صفحے پر نظر آتے ہیں، سائیڈ میں سکرول نہیں کرنا پڑتا۔</li>
                <li>منظور کرنے کے لیے <span class="en">Approve</span> دبائیں، پھر تصدیقی ونڈو میں دوبارہ <span class="en">Approve</span>۔</li>
                <li>مسترد کرنے کے لیے پہلے وجہ لکھیں، پھر <span class="en">Reject</span>۔</li>
            </ol>
            <figure><img src="{{ asset('icons-images/ticket-help/6-review.jpg') }}" alt="ایڈمن کا ریویو" loading="lazy"><figcaption>ایڈمن کا صفحہ: دائیں طرف Review (منظوری/رد) کا خانہ</figcaption></figure>
            <figure><img src="{{ asset('icons-images/ticket-help/7-approve.jpg') }}" alt="منظوری کی تصدیق" loading="lazy"><figcaption>منظوری کی تصدیقی ونڈو</figcaption></figure>
            <div class="note"><b>اسٹاک ایڈجسٹمنٹ</b> کی منظوری پر اسٹاک اور اکاؤنٹس بدلتے ہیں، اس لیے ایڈمن کو اپنا <b>پاس ورڈ</b> بھی لکھنا ہوتا ہے، اور اس کے پاس <span class="en">stock-adjustment-post</span> کی اجازت ہونا ضروری ہے۔</div>
            <figure><img src="{{ asset('icons-images/ticket-help/8-password.jpg') }}" alt="پاس ورڈ کی تصدیق" loading="lazy"><figcaption>اسٹاک ایڈجسٹمنٹ پوسٹ کرنے سے پہلے پاس ورڈ</figcaption></figure>
        </section>

        <section>
            <h2>۶۔ چند ضروری باتیں</h2>
            <ul>
                <li>صارف صرف اپنی کمپنی کے آئٹمز اور ٹکٹ دیکھ سکتا ہے۔</li>
                <li>اگر منظوری کے وقت کام ممکن نہ ہو (مثلاً اسٹاک ختم ہو چکا ہو) تو وجہ لکھی آتی ہے اور ٹکٹ <span class="en">Pending</span> ہی رہتا ہے، کچھ نہیں بدلتا۔</li>
                <li>قیمت بدلنے والا ٹکٹ منظور ہو جائے تو ٹکٹ کے صفحے پر <span class="en">View in price change log</span> کا بٹن آتا ہے (اجازت ہو تو)۔</li>
                <li>مسئلہ ہو تو اپنے ایڈمن سے رابطہ کریں۔</li>
            </ul>
        </section>
    </main>
</body>
</html>

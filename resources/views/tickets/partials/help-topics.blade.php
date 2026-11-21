{{-- Urdu help of the Tickets pages, shown in <x-register-help page="tickets" />. --}}
<section data-topic="what">
    <h2>ٹکٹ کیا ہے؟</h2>
    <p>ٹکٹ ایک درخواست ہے جو کمپنی کا صارف ایڈمن کو بھیجتا ہے، مثلاً قیمت بدلوانا، نیا آئٹم یا اسٹاک ایڈجسٹمنٹ۔ ایڈمن جب تک <b>منظور</b> نہ کرے، نظام میں کچھ نہیں بدلتا۔ منظوری پر وہی تبدیلی ہوتی ہے جو عام اسکرین سے کرنے پر ہوتی۔</p>
</section>
<section data-topic="create">
    <h2>ٹکٹ کیسے بنائیں</h2>
    <ol>
        <li>اوپر <span class="en">New ticket</span> دبائیں۔</li>
        <li><span class="en">Ticket type</span> میں سے قسم چنیں، عنوان اور چاہیں تو وجہ لکھیں۔</li>
        <li>فارم بھریں اور <span class="en">Submit for approval</span> ایک بار دبائیں۔</li>
    </ol>
    <div class="note">ٹکٹ بنتے ہی ایڈمن کو ای میل جاتی ہے؛ منظوری یا رد پر بنانے والے کو ای میل آتی ہے۔</div>
</section>
<section data-topic="types">
    <h2>ٹکٹ کی اقسام</h2>
    <table>
        <thead><tr><th>قسم</th><th>منظوری پر کیا ہوتا ہے</th></tr></thead>
        <tbody>
            <tr><td><span class="en">Price / Reorder Update</span></td><td>پروڈکٹ اور بیچز کی قیمت بدلتی ہے اور <span class="en">Price Change Log</span> میں درج ہوتی ہے</td></tr>
            <tr><td><span class="en">New SKU Add</span></td><td>نیا آئٹم بن جاتا ہے</td></tr>
            <tr><td><span class="en">Activate / Deactivate SKU</span></td><td>آئٹم کی حالت بدل جاتی ہے (غیر فعال کرنے کے لیے گودام اور وینز پر مقدار صفر ہونی چاہیے)</td></tr>
            <tr><td><span class="en">Stock Adjustment</span></td><td>ایڈجسٹمنٹ بن کر پوسٹ ہوتی ہے: اسٹاک اور جرنل انٹری</td></tr>
            <tr><td><span class="en">Supplier Ledger / Claim Entry</span></td><td>صرف اندراج بنتا ہے، پوسٹ نہیں ہوتا</td></tr>
            <tr><td><span class="en">New Customer</span></td><td>کسٹمر بن جاتا ہے</td></tr>
        </tbody>
    </table>
</section>
<section data-topic="price">
    <h2>قیمت بدلوانا</h2>
    <ul>
        <li>جو قیمت بدلنی ہے صرف وہی لکھیں؛ خالی خانے کا مطلب ہے "تبدیلی نہیں"۔</li>
        <li><span class="en">All batches</span> یا <span class="en">Selected batches</span> چنیں۔ اگر چنی ہوئی سب بیچز پہلے ہی اسی قیمت پر ہوں تو ٹکٹ نہیں بنتا۔</li>
        <li>اگر ٹکٹ بننے کے بعد کسی اور نے وہی قیمت بدل دی ہو تو منظوری رک جاتی ہے، تاکہ نئی قیمت چپکے سے نہ مٹے۔ ٹکٹ <span class="en">Edit</span> کر کے نئی قیمت کی تصدیق کریں۔</li>
    </ul>
</section>
<section data-topic="adjustment">
    <h2>اسٹاک ایڈجسٹمنٹ</h2>
    <ul>
        <li>گودام اور بیچ چنیں، پھر گنی ہوئی مقدار لکھیں۔ فرق اور مالیت بیچ کی اپنی لاگت پر خود نکلتی ہے؛ ٹکٹ سے <span class="en">Unit cost</span> نہیں بدلتی۔</li>
        <li>ایک بیچ ایک وقت میں صرف ایک زیر التوا ٹکٹ پر ہو سکتا ہے۔ دوسری گنتی ہو تو پہلا ٹکٹ ہی <span class="en">Edit</span> کریں۔</li>
        <li>اگر گنتی کے بعد بیچ کا اسٹاک بدل گیا ہو (فروخت، وصولی، کوئی اور ایڈجسٹمنٹ) تو منظوری رک جاتی ہے اور دونوں مقداریں دکھائی جاتی ہیں۔ دوبارہ گن کر ٹکٹ <span class="en">Edit</span> کریں؛ ایڈٹ پر سسٹم کی موجودہ مقدار خود آ جاتی ہے۔</li>
        <li><span class="en">Selling cost</span> صرف تب بدلتی ہے جب ٹکٹ میں نئی قیمت لکھی گئی ہو۔</li>
    </ul>
</section>
<section data-topic="status">
    <h2>ٹکٹ کی حالت (ٹیبز)</h2>
    <ul>
        <li><span class="en">Pending</span>: فیصلے کا انتظار۔ اس دوران آپ ٹکٹ <span class="en">Edit</span> یا <span class="en">Delete</span> کر سکتے ہیں۔</li>
        <li><span class="en">Approved</span>: منظور ہوا اور تبدیلی ہو گئی۔ اس کے بعد ٹکٹ نہ بدلا جا سکتا ہے نہ مٹایا جا سکتا ہے۔</li>
        <li><span class="en">Rejected</span>: مسترد، کچھ نہیں بدلا۔ وجہ ٹکٹ کے صفحے پر دکھائی دیتی ہے۔</li>
        <li><span class="en">All</span>: سب ٹکٹ۔</li>
    </ul>
</section>
<section data-topic="filters">
    <h2>فلٹر</h2>
    <p>اوپر <span class="en">Filters</span> دبائیں: تلاش (نمبر، عنوان، وجہ)، تاریخ، کمپنی، قسم، بنانے والا اور منظور / رد کرنے والا۔ لگے ہوئے فلٹر چِپس کی صورت میں اوپر دکھتے ہیں؛ ✕ سے ہٹائیں۔</p>
</section>
<section data-topic="admin">
    <h2>ایڈمن کے لیے: منظوری یا رد</h2>
    <ol>
        <li>ٹکٹ کھولیں؛ پرانی اور نئی قیمت، فرق، بیچز اور وجہ ایک ہی صفحے پر ہیں۔</li>
        <li><span class="en">Approve</span> دبائیں اور تصدیق کریں۔ اسٹاک ایڈجسٹمنٹ پر اپنا پاس ورڈ بھی لکھنا ہوتا ہے۔</li>
        <li>مسترد کرنے کے لیے وجہ لکھ کر <span class="en">Reject</span> دبائیں۔</li>
    </ol>
    <div class="note">اگر منظوری ممکن نہ ہو تو وجہ لکھی آتی ہے اور ٹکٹ <span class="en">Pending</span> ہی رہتا ہے؛ کچھ نہیں بدلتا۔</div>
    <p>تصویروں کے ساتھ مکمل گائیڈ: <a href="{{ route('tickets.help') }}" target="_blank" rel="noopener" class="en">User manual</a></p>
</section>

{{-- Urdu help of the Expense Vouchers pages, shown in <x-register-help page="expense-vouchers" />. --}}
<section data-topic="what">
    <h2>اخراجاتی واؤچر کیا ہے؟</h2>
    <p>روزانہ کے خرچ (چائے، مرمت، کرایہ، ٹونر) اور ملازم کو ایڈوانس کا ریکارڈ۔ ایک صارف واؤچر بناتا ہے (<span class="en">Maker</span>) اور دوسرا صارف اسے جانچ کر منظور کرتا ہے (<span class="en">Checker</span>)۔ منظوری پر ہی کتاب میں جرنل بنتا ہے۔</p>
</section>
<section data-topic="method">
    <h2>ادائیگی کا طریقہ</h2>
    <div class="rgh-col" data-topic="method-cash">
        <div class="rgh-col-head"><span class="en">Cash</span><b>نقد</b></div>
        <p>رقم <bdi class="lt" dir="ltr">1121 Cash</bdi> سے جاتی ہے۔ نوٹ منظور کرنے والا گنتا ہے، بنانے والا نہیں۔</p>
    </div>
    <div class="rgh-col" data-topic="method-bank">
        <div class="rgh-col-head"><span class="en">Bank transfer</span><b>بینک ٹرانسفر</b></div>
        <p>بینک اکاؤنٹ چنیں (صرف وہ فعال بینک جو کھاتوں سے جڑے ہیں)۔ حوالہ نمبر، مثلاً <bdi class="lt" dir="ltr">IBFT</bdi>، لکھ سکتے ہیں۔</p>
    </div>
    <div class="rgh-col" data-topic="method-cheque">
        <div class="rgh-col-head"><span class="en">Cheque</span><b>چیک</b></div>
        <p>بینک اکاؤنٹ، چیک نمبر اور چیک کی تاریخ لازمی ہیں۔ رقم اسی بینک کے کھاتے سے جاتی ہے۔</p>
    </div>
    <div class="rgh-col" data-topic="method-credit">
        <div class="rgh-col-head"><span class="en">Credit, pay later</span><b>ادھار</b></div>
        <p>صرف دکان کے لیے۔ خرچ آج لکھا جاتا ہے مگر رقم <bdi class="lt" dir="ltr">2113 Expense Payables</bdi> میں رہتی ہے، بعد میں <span class="en">Vendor payment</span> سے ادا ہوتی ہے۔</p>
    </div>
</section>
<section data-topic="approve">
    <h2>منظوری اور نوٹوں کی گنتی</h2>
    <ol>
        <li>نقد واؤچر پر <span class="en">Approve and post</span> دبانے سے نوٹوں کا خانہ کھلتا ہے۔</li>
        <li>جو نوٹ آپ ادا کر رہے ہیں ان کی تعداد لکھیں (<bdi class="lt" dir="ltr">5000</bdi> سے <bdi class="lt" dir="ltr">10</bdi> تک، اور سکے رقم میں)۔</li>
        <li>جب تک گنے ہوئے نوٹ واؤچر کی رقم کے برابر نہ ہوں، منظوری کا بٹن بند رہتا ہے۔ فرق صفر ہو تو منظور کریں۔</li>
    </ol>
    <div class="note">نوٹ صرف منظوری کے وقت محفوظ ہوتے ہیں۔ مسترد واؤچر پر کوئی نوٹ نہیں ہوتے۔ ملازم کا نقد ایڈوانس بھی اسی طرح گنا جاتا ہے۔ بینک، چیک اور ادھار واؤچر بغیر نوٹوں کے منظور ہوتے ہیں۔</div>
</section>
<section data-topic="statuses">
    <h2>واؤچر کی حالت</h2>
    <table>
        <tr><th>حالت</th><th>مطلب</th></tr>
        <tr><td><span class="en">Draft</span></td><td>ابھی بھیجا نہیں گیا۔ بنانے والا بدل یا حذف کر سکتا ہے۔</td></tr>
        <tr><td><span class="en">Pending approval</span></td><td>جانچ کرنے والے کے پاس ہے۔ بنانے والا خود منظور نہیں کر سکتا۔</td></tr>
        <tr><td><span class="en">Approved</span></td><td>منظور ہوا اور کتاب میں جرنل بن گیا۔ اب بدلا نہیں جا سکتا۔</td></tr>
        <tr><td><span class="en">Rejected</span></td><td>وجہ کے ساتھ واپس آیا۔ بنانے والا درست کر کے دوبارہ بھیجے؛ پچھلی وجہ ساتھ نظر آتی رہتی ہے۔</td></tr>
        <tr><td><span class="en">Reversed</span></td><td>منظور شدہ واؤچر واپس لیا گیا؛ الٹا جرنل بن گیا۔</td></tr>
    </table>
</section>
<section data-topic="reverse">
    <h2>واپس لینا (<bdi class="lt" dir="ltr">Reverse</bdi>)</h2>
    <ul>
        <li>صرف وہ صارف جسے <span class="en">expense-voucher-reverse</span> کی اجازت ہو، اور جو اس واؤچر کا بنانے والا نہ ہو۔</li>
        <li>وجہ (کم از کم پانچ حروف) اور اپنا پاس ورڈ لازمی ہیں۔</li>
        <li>الٹا جرنل آج کی تاریخ میں بنتا ہے۔ نقد واؤچر کی رقم آج کی کیش شیٹ میں واپس آتی ہے؛ واؤچر والے دن کی شیٹ نہیں بدلتی۔</li>
        <li>ادھار واؤچر جس کی کچھ ادائیگی ہو چکی ہو، یا ایڈوانس جس کی کٹوتی ہو چکی ہو، واپس نہیں ہو سکتا۔</li>
    </ul>
</section>
<section data-topic="print">
    <h2>پرنٹ</h2>
    <p>منظور شدہ واؤچر پر <span class="en">Print voucher</span> سے <bdi class="lt" dir="ltr">A4</bdi> کا ادائیگی واؤچر بنتا ہے: کمپنی کا نام، <span class="en">Cash Payment Voucher</span> یا <span class="en">Bank Payment Voucher</span>، کھاتے، رقم الفاظ میں اور دستخط کی جگہیں۔ <span class="en">Print slip</span> سے <bdi class="lt" dir="ltr">80mm</bdi> کی پرچی۔</p>
    <div class="tip">دوسری بار پرنٹ کرنے پر واؤچر پر <span class="en">DUPLICATE</span> لکھا آتا ہے۔</div>
</section>

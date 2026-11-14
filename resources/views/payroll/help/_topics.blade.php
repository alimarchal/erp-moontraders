{{-- Payroll help (Urdu), shown in <x-register-help page="payroll" />. Each section has a data-topic that the ⓘ buttons on
     the Payroll screens open. Every pay item says where it comes from and where to correct it. --}}
<section data-topic="what">
    <h2>پے رول کیا ہے؟</h2>
    <p>ہر مہینے ہر ملازم کی تنخواہ: <b>بائیں آمدن</b> (بنیادی تنخواہ، الاؤنس، ویری ایبل، سیٹلمنٹ ایکسس) اور <b>دائیں کٹوتی</b> (ایڈوانس، سیٹلمنٹ شارٹ، غیر حاضری، ڈیتھ فنڈ، جرمانہ)۔ <b>خالص تنخواہ = کل آمدن − کل کٹوتی</b>۔</p>
    <p>زیادہ تر رقمیں خود آتی ہیں: سیٹ اپ سے، ایکسپنس واؤچر کے ایڈوانس سے اور سیل سیٹلمنٹ سے۔ ہاتھ سے صرف وہ لکھیں جو اس مہینے خاص ہو۔</p>
</section>

<style>.rgh-link { border: 0; background: none; padding: 0; color: #0066cc; font: inherit; text-decoration: underline; cursor: pointer; }</style>
<section data-topic="tabs">
    <h2>چار حصے (ٹیبز)</h2>
    <table>
        <tr><th>ٹیب</th><th>کام</th></tr>
        <tr><td><span class="en">Payroll runs</span></td><td>ہر مہینے کا پے رول: بنانا، جانچنا، منظور اور ادا کرنا۔</td></tr>
        <tr><td><span class="en">Employee salaries</span></td><td>ہر ملازم کی مستقل تنخواہ: بنیادی، الاؤنس، مستقل کٹوتی۔ ایک یا سب کو اکٹھا۔</td></tr>
        <tr><td><span class="en">Incentives &amp; adjustments</span></td><td>کسی ایک مہینے کی رقم: عید بونس، کمیشن، جرمانہ وغیرہ، کئی ملازمین کو ایک ساتھ۔</td></tr>
        <tr><td><span class="en">Categories</span></td><td>آمدن اور کٹوتی کی مدیں؛ نئی مد کبھی بھی بنائیں۔</td></tr>
    </table>
    <div class="tip">ہر ٹیب صرف اسے دکھتی ہے جسے اس کی اجازت ہو: <button type="button" class="rgh-link" @click="go('permissions')">اجازتیں دیکھیں</button>۔ مزید: <button type="button" class="rgh-link" @click="go('incentive')">ایک بار کا انسینٹو / کمیشن</button>، <button type="button" class="rgh-link" @click="go('excess')">ایکسس</button>، <button type="button" class="rgh-link" @click="go('death-fund')">ڈیتھ فنڈ</button>۔ پوری گائیڈ ایک صفحے پر: <a href="{{ route('payroll.help') }}" target="_blank" rel="noopener">کھولیں</a>۔</div>
</section>

<section data-topic="lifecycle">
    <h2>مہینے کا پورا چکر</h2>
    <x-help-flow flow="payroll" />
</section>

<section data-topic="status">
    <h2>پے رول کی حالت</h2>
    <table>
        <tr><th>حالت</th><th>مطلب</th><th>کون کیا کر سکتا ہے</th></tr>
        <tr><td><span class="en">Draft</span></td><td>بن گیا، ابھی بدل سکتا ہے۔</td><td>بنانے والا (میکر): ویری ایبل، غیر حاضری، لائن، دوبارہ حساب، حذف۔</td></tr>
        <tr><td><span class="en">Pending approval</span></td><td>منظوری کے لیے بھیجا گیا؛ اب تبدیلی نہیں۔</td><td>دوسرا شخص (چیکر): منظور یا واپس۔</td></tr>
        <tr><td><span class="en">Rejected</span></td><td>چیکر نے وجہ لکھ کر واپس کیا۔</td><td>میکر درست کر کے دوبارہ بھیجے۔</td></tr>
        <tr><td><span class="en">Approved</span></td><td>کتاب میں پوسٹ ہو گیا؛ ایڈوانس اور بونس "لاگو" ہو گئے۔</td><td>ادائیگی والا: <span class="en">Pay salaries</span>۔</td></tr>
        <tr><td><span class="en">Paid</span></td><td>تنخواہ دے دی گئی (کیش یا بینک)۔</td><td>صرف دیکھنا اور پرنٹ۔</td></tr>
    </table>
    <div class="note">جس نے پے رول بنا کر بھیجا وہ خود منظور نہیں کر سکتا۔</div>
</section>

<section data-topic="salary-window">
    <h2>تنخواہ کی ونڈو</h2>
    <ul>
        <li>ملازم کے آگے <span class="en">Set salary</span>، یا کئی ملازمین پر ٹِک لگا کر نیچے کی پٹی سے <span class="en">Set salary for selected</span>۔</li>
        <li>عہدے سے فلٹر کریں (مثلاً <span class="en">Order Booker</span>)، اوپر والا ٹِک سب کو چن لیتا ہے، پھر ایک بار میں سب کی تنخواہ۔</li>
        <li>کئی ملازمین میں خالی خانہ = ہر ایک کی پرانی رقم رہے گی۔ <span class="en">Remove categories not listed here</span> لگائیں تو جو مد ونڈو میں نہیں وہ ہٹ جائے گی۔ جس ملازم کی تنخواہ ابھی لگی ہی نہیں، اسے بیسک لکھے بغیر چھوڑ دیا جاتا ہے۔</li>
        <li>بعد میں کسی ایک کی تنخواہ الگ سے بدلی جا سکتی ہے۔ بچایا ہوا سیٹ اپ <b>اگلے</b> پے رول یا <span class="en">Recalculate</span> پر لگتا ہے۔</li>
    </ul>
    <x-help-flow flow="payroll-setup" />
</section>

<section data-topic="basic">
    <h2>بنیادی تنخواہ <bdi class="lt" dir="ltr">(Basic)</bdi></h2>
    <p>ہر مہینے کی طے شدہ رقم۔ <b>کہاں سے:</b> تنخواہ کی ونڈو۔ <b>کہاں درست کریں:</b> <span class="en">Employee salaries</span> → <span class="en">Set salary</span>، پھر ڈرافٹ پے رول میں <span class="en">Recalculate</span>۔</p>
</section>

<section data-topic="salary-type">
    <h2>تنخواہ کی قسم</h2>
    <table>
        <tr><th>قسم</th><th>غیر حاضری</th></tr>
        <tr><td><span class="en">Basic</span></td><td>غیر حاضری پر کٹوتی: بنیادی ÷ مہینے کے دن × غیر حاضر دن۔</td></tr>
        <tr><td><span class="en">Fixed</span></td><td>غیر حاضری پر خود کوئی کٹوتی نہیں۔</td></tr>
    </table>
</section>

<section data-topic="pay-method">
    <h2>ادائیگی کا طریقہ</h2>
    <p><span class="en">Cash</span> یا <span class="en">Bank</span>: ملازم کو تنخواہ کیسے ملتی ہے۔ اصل ادائیگی پے رول کے <span class="en">Pay salaries</span> میں چنی جاتی ہے؛ کیش ادائیگی اسی دن کی <span class="en">Cash Collection</span> رپورٹ میں آتی ہے۔</p>
</section>

<section data-topic="calc">
    <h2>الاؤنس کا حساب: تین طریقے</h2>
    <table>
        <tr><th>طریقہ</th><th>مثال</th></tr>
        <tr><td><span class="en">Fixed Rs</span></td><td>مکان کرایہ <bdi class="lt" dir="ltr">3,500</bdi> ہر مہینے۔</td></tr>
        <tr><td><span class="en">% of basic</span></td><td>بنیادی <bdi class="lt" dir="ltr">30,000</bdi> کا <bdi class="lt" dir="ltr">40%</bdi> = <bdi class="lt" dir="ltr">12,000</bdi>۔</td></tr>
        <tr><td><span class="en">% of another allowance</span></td><td>یوٹیلیٹی = مکان کرایہ کا <bdi class="lt" dir="ltr">10%</bdi>۔ وہ دوسرا الاؤنس اسی ونڈو میں ہونا چاہیے۔</td></tr>
    </table>
    <div class="tip">ونڈو میں رقم ساتھ ساتھ حساب ہو کر دائیں دکھتی ہے؛ ہر کالم کا ٹوٹل اور خالص تنخواہ نیچے۔</div>
</section>

<section data-topic="fixed-part">
    <h2>فکس حصہ (ہر مہینے)</h2>
    <p>بنیادی تنخواہ اور مستقل الاؤنس: مکان کرایہ، یوٹیلیٹی، موبائل، بائیک مینٹیننس، بائیک فیول، <span class="en">HHT Regularity</span>، پرانے ملازم کا فائدہ وغیرہ۔ <b>کہاں سے:</b> تنخواہ کی ونڈو۔ <b>کہاں درست کریں:</b> وہیں، پھر <span class="en">Recalculate</span>۔</p>
</section>

<section data-topic="variable-part">
    <h2>ویری ایبل حصہ (ہر مہینے الگ)</h2>
    <p>کارکردگی پر: <span class="en">Zero Return, MOP Achievement, POS, Numerics, Punctuality, LPPC, Distribution Incentive, OS Patrol</span>، اوور ٹائم، بونس۔ یہ مدیں <span class="en">Decided each month</span> ہوتی ہیں۔ <b>کہاں لکھیں:</b> پے رول کھول کر <span class="en">Enter variable pay</span> (گرڈ)، یا ایک ہی رقم کئی لوگوں کو ہو تو <span class="en">Incentives &amp; adjustments</span>۔</p>
</section>

<section data-topic="categories">
    <h2>مدیں (کیٹیگریز)</h2>
    <ul>
        <li>ہر مد یا <b>آمدن</b> ہے یا <b>کٹوتی</b>، اور یا <b>ہر مہینے</b> (سیٹ اپ میں) یا <b>ہر مہینے الگ</b> (گرڈ / بونس ٹیب)۔</li>
        <li>نئی مد: <span class="en">Categories</span> ٹیب، یا تنخواہ کی ونڈو میں <span class="en">+ New category</span>۔</li>
        <li>جو مد ملازمین کو لگی ہو اس کی قسم (آمدن/کٹوتی) یا "ہر مہینے" نہیں بدلتی؛ نئی مد بنائیں۔ نام بدل سکتے ہیں۔</li>
        <li>مدیں سب کمپنیوں کی مشترک ہیں، اس لیے موجودہ مد صرف ایڈمن بدل سکتا ہے؛ نئی مد اجازت والا ہر صارف بنا سکتا ہے۔</li>
        <li><span class="en">EOBI</span> بند ہے؛ پرانی سلپس میں نظر آتی ہے، نئی میں نہیں۔</li>
    </ul>
</section>

<section data-topic="deductions">
    <h2>کٹوتیاں: کون سی، کہاں سے</h2>
    <table>
        <tr><th>کٹوتی</th><th>کہاں سے آتی ہے</th><th>کہاں درست کریں</th></tr>
        <tr><td>ایڈوانس</td><td>ایکسپنس واؤچر میں ملازم کو دیا ایڈوانس (قسط کے ساتھ)</td><td><span class="en">Expense Vouchers</span>؛ قسط بدلیں، پھر <span class="en">Recalculate</span></td></tr>
        <tr><td>سیٹلمنٹ شارٹ</td><td>مہینے کی پوسٹ شدہ سیٹلمنٹس میں <bdi class="lt" dir="ltr">5293 Short Amount</bdi></td><td>سیل سیٹلمنٹ؛ یا سلپ پر معاف کریں</td></tr>
        <tr><td>غیر حاضری</td><td>صرف <span class="en">Basic</span> قسم</td><td>گرڈ میں <span class="en">Absent days</span> یا سلپ میں</td></tr>
        <tr><td>ڈیتھ فنڈ</td><td>تنخواہ کی ونڈو (ہر مہینے)</td><td><span class="en">Employee salaries</span></td></tr>
        <tr><td>جرمانہ، ٹیکس، قرض</td><td>گرڈ یا <span class="en">Incentives &amp; adjustments</span></td><td>وہیں</td></tr>
    </table>
</section>

<section data-topic="advance">
    <h2>ایڈوانس</h2>
    <p>ایکسپنس واؤچر سے ملازم کو ایڈوانس دیں (کتاب: <bdi class="lt" dir="ltr">Dr 1131 / Cr Cash</bdi>)۔ ہر پے رول میں <b>قسط</b> خود کٹتی ہے؛ قسط نہ ہو تو جتنا باقی ہے، مگر بچی تنخواہ سے زیادہ کبھی نہیں۔ منظوری پر وصولی درج ہو جاتی ہے، اس لیے ایک ہی رقم دو بار نہیں کٹتی۔</p>
    <div class="tip">تنخواہ کی ونڈو میں ملازم کے کھلے ایڈوانس (باقی رقم اور قسط) دائیں نظر آتے ہیں۔</div>
</section>

<section data-topic="short">
    <h2>سیٹلمنٹ شارٹ</h2>
    <p>اس مہینے (سیٹلمنٹ کی تاریخ کے حساب سے) ملازم کی پوسٹ شدہ سیٹلمنٹس میں جو رقم <bdi class="lt" dir="ltr">5293 Short Amount</bdi> میں گئی، وہ تنخواہ سے خود کٹتی ہے۔ منظوری پر <bdi class="lt" dir="ltr">5293</bdi> کریڈٹ ہو کر خرچ واپس ہو جاتا ہے۔</p>
    <ul>
        <li>شارٹ بچی تنخواہ سے زیادہ ہو تو جتنی تنخواہ ہے اتنا کٹتا ہے؛ سلپ پر لکھا آتا ہے کتنا نہیں کٹا۔</li>
        <li>کسی ایک ملازم کا شارٹ معاف کرنا ہو تو سلپ کھول کر <span class="en">Take settlement short from salary</span> بند کریں۔</li>
        <li>جو سیٹلمنٹ منظوری کو بھیجے گئے یا منظور پے رول میں آ چکی ہو، اس کا شارٹ یا ایکسس ہو تو وہ ریورس نہیں ہو سکتی۔</li>
    </ul>
</section>

<section data-topic="excess">
    <h2>سیٹلمنٹ ایکسس</h2>
    <p>سیلز مین نے جتنا کیش بنتا تھا اس سے زیادہ جمع کرایا تو سیٹلمنٹ وہ <bdi class="lt" dir="ltr">4250 Settlement Excess Income</bdi> میں رکھتی ہے۔ وہ <b>ملازم کی کمائی</b> ہے: اسی مہینے کی سلپ میں آمدن بن کر ملتی ہے، اور منظوری پر <bdi class="lt" dir="ltr">4250</bdi> ڈیبٹ ہوتا ہے۔</p>
</section>

<section data-topic="absent">
    <h2>غیر حاضری</h2>
    <p>صرف <span class="en">Basic</span> قسم میں کٹتی ہے: بنیادی ÷ مہینے کے دن × غیر حاضر دن (آدھا دن بھی)۔ گرڈ کے <span class="en">Absent days</span> کالم میں یا سلپ کھول کر لکھیں۔ <span class="en">Fixed</span> قسم میں خانہ بند رہتا ہے۔</p>
</section>

<section data-topic="death-fund">
    <h2>ڈیتھ فنڈ</h2>
    <p>ہر مہینے کی مستقل کٹوتی (مثلاً <bdi class="lt" dir="ltr">300</bdi> یا <bdi class="lt" dir="ltr">200</bdi>)۔ تنخواہ کی ونڈو میں دائیں <span class="en">+ Add deduction</span> سے لگائیں؛ سب پر ایک ساتھ لگانے کے لیے سب کو چن کر لگائیں۔</p>
</section>

<section data-topic="one-off">
    <h2>بونس اور ایک بار کی کٹوتی</h2>
    <ul>
        <li><span class="en">Incentives &amp; adjustments</span> ٹیب: مہینہ، آمدن یا کٹوتی، مد، رقم اور ملازمین چنیں۔ ایک ہی رقم سب چنے ہوئے ملازمین کو (مثلاً عید بونس)۔</li>
        <li>اس مہینے کا پے رول ڈرافٹ ہو تو سلپ فوراً بدل جاتی ہے، ورنہ پے رول بنتے وقت خود آ جاتی ہے۔</li>
        <li>اس مہینے کا پے رول منظوری کو چلا جائے تو ہٹائی نہیں جا سکتی؛ منظوری پر حالت <span class="en">Applied</span> ہو جاتی ہے۔</li>
        <li>سلپ کے اندر <span class="en">Add a line</span> سے صرف اسی سلپ میں ایک لائن؛ <bdi class="lt" dir="ltr">×</bdi> سے صرف ہاتھ کی لائن ہٹتی ہے۔</li>
    </ul>
</section>

<section data-topic="recurring">
    <h2>ایک بار یا ہر مہینے</h2>
    <table>
        <tr><th>انتخاب</th><th>کب لگتا ہے</th></tr>
        <tr><td><span class="en">One time</span></td><td>صرف چنے ہوئے مہینے کی سلپ پر۔ اگلے مہینے خود نہیں آتا۔ پے رول منظور ہوتے ہی <span class="en">Applied</span>۔</td></tr>
        <tr><td><span class="en">Every month</span></td><td>پہلے مہینے سے ہر مہینے کی سلپ پر، آخری مہینے تک یا <span class="en">Stop</span> دبانے تک۔ ہر مہینے کی حالت الگ دکھتی ہے۔</td></tr>
    </table>
    <div class="tip"><span class="en">Stop</span>: جو مہینے ادا ہو چکے وہ ویسے ہی رہتے ہیں؛ چنے ہوئے مہینے سے یہ نہیں لگتا اور مسودہ سلپ سے فوراً ہٹ جاتا ہے۔ <b>Narration</b> سلپ پر مد کے نام کے ساتھ دکھتی ہے۔</div>
</section>

<section data-topic="import">
    <h2>Excel سے امپورٹ</h2>
    <p><b>تنخواہ کا سیٹ اپ</b> (<span class="en">Employee salaries</span> ٹیب): ٹیمپلیٹ میں سب ملازمین ان کی موجودہ تنخواہ کے ساتھ آتے ہیں۔ شیٹ <span class="en">Salaries</span> میں ملازم کوڈ، <span class="en">Salary type</span>، <span class="en">Pay by</span> اور بنیادی تنخواہ؛ شیٹ <span class="en">Earnings &amp; Deductions</span> میں ہر الاؤنس یا کٹوتی کی ایک لائن: مد (ڈراپ ڈاؤن)، <span class="en">Fixed amount / % of basic / % of another allowance</span> اور رقم۔ رقم 0 لکھیں تو وہ مد ہٹ جاتی ہے۔</p>
    <p><b>انسینٹو</b> (<span class="en">Incentives &amp; adjustments</span> ٹیب): ملازم کوڈ، مہینہ، مد، رقم، <span class="en">One time / Every month</span>، آخری مہینہ اور <span class="en">Narration</span>۔ جس لائن میں رقم نہ ہو وہ چھوڑ دی جاتی ہے۔</p>
    <div class="tip">کسی ایک لائن میں بھی غلطی ہو تو کچھ بھی محفوظ نہیں ہوتا اور لائن نمبر کے ساتھ غلطیاں دکھتی ہیں؛ درست کر کے دوبارہ اپ لوڈ کریں۔ یہ اختیار صرف <span class="en">payroll-import-excel</span> اجازت والوں (سپر ایڈمن، ایڈمن) کے پاس ہے۔</div>
</section>

<section data-topic="incentive">
    <h2>ایک بار کا انسینٹو یا کمیشن (اسی مہینے)</h2>
    <p>کسی مہینے کا انسینٹو، کمیشن، عید بونس یا جرمانہ صرف اسی مہینے کی سلپ میں آتا ہے؛ اگلے مہینے خود نہیں دہرایا جاتا۔ مد <span class="en">Incentive / Commission</span> (یا کوئی بھی "ہر مہینے الگ" والی مد)۔ تین طریقے:</p>
    <table>
        <tr><th>کس کو</th><th>کہاں</th><th>کیسے</th></tr>
        <tr><td>سب کو ایک جیسی رقم</td><td>پے رول → <span class="en">Enter variable pay</span></td><td>اوپر <span class="en">Same for everyone</span> کی لائن میں رقم، <span class="en">Enter</span></td></tr>
        <tr><td>ہر ایک کی الگ رقم</td><td>پے رول → <span class="en">Enter variable pay</span></td><td>ہر ملازم کے خانے میں رقم، یا ایکسل سے پیسٹ</td></tr>
        <tr><td>ایک ملازم</td><td>پے رول → اس کی سلپ</td><td><span class="en">Add for this month</span> سے مد اور رقم</td></tr>
        <tr><td>پے رول بننے سے پہلے</td><td><span class="en">Incentives &amp; adjustments</span> ٹیب</td><td>مہینہ، مد، رقم، اور ایک یا سب ملازمین چنیں؛ پے رول بنتے وقت خود آ جاتا ہے</td></tr>
    </table>
    <div class="tip">نئی قسم کا انسینٹو چاہیے تو <span class="en">Categories</span> میں "ہر مہینے الگ" والی نئی آمدن کی مد بنائیں؛ وہ گرڈ میں نیا کالم بن جاتی ہے۔</div>
</section>

<section data-topic="grid">
    <h2>ویری ایبل گرڈ</h2>
    <ul>
        <li>ڈرافٹ یا واپس آئے (<span class="en">Rejected</span>) پے رول میں، صرف بنانے والے کو <span class="en">Enter variable pay</span>: اوپر نیچے ملازمین، دائیں بائیں ہر مہینے الگ والی مدیں (انسینٹو، کمیشن، <span class="en">MOP</span>، اوور ٹائم، جرمانہ…) اور غیر حاضری۔</li>
        <li><b>ہر ایک کی الگ رقم:</b> ملازم کی لائن میں اس کالم کے خانے میں لکھیں۔</li>
        <li><b>سب کو ایک جیسی رقم:</b> سب سے اوپر <span class="en">Same for everyone</span> والی لائن میں اس کالم میں رقم لکھ کر <span class="en">Enter</span> دبائیں؛ نیچے دکھنے والے سب ملازمین میں بھر جاتی ہے (تلاش سے چند ملازمین چن کر صرف انہیں بھی)۔ پھر کسی ایک کی رقم بدل سکتے ہیں۔</li>
        <li><b>ایکسل سے کاپی پیسٹ:</b> اسی ترتیب کا بلاک کاپی کریں اور جس خانے سے شروع کرنا ہو وہاں پیسٹ کریں؛ نیچے اور دائیں بھر جاتا ہے۔ لفظ (نام وغیرہ) والے خانے چھوڑ دیے جاتے ہیں۔</li>
        <li>خالی یا <bdi class="lt" dir="ltr">0</bdi> = وہ رقم ہٹ جائے گی۔ لال کالم کٹوتی ہیں۔</li>
        <li>محفوظ کرنے پر ہر سلپ دوبارہ بنتی ہے، تو ایڈوانس اور شارٹ نئی تنخواہ پر حساب ہوتے ہیں۔</li>
    </ul>
</section>

<section data-topic="payslip">
    <h2>سیلری سلپ</h2>
    <p>کسی ملازم پر کلک کریں: بائیں آمدن، دائیں کٹوتی، ہر لائن کے نیچے اس کا ذریعہ (<span class="en">Basic, Allowance, Advance, Settlement short</span>…)، نیچے خالص تنخواہ اور حالت۔ <span class="en">Previous / Next</span> یا تیر کے بٹن سے اگلی سلپ۔ <span class="en">Download PDF</span> سے ایک سلپ۔</p>
</section>

<section data-topic="sources">
    <h2>ہر رقم کہاں سے آتی ہے اور کہاں درست کریں</h2>
    <table>
        <tr><th>سلپ کی لائن</th><th>ذریعہ</th><th>درستی</th></tr>
        <tr><td>بنیادی، الاؤنس، ڈیتھ فنڈ</td><td>تنخواہ کی ونڈو</td><td><span class="en">Employee salaries</span> → <span class="en">Recalculate</span></td></tr>
        <tr><td>ویری ایبل، انسینٹو، کمیشن، جرمانہ</td><td>گرڈ (سب کو یا ہر ایک کو)</td><td>گرڈ دوبارہ کھولیں</td></tr>
        <tr><td>بونس / ایک بار</td><td>بونس ٹیب</td><td>وہاں ہٹائیں یا نئی لکھیں</td></tr>
        <tr><td>ایڈوانس</td><td>ایکسپنس واؤچر</td><td>واؤچر کی قسط → <span class="en">Recalculate</span></td></tr>
        <tr><td>سیٹلمنٹ شارٹ / ایکسس</td><td>سیل سیٹلمنٹ (<bdi class="lt" dir="ltr">5293 / 4250</bdi>)</td><td>سیٹلمنٹ درست کریں → <span class="en">Recalculate</span>؛ یا شارٹ معاف</td></tr>
        <tr><td>غیر حاضری</td><td>گرڈ یا سلپ</td><td>وہیں</td></tr>
    </table>
    <div class="note">منظوری کو بھیجتے وقت سب سلپس خود دوبارہ بنتی ہیں، تاکہ بعد میں پوسٹ یا ریورس ہوئی سیٹلمنٹ اور نیا ایڈوانس بھی شامل ہو۔</div>
</section>

<section data-topic="recalc">
    <h2><bdi class="lt" dir="ltr">Recalculate</bdi></h2>
    <p>ہر سلپ کو تازہ سیٹ اپ، ایڈوانس، شارٹ، ایکسس اور بونس سے دوبارہ بناتا ہے۔ گرڈ یا ہاتھ سے لکھی لائنیں اور غیر حاضری قائم رہتی ہیں۔ نیا ملازم آئے تو <span class="en">Add missing employees</span>۔</p>
</section>

<section data-topic="approve">
    <h2>منظوری</h2>
    <ul>
        <li>میکر <span class="en">Send for approval</span> دبائے۔ کسی کی خالص تنخواہ منفی ہو تو نہیں جائے گا۔</li>
        <li>دوسرا شخص <span class="en">Approve</span> (کتاب میں پوسٹ) یا <span class="en">Reject</span> (وجہ لکھنا لازمی)۔</li>
        <li>منظوری کے بعد کوئی تبدیلی نہیں؛ غلطی ہو تو اگلے مہینے میں ایڈجسٹ کریں۔</li>
    </ul>
</section>

<section data-topic="ledger">
    <h2>کتاب میں کیا جاتا ہے</h2>
    <table>
        <tr><th>مرحلہ</th><th>ڈیبٹ</th><th>کریڈٹ</th></tr>
        <tr><td>منظوری</td><td><bdi class="lt" dir="ltr">5281 Salary</bdi> (کل آمدن)، <bdi class="lt" dir="ltr">4250</bdi> (ایکسس)</td><td><bdi class="lt" dir="ltr">1131</bdi> ایڈوانس، <bdi class="lt" dir="ltr">5293</bdi> شارٹ، مد کا اپنا اکاؤنٹ ہو تو وہ (ٹیکس، قرض)، ورنہ <bdi class="lt" dir="ltr">5281</bdi> (غیر حاضری، جرمانہ)، <bdi class="lt" dir="ltr">2112 Payroll Payable</bdi> (خالص)</td></tr>
        <tr><td>ادائیگی</td><td><bdi class="lt" dir="ltr">2112 Payroll Payable</bdi></td><td><bdi class="lt" dir="ltr">1121 Cash</bdi> یا بینک</td></tr>
    </table>
</section>

<section data-topic="pay">
    <h2>ادائیگی</h2>
    <p>منظور پے رول پر <span class="en">Pay salaries</span>: کیش یا بینک اکاؤنٹ اور تاریخ (آج تک)۔ کیش ادائیگی اسی دن کی <span class="en">Cash Collection</span> رپورٹ میں "<span class="en">salaries paid</span>" میں آتی ہے۔</p>
</section>

<section data-topic="sheet">
    <h2>سیلری شیٹ اور ڈاؤن لوڈ</h2>
    <ul>
        <li><span class="en">Salary sheet (PDF)</span>: ہر ملازم کی ایک لائن، <span class="en">Fixed part | Variable part | Deductions | Net</span>، ٹوٹل اور دستخط۔ کالم زیادہ ہوں تو بڑا صفحہ (<bdi class="lt" dir="ltr">A3</bdi>)۔</li>
        <li><span class="en">Salary sheet (Excel)</span>: وہی شیٹ، رقمیں نمبر کی صورت میں تاکہ جمع ہو سکیں۔</li>
        <li>اس کے علاوہ: سب سلپس <bdi class="lt" dir="ltr">PDF</bdi>، پے رول رجسٹر، اور <span class="en">Employee salaries</span> ٹیب سے تنخواہوں کی فہرست۔</li>
    </ul>
</section>

<section data-topic="exports">
    <h2>فہرستیں ڈاؤن لوڈ کریں</h2>
    <p>ہر ٹیب کے اوپر <span class="en">Excel</span> اور <span class="en">PDF</span>: موجودہ فلٹر کے ساتھ فہرست بنتی ہے۔</p>
</section>

<section data-topic="permissions">
    <h2>اجازتیں</h2>
    <table>
        <tr><th>اجازت</th><th>کام</th></tr>
        <tr><td><span class="en">payroll-list</span></td><td>پے رول دیکھنا اور ڈاؤن لوڈ</td></tr>
        <tr><td><span class="en">payroll-create</span></td><td>پے رول بنانا، گرڈ، منظوری کو بھیجنا (میکر)</td></tr>
        <tr><td><span class="en">payroll-approve</span></td><td>منظور یا واپس (چیکر)</td></tr>
        <tr><td><span class="en">payroll-pay</span></td><td>تنخواہ ادا کرنا</td></tr>
        <tr><td><span class="en">payroll-salary-manage</span></td><td>تنخواہیں اور مدیں</td></tr>
        <tr><td><span class="en">payroll-adjustment-manage</span></td><td>انسینٹو، بونس اور کٹوتی (ایک بار یا ہر مہینے)</td></tr>
        <tr><td><span class="en">payroll-import-excel</span></td><td>Excel ٹیمپلیٹ سے تنخواہ کا سیٹ اپ اور انسینٹو امپورٹ کرنا (سپر ایڈمن اور ایڈمن)</td></tr>
    </table>
    <div class="tip">سب ملازمین کا پے رول صرف ایڈمن یا <span class="en">payroll-view-all</span> والا دیکھتا ہے؛ <span class="en">payroll-view-company</span> والا اپنی کمپنی کے؛ باقی صرف وہ پے رول جو خود بنائے۔ سپلائر کا صارف صرف اپنی کمپنی کے ملازمین کی تنخواہ دیکھتا اور بدلتا ہے۔</div>
</section>

<section data-topic="flow"><x-help-flow flow="payroll" /></section>

{{-- Urdu help of the Cheque Register, shown in <x-register-help page="cheque-register" />. --}}
<section data-topic="what">
    <h2>چیک رجسٹر کیا ہے؟</h2>
    <p>سیلز مین کی سیٹلمنٹ میں دکانداروں سے جو چیک آئے، ان سب کا ریکارڈ۔ ہر چیک پہلے <bdi class="lt" dir="ltr">1122 Cheques in Hand</bdi> میں رہتا ہے؛ کلیئر ہونے پر نقد یا بینک میں جاتا ہے، اور باؤنس ہونے پر رقم دوبارہ دکاندار کے کھاتے (<bdi class="lt" dir="ltr">1112 Debtors</bdi>) میں لکھی جاتی ہے۔</p>
</section>
<section data-topic="start">
    <h2>شروع کیسے کریں (فلٹر)</h2>
    <ol>
        <li>صفحہ خالی کھلتا ہے۔ فلٹر میں سب سے پہلے <span class="en">Company</span> چنیں۔ کمپنی کے صارف کی اپنی کمپنی پہلے سے لگی ہوتی ہے۔</li>
        <li><span class="en">All companies</span> صرف اسے دکھتا ہے جسے سب کمپنیوں کی اجازت ہو۔</li>
        <li>چیک نمبر، چیک کی تاریخ اور ایک صفحے پر لائنیں چن کر <span class="en">Apply</span> دبائیں۔ <span class="en">Apply</span> کے بغیر کوئی چیک نہیں دکھتا۔</li>
        <li><span class="en">More filters</span> میں: سیلز مین، دکاندار، چیک کا بینک، سیٹلمنٹ نمبر، انٹری کی تاریخ، کلیئر کی تاریخ، کلیئر کا طریقہ (نقد یا بینک)، جمع کرنے والا بینک اور رقم کی حد (سے / تک)۔</li>
    </ol>
    <div class="tip">اوپر <span class="en">Excel</span> اور <bdi class="lt" dir="ltr">PDF</bdi> کے بٹن وہی فلٹر شدہ فہرست ڈاؤن لوڈ کرتے ہیں؛ <bdi class="lt" dir="ltr">PDF</bdi> ہمیشہ <bdi class="lt" dir="ltr">A4 Landscape</bdi> میں بنتی ہے۔</div>
</section>
<section data-topic="tabs">
    <h2>ٹیب</h2>
    <table>
        <tr><th>ٹیب</th><th>مطلب</th></tr>
        <tr><td><span class="en">All</span></td><td>فلٹر کے سب چیک۔</td></tr>
        <tr><td><span class="en">Pending</span></td><td>ابھی ہاتھ میں ہیں (<bdi class="lt" dir="ltr">1122</bdi>)؛ انہی کو کلیئر کیا جا سکتا ہے۔</td></tr>
        <tr><td><span class="en">Cleared</span></td><td>نقد واپس آیا یا بینک نے رقم دے دی۔</td></tr>
        <tr><td><span class="en">Bounced</span></td><td>بینک نے واپس کر دیا؛ رقم دکاندار کے کھاتے میں واپس لکھی گئی۔</td></tr>
        <tr><td><span class="en">Cancelled</span></td><td>منسوخ (دکاندار نے چیک واپس لے لیا)۔ باؤنس کی طرح رقم <bdi class="lt" dir="ltr">1122</bdi> سے نکل کر دکاندار کے کھاتے (<bdi class="lt" dir="ltr">1112 Debtors</bdi>) میں لکھی جاتی ہے؛ دوبارہ Pending کرنے پر یہ اندراج واپس ہو جاتا ہے۔</td></tr>
    </table>
    <p>ہر ٹیب پر چیکوں کی تعداد ہے، اور فہرست کے اوپر کل رقم۔ ٹیب بدلنے سے باقی فلٹر نہیں بدلتے۔</p>
</section>
<section data-topic="clear">
    <h2>کلیئر کرنا: نقد یا بینک</h2>
    <div class="rgh-col" data-topic="clear-cash">
        <div class="rgh-col-head"><span class="en">Cash brought back</span><b>نقد واپس آیا</b></div>
        <p>سیلز مین نقد لایا اور چیک واپس لے گیا۔ کتاب میں: <bdi class="lt" dir="ltr">Dr 1121 Cash / Cr 1122 Cheques in Hand</bdi>۔ نوٹ گننا لازمی ہے۔</p>
    </div>
    <div class="rgh-col" data-topic="clear-bank">
        <div class="rgh-col-head"><span class="en">Deposited in bank</span><b>بینک میں جمع</b></div>
        <p>چیک بینک میں جمع ہوا اور بینک نے رقم دے دی۔ جمع کرنے والا بینک چنیں۔ کتاب میں: <bdi class="lt" dir="ltr">Dr Bank / Cr 1122</bdi>۔ نوٹ نہیں گنے جاتے۔</p>
    </div>
    <ul>
        <li>ایک چیک: لائن کے آخر میں <span class="en">Change</span> دبائیں اور <span class="en">Cleared</span> چنیں۔</li>
        <li>کئی چیک ایک ساتھ: <span class="en">Pending</span> چیکوں کے ڈبے پر نشان لگائیں، نیچے کی پٹی میں <span class="en">Clear selected</span> دبائیں۔ کھڑکی میں چنے ہوئے چیک (نمبر · کمپنی · رقم) کی فہرست آتی ہے۔</li>
        <li>کلیئر کی تاریخ سیٹلمنٹ کی تاریخ سے پہلے اور آج کے بعد کی نہیں ہو سکتی۔</li>
    </ul>
</section>
<section data-topic="one-company">
    <h2>نقد کلیئرنگ: ایک وقت میں ایک کمپنی</h2>
    <p>نقد سے کلیئر ہونے والے سب چیک ایک ہی کمپنی کے ہونے چاہییں، تاکہ گنے ہوئے نوٹ اسی کمپنی کی کیش شیٹ میں جائیں۔ اگر چنے ہوئے چیکوں میں دو کمپنیاں ہوں تو کھڑکی میں سرخ لائن آتی ہے اور محفوظ نہیں ہوتا۔ ہر کمپنی کے چیک الگ الگ کلیئر کریں۔</p>
    <div class="note">بینک سے کلیئرنگ میں کمپنیاں ملی ہو سکتی ہیں، کیونکہ اس میں نوٹ نہیں ہوتے۔</div>
</section>
<section data-topic="notes">
    <h2>نوٹوں کی گنتی</h2>
    <ol>
        <li>نقد کلیئرنگ میں ہر نوٹ (<bdi class="lt" dir="ltr">5000</bdi> سے <bdi class="lt" dir="ltr">10</bdi> تک) کی تعداد لکھیں، اور سکے رقم میں۔</li>
        <li>نیچے <span class="en">Counted</span>، <span class="en">Required</span> اور <span class="en">Difference</span> نظر آتے ہیں۔ فرق صفر (سبز) ہو تب ہی محفوظ ہوتا ہے۔</li>
        <li>کئی چیک ایک ساتھ ہوں تو سب کے نوٹ اکٹھے گنیں؛ یہ نوٹ ایک بار، اسی کلیئرنگ پر محفوظ ہوتے ہیں اور اس دن کی <span class="en">Cash Collection</span> رپورٹ میں جاتے ہیں۔</li>
    </ol>
</section>
<section data-topic="unclear">
    <h2>کلیئر واپس لینا (صرف اسی دن)</h2>
    <ul>
        <li>کلیئر شدہ چیک کو <span class="en">Pending</span> پر صرف اسی دن واپس کیا جا سکتا ہے جس دن وہ کلیئر ہوا، یا جس دن کلیئرنگ لکھی گئی۔</li>
        <li>اگر چیک کئی چیکوں کی ایک کلیئرنگ میں تھا تو پوری کلیئرنگ کھل جاتی ہے: سب چیک <span class="en">Pending</span> پر آتے ہیں اور ان کا جرنل الٹا ہو جاتا ہے۔</li>
        <li>اگلے دن غلطی ہو تو ایڈمن سے رابطہ کریں۔</li>
    </ul>
</section>
<section data-topic="bounce">
    <h2>باؤنس</h2>
    <ol>
        <li><span class="en">Change</span> دبائیں اور <span class="en">Bounced</span> چنیں۔</li>
        <li>باؤنس کی تاریخ لکھیں (آج یا پہلے کی)۔</li>
        <li>اگر بینک نے فیس کاٹی تو <span class="en">Bank charge</span> لکھیں اور وہ بینک چنیں جس میں چیک جمع ہوا تھا۔ فیس بھی دکاندار کے کھاتے میں جاتی ہے۔</li>
    </ol>
    <div class="note">کتاب میں: <bdi class="lt" dir="ltr">Dr 1112 Debtors / Cr 1122 Cheques in Hand</bdi>، اور فیس ہو تو <bdi class="lt" dir="ltr">Dr 1112 / Cr Bank</bdi>۔ کلیئر شدہ چیک باؤنس ہو تو پہلے اس کی کلیئرنگ واپس ہوتی ہے (اسی دن)، تاکہ <bdi class="lt" dir="ltr">1122</bdi> دو بار نہ کٹے۔</div>
</section>
<section data-topic="history">
    <h2>تاریخچہ (<bdi class="lt" dir="ltr">History</bdi>)</h2>
    <p>چیک نمبر کے ساتھ گھڑی کا بٹن ہر تبدیلی دکھاتا ہے، نئی سب سے اوپر: کب، کس نے، کس حالت سے کس حالت میں، نقد یا بینک، کون سا بینک، کلیئرنگ نمبر (<bdi class="lt" dir="ltr">Batch</bdi>)، جرنل نمبر، الٹا جرنل، بینک فیس اور گنے ہوئے نوٹ۔ پرانے کلیئر شدہ چیک بھی یہیں نظر آتے ہیں۔ یہ ریکارڈ کبھی مٹتا نہیں۔</p>
</section>

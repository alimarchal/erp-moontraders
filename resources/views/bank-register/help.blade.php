{{--
    Bank Register -> Help (/bank-register/help). Urdu, right-to-left user guide of every screen, with the life of an
    entry, every filter and every setting. Static page: no data is read. Screenshots live in
    public/icons-images/bank-register-help.
--}}
@php
    // the file time is added so a browser never keeps showing an older screenshot under the same name
    $shot = fn (string $file) => asset('icons-images/bank-register-help/'.$file.'.jpg').'?v='.(@filemtime(public_path('icons-images/bank-register-help/'.$file.'.jpg')) ?: 1);
    $sections = [
        'what' => 'بینک رجسٹر کیا ہے', 'screen' => 'سکرین کا نقشہ', 'lifecycle' => 'انٹری کی زندگی (لائف سائیکل)', 'roles' => 'کون کیا کرتا ہے',
        'daily' => 'روزانہ کا آسان طریقہ', 'import' => 'سٹیٹمنٹ امپورٹ کرنا', 'manual' => 'ہاتھ سے انٹری شامل کرنا', 'opening' => 'اوپننگ بیلنس', 'scans' => 'رسیدوں کا اسکین ان باکس',
        'register' => 'رجسٹر، ٹیب اور پاپ اپ', 'filters' => 'تمام فلٹر', 'classify' => 'کمپنی اور کیٹیگری لگانا', 'attach' => 'رسید اور اٹیچمنٹ',
        'maker' => 'پوسٹ کرنا یا منظوری کے لیے بھیجنا', 'checker' => 'منظور یا مسترد کرنا', 'posting' => 'پوسٹنگ: رقم اصل کھاتوں میں',
        'reverse' => 'ریورس اور ڈیلیٹ', 'recon' => 'بینک ریکنسیلی ایشن', 'masters' => 'سیٹ اپ: کیٹیگری، الیاس، رولز، چیک', 'reports' => 'رپورٹس',
        'env' => 'تمام سیٹنگز (.env)', 'print' => 'پرنٹ کرنا', 'flows' => 'تمام فلو ایک نظر میں', 'faq' => 'عام سوالات اور مسائل',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Bank Register Help" sub="How the bank register works, step by step, in Urdu." :crumbs="['Help' => null]">
            <a href="{{ route('bank-statement-entries.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Register</a>
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bh" lang="ur" dir="rtl">
            <div class="bh-layout">
                <nav class="bh-toc bank-noprint" aria-label="فہرست">
                    <b>فہرست</b>
                    <ol>
                        @foreach ($sections as $id => $title)<li><a href="#{{ $id }}">{{ $title }}</a></li>@endforeach
                    </ol>
                </nav>

                <div class="bh-main">
                    <section id="what">
                        <h2>بینک سٹیٹمنٹ رجسٹر کیا ہے؟</h2>
                        <p>بینک، کیش اور موبائل والٹ کے ہر اکاؤنٹ میں آنے اور جانے والی <b>ہر رقم</b> یہاں لکھی جاتی ہے۔ ہر رقم پر <b>کمپنی اور کیٹیگری</b> لگتی ہے، اس کی <b>رسید</b> ساتھ لگتی ہے، بڑی رقم کو دوسرا آدمی <b>منظور</b> کرتا ہے، پھر وہ <b>پوسٹ</b> ہو کر کھاتے میں جاتی ہے، اور مہینے کے آخر میں بینک کی سٹیٹمنٹ سے <b>ملائی</b> جاتی ہے۔</p>
                        <div class="flow">
                            <span class="step">امپورٹ یا ہاتھ سے انٹری</span><span class="arrow">←</span>
                            <span class="step">کمپنی اور کیٹیگری</span><span class="arrow">←</span>
                            <span class="step">رسید لگانا</span><span class="arrow">←</span>
                            <span class="step">پوسٹ یا منظوری کے لیے بھیجنا</span><span class="arrow">←</span>
                            <span class="step">چیکر کی منظوری</span><span class="arrow">←</span>
                            <span class="step">پوسٹنگ</span><span class="arrow">←</span>
                            <span class="step">ریکنسیلی ایشن اور لاک</span>
                        </div>
                        <div class="tip">رجسٹر کا حساب باقی سسٹم (جی آر این، سیٹلمنٹ، گڈز ایشو) سے <b>بالکل الگ</b> ہے۔ یہاں کی کوئی انٹری ان کے بیلنس نہیں بدلتی۔</div>
                    </section>

                    <section id="screen">
                        <h2>سکرین کا نقشہ</h2>
                        <p>اوپر کے مینو میں <span class="en">Bank Register</span> دبائیں تو رجسٹر کھلتا ہے۔ بینک رجسٹر کی ہر چیز اسی ایک جگہ سے ملتی ہے؛ <span class="en">Settings</span> یا <span class="en">Reports</span> میں اب اس کا کوئی حصہ نہیں۔</p>
                        <table>
                            <tr><th>بٹن</th><th>کام</th></tr>
                            <tr><td><span class="en">Filters</span></td><td>تلاش اور فلٹر کا خانہ کھولتا یا بند کرتا ہے۔ جتنے فلٹر لگے ہوں، بٹن پر ان کی گنتی آتی ہے۔</td></tr>
                            <tr><td><span class="en">+ Add entry</span></td><td>ہاتھ سے نئی انٹری۔</td></tr>
                            <tr><td><span class="en">Import statement</span></td><td>بینک کی ایکسل یا <span class="en">CSV</span> فائل سے انٹریاں لانا۔</td></tr>
                            <tr><td><span class="en">? Help</span></td><td>یہی رہنمائی۔ ہر صفحے پر موجود ہے۔</td></tr>
                            <tr><td><span class="en">Menu</span></td><td>باقی تمام سکرینیں: روز کا کام، رپورٹس اور سیٹ اپ۔</td></tr>
                        </table>
                        <div class="shots">
                            <figure><img src="{{ $shot('01-register') }}" alt="رجسٹر" loading="lazy"><figcaption>رجسٹر: اوپر بٹن، پھر حالت کے ٹیب، پھر انٹریاں</figcaption></figure>
                            <figure><img src="{{ $shot('36-menu') }}" alt="مینو" loading="lazy"><figcaption><span class="en">Menu</span> دبانے پر کھلنے والا خانہ</figcaption></figure>
                        </div>
                        <h3>مینو کے تین حصے</h3>
                        <table>
                            <tr><th>حصہ</th><th>اس میں کیا ہے</th></tr>
                            <tr><td><span class="en">Daily work</span></td><td><span class="en">Register</span> (رجسٹر)، <span class="en">Receipt scans</span> (رسیدوں کا ان باکس)، <span class="en">Reconciliation</span> (سٹیٹمنٹ سے ملانا)، <span class="en">Cheques</span> (چیک رجسٹر)۔</td></tr>
                            <tr><td><span class="en">Reports</span></td><td>بینک رجسٹر کی رپورٹس، <span class="en">Export to Excel</span> (جو انٹریاں اس وقت سامنے ہیں وہی، انہی فلٹر کے ساتھ) اور <span class="en">Print this page</span>۔</td></tr>
                            <tr><td><span class="en">Setup</span></td><td><span class="en">Categories</span>، <span class="en">Party aliases</span>، <span class="en">Auto-classify rules</span> اور یہ رہنمائی۔</td></tr>
                        </table>
                        <div class="note">منظوری کی الگ سکرین اور امپورٹ کی ہوئی فائلوں کی الگ فہرست اب نہیں ہے۔ منظوری کے لیے رجسٹر کا ٹیب <span class="en">Waiting for approval</span> ہے، اور کسی ایک فائل کی لائنیں دیکھنے کے لیے فلٹر <span class="en">Statement file</span>۔</div>
                    </section>

                    <section id="lifecycle">
                        <h2>انٹری کی زندگی (لائف سائیکل)</h2>
                        <p>ہر انٹری ان حالتوں سے گزرتی ہے۔ رجسٹر کے ٹیب بھی اسی ترتیب میں ہیں اور ہر ٹیب پر گنتی لکھی ہے۔ ٹیب ہمیشہ نظر آتے ہیں؛ جس میں کوئی انٹری نہ ہو وہ ہلکا دکھتا ہے۔</p>
                        <table>
                            <tr><th>حالت</th><th>مطلب</th><th>اب کون کیا کرے</th><th>اگلا قدم</th></tr>
                            <tr><td><span class="badge b-gray">To classify</span></td><td>انٹری آ گئی ہے مگر کیٹیگری نہیں لگی۔</td><td>میکر <span class="en">Classify</span> دبا کر کمپنی اور کیٹیگری لگائے۔</td><td><span class="en">Ready to post</span></td></tr>
                            <tr><td><span class="badge b-blue">Ready to post</span></td><td>کیٹیگری لگ چکی ہے، ابھی کھاتے میں نہیں گئی۔</td><td>میکر <span class="en">Post</span> دبائے۔ چھوٹی رقم فوراً پوسٹ، بڑی منظوری کو جاتی ہے۔</td><td><span class="en">Posted</span> یا <span class="en">Waiting for approval</span></td></tr>
                            <tr><td><span class="badge b-amber">Waiting for approval</span></td><td>رقم حد سے زیادہ ہے؛ دوسرے آدمی کی منظوری چاہیے۔</td><td>چیکر (اور بہت بڑی رقم پر سینئر) <span class="en">Approve</span> یا <span class="en">Reject</span> کرے۔</td><td><span class="en">Posted</span> یا <span class="en">Rejected</span></td></tr>
                            <tr><td><span class="badge b-red">Rejected</span></td><td>چیکر نے وجہ لکھ کر واپس کر دی۔</td><td>میکر درست کر کے دوبارہ <span class="en">Post</span> دبائے۔</td><td><span class="en">Waiting for approval</span></td></tr>
                            <tr><td><span class="badge b-green">Posted</span></td><td>کھاتے میں چلی گئی، رسید نمبر مل گیا، لاک ہے۔</td><td>غلط ہو تو <span class="en">Reverse entry</span>؛ ورنہ ریکنسیلی ایشن کا انتظار۔</td><td><span class="en">Reconciled</span> یا <span class="en">Reversed</span></td></tr>
                            <tr><td><span class="badge b-green">Reconciled</span></td><td>بینک سٹیٹمنٹ سے مل گئی اور مدت لاک ہو گئی۔</td><td>کچھ نہیں۔ بدلنا ہو تو پہلے ریکنسیلی ایشن واپس کرنی ہوگی۔</td><td>—</td></tr>
                            <tr><td><span class="badge b-red">Reversed</span></td><td>پوسٹ ہوئی تھی، پھر الٹی جرنل انٹری سے واپس لی گئی۔</td><td>میکر <span class="en">Edit</span> سے درست کر کے دوبارہ <span class="en">Post</span> کرے (رسید نمبر وہی رہتا ہے) یا ڈیلیٹ کرے۔</td><td><span class="en">Posted</span></td></tr>
                        </table>
                        <div class="tip">ٹیبل کی قطار میں حالت کے نیچے وہی لفظ لکھا ہوتا ہے جو ٹیب پر ہے، اس لیے الجھن نہیں ہوتی۔ ہر قدم (کس نے، کب) انٹری کی <span class="en">History</span> میں لکھا جاتا ہے۔</div>
                    </section>

                    <section id="roles">
                        <h2>کون کیا کرتا ہے</h2>
                        <table>
                            <tr><th>کردار</th><th>کیا کر سکتا ہے</th></tr>
                            <tr><td>میکر / اکاؤنٹنٹ <span class="en">accountant</span></td><td>سٹیٹمنٹ امپورٹ، ہاتھ سے انٹری، رسیدیں اسکین کرنا اور جوڑنا، کمپنی اور کیٹیگری لگانا، فائلیں لگانا، پوسٹ کرنا یا منظوری کے لیے بھیجنا۔ حد سے کم رقم خود پوسٹ کر سکتا ہے۔ تمام رپورٹیں دیکھ سکتا ہے۔</td></tr>
                            <tr><td>چیکر <span class="en">bank-checker</span></td><td>انتظار والی انٹری منظور یا مسترد کرنا، ریورس کرنا، ریکنسیلی ایشن مکمل کرنا۔ منظوری سے پہلے رسید کھول کر دیکھ سکتا ہے۔</td></tr>
                            <tr><td>سینئر منظور کنندہ <span class="en">bank-senior-approver</span></td><td>بہت بڑی رقم کی دوسری منظوری۔</td></tr>
                            <tr><td>آڈیٹر <span class="en">auditor</span></td><td>سب کچھ دیکھنا (رسیدوں سمیت)، ایکسپورٹ اور رپورٹیں۔ کچھ بدل نہیں سکتا۔</td></tr>
                            <tr><td>ایڈمن</td><td>سب کچھ، اور اجازتیں دینا (<span class="en">Settings › Roles</span>)۔ ڈیلیٹ کی اجازت صرف سپر ایڈمن کے پاس ہے۔</td></tr>
                        </table>
                        <div class="note"><b>اصول:</b> جس نے انٹری بھیجی وہ اسے خود منظور نہیں کر سکتا، اور ایک ہی آدمی دونوں منظوریاں نہیں دے سکتا۔ جو بٹن یا سکرین آپ کو نظر نہ آئے، اس کی اجازت آپ کے پاس نہیں۔</div>
                    </section>

                    <section id="daily">
                        <h2>روزانہ کا آسان طریقہ</h2>
                        <ol>
                            <li><b>رسیدیں:</b> دن بھر کی رسیدیں، پرچیاں اور چیک کی کاپیاں موبائل سے فوٹو کر کے <span class="en">Menu › Receipt scans</span> میں ڈال دیں۔</li>
                            <li><b>انٹری:</b> ہر رقم کے لیے <span class="en">+ Add entry</span> دبائیں، رقم اور پارٹی لکھیں۔ <span class="en">Save &amp; add another</span> سے اگلی انٹری فوراً کھل جاتی ہے۔</li>
                            <li><b>سٹیٹمنٹ آئے تو:</b> <span class="en">Import statement</span> سے فائل لگائیں۔</li>
                            <li><b>درجہ بندی:</b> ٹیب <span class="en">To classify</span> کھولیں اور سب پر کمپنی اور کیٹیگری لگا دیں۔</li>
                            <li><b>پوسٹ:</b> ٹیب <span class="en">Ready to post</span> کھولیں، سب پر ٹک لگا کر <span class="en">Submit selected</span> دبائیں۔</li>
                            <li><b>رسید رہ گئی ہو؟</b> ٹیب کی لائن کے آخر میں پیلا بٹن <span class="en">No receipt</span> ایسی تمام انٹریاں دکھاتا ہے۔</li>
                            <li><b>مہینے کے آخر میں:</b> <span class="en">Menu › Reconciliation</span> سے سٹیٹمنٹ ملائیں اور مدت لاک کریں۔</li>
                        </ol>
                        <div class="tip">نیا بینک، کیش یا والٹ اکاؤنٹ <span class="en">Settings › Bank Accounts</span> میں بنتا ہے؛ بنتے ہی رجسٹر میں آ جاتا ہے۔</div>
                    </section>

                    <section id="import">
                        <h2>سٹیٹمنٹ امپورٹ کرنا</h2>
                        <ol>
                            <li>رجسٹر پر <span class="en">Import statement</span> دبائیں۔</li>
                            <li><span class="en">Bank account</span> چنیں اور ایکسل یا <span class="en">CSV</span> فائل لگائیں۔ فائل کی پہلی قطار میں کم از کم <span class="en">Posting date</span> اور <span class="en">Debit / Credit</span> کے عنوان ہوں (یا ایک <span class="en">Amount</span> کالم جس کے ساتھ <span class="en">Dr/Cr</span> ہو)۔ کالموں کی ترتیب کوئی بھی ہو سکتی ہے۔</li>
                            <li>پرنٹ شدہ سٹیٹمنٹ کے اعداد لکھیں: <span class="en">Opening balance</span>، <span class="en">Closing balance</span>، <span class="en">Total debits</span>، <span class="en">Total credits</span>۔ <b>یہ ضرور لکھیں</b>؛ نظام فائل کا جوڑ نکال کر ان سے ملاتا ہے۔</li>
                            <li><span class="en">Read file &amp; show summary</span> دبائیں۔ خلاصہ آئے گا: کتنی قطاریں نئی ہیں، کتنی پہلے سے ہیں، کتنی میں غلطی ہے (غلط قطاروں کی فہرست نیچے ہوتی ہے)۔</li>
                            <li>تینوں جانچیں سبز <span class="badge b-green">Tallies</span> ہوں تو <span class="en">Confirm import</span> دبائیں۔ سرخ <span class="badge b-red">Does not tally</span> ہو تو امپورٹ بند رہتی ہے؛ فائل یا اعداد درست کر کے دوبارہ لگائیں۔</li>
                        </ol>
                        <div class="shots">
                            <figure><img src="{{ $shot('04-import-form') }}" alt="امپورٹ فارم" loading="lazy"><figcaption>پہلا قدم: فائل اور سٹیٹمنٹ کے اعداد</figcaption></figure>
                            <figure><img src="{{ $shot('05-import-preview') }}" alt="امپورٹ کا خلاصہ" loading="lazy"><figcaption>خلاصہ اور جوڑ کی جانچ</figcaption></figure>
                        </div>
                        <div class="tip">ایک ہی فائل دوبارہ لگ جائے تو پرانی انٹریاں دوبارہ نہیں بنتیں؛ وہ <span class="en">Already in system</span> میں گنی جاتی ہیں۔ بعد میں اسی فائل کی لائنیں دیکھنی ہوں تو رجسٹر میں <span class="en">Filters › More filters › Statement file</span> سے فائل چنیں؛ وہاں سے <span class="en">Open its import summary</span> پر خلاصہ بھی کھل جاتا ہے۔</div>
                    </section>

                    <section id="manual">
                        <h2>ہاتھ سے انٹری شامل کرنا</h2>
                        <p>سٹیٹمنٹ فائل نہ ہو، یا رقم آج ہی لکھنی ہو، تو <span class="en">+ Add entry</span> دبائیں۔ سارا فارم ایک ہی خانے میں ہے۔</p>
                        <ol>
                            <li><b>رقم آئی یا گئی:</b> <span class="en">+ Received</span> یا <span class="en">− Paid out</span> دبائیں اور رقم لکھیں۔</li>
                            <li>بینک اکاؤنٹ اور تاریخ چنیں (آج کی تاریخ لگی ہوتی ہے؛ آنے والی تاریخ نہیں چلتی)۔</li>
                            <li>پارٹی کا نام لکھیں۔ اگر اس نام کا الیاس بنا ہو تو کمپنی خود لگ جاتی ہے۔</li>
                            <li>کیٹیگری چنیں (پوسٹ کرنے کے لیے ضروری ہے) یا <span class="en">decide later</span> رہنے دیں۔ کیٹیگریاں رقم کی سمت کے حساب سے خود چھن جاتی ہیں۔</li>
                            <li>رسید ابھی لگانی ہو تو اسی خانے میں فائل چنیں۔</li>
                            <li>ریفرنس / چیک نمبر، نریشن، نوٹ اور ویلیو ڈیٹ کے لیے نیچے <span class="en">More details</span> کھولیں۔</li>
                        </ol>
                        <table>
                            <tr><th>بٹن</th><th>کام</th></tr>
                            <tr><td><span class="en">Save</span></td><td>صرف محفوظ کرتا ہے اور انٹری کا صفحہ کھولتا ہے۔</td></tr>
                            <tr><td><span class="en">Save &amp; add another</span></td><td>محفوظ کر کے اگلی انٹری کا خالی فارم کھولتا ہے، وہی اکاؤنٹ، تاریخ اور سمت کے ساتھ۔</td></tr>
                            <tr><td><span class="en">Save &amp; submit</span></td><td>محفوظ کر کے فوراً پوسٹ کرتا ہے؛ رقم حد سے زیادہ ہو تو منظوری کے لیے بھیج دیتا ہے۔ کیٹیگری ضروری ہے۔</td></tr>
                        </table>
                        <figure><img src="{{ $shot('24-add-entry') }}" alt="نئی انٹری" loading="lazy"><figcaption>ہاتھ سے نئی انٹری</figcaption></figure>
                        <div class="note"><b>دوبارہ تو نہیں ڈل گئی؟</b> اسی اکاؤنٹ، تاریخ اور رقم کی انٹری پہلے سے ہو تو پیلا انتباہ آتا ہے اور موجودہ انٹری کا لنک ملتا ہے۔ واقعی الگ لین دین ہو تو <span class="en">save it anyway</span> پر ٹک لگا کر محفوظ کریں۔</div>
                    </section>

                    <section id="opening">
                        <h2>اوپننگ بیلنس</h2>
                        <p>جس دن سے رجسٹر شروع کریں، اس دن بینک میں جتنی رقم تھی وہ ایک بار اوپننگ بیلنس کے طور پر لکھیں، تاکہ کتاب کا بیلنس بینک سٹیٹمنٹ سے ملے۔</p>
                        <ol>
                            <li><span class="en">+ Add entry</span> دبائیں، بینک اکاؤنٹ چنیں اور تاریخ وہ لکھیں جس دن کا بیلنس ہے (سٹیٹمنٹ کا اوپننگ بیلنس)۔</li>
                            <li>بینک میں رقم ہو تو <span class="en">+ Received</span>؛ اوور ڈرافٹ (منفی بیلنس) ہو تو <span class="en">− Paid out</span>۔</li>
                            <li>کیٹیگری <span class="en">Opening Balance</span> چنیں اور سٹیٹمنٹ کا صفحہ رسید کے طور پر لگائیں۔</li>
                            <li><span class="en">Save &amp; submit</span> دبائیں۔ پوسٹ ہونے پر: بینک ڈیبٹ اور <span class="en">3300 Opening Balance Equity</span> کریڈٹ۔</li>
                        </ol>
                        <div class="note">ہر بینک کا اوپننگ بیلنس <b>ایک ہی بار</b> لگتا ہے؛ دوسرا بھیجیں تو سسٹم روک دیتا ہے۔ غلط ہو تو پہلے والا ریورس کریں، پھر درست لکھیں۔ اس انٹری کو "پہلے سے کتاب میں" والی جانچ نہیں روکتی۔</div>
                        <div class="tip">ریکنسیلی ایشن کا <span class="en">Opening balance</span> خانہ صرف اس مدت کے حساب کے لیے ہے؛ کتاب میں رقم اسی انٹری سے آتی ہے۔</div>
                    </section>

                    <section id="scans">
                        <h2>رسیدوں کا اسکین ان باکس</h2>
                        <p>رسید ملتے ہی فوٹو یا اسکین ان باکس میں ڈال دیں، چاہے انٹری ابھی نہ بنی ہو۔ اس طرح رسید کبھی گم نہیں ہوتی۔</p>
                        <ol>
                            <li><span class="en">Menu › Receipt scans</span> کھولیں۔</li>
                            <li>فائلیں خانے میں گھسیٹ کر چھوڑیں، رسید کی تاریخ لکھیں۔ <b>ایک ہی فائل ہو تو رقم اور سمت بھی لکھ دیں</b>؛ نظام خود ملتی جلتی انٹری ڈھونڈ لیتا ہے۔</li>
                            <li><span class="en">Save to inbox</span> دبائیں۔ ہر فائل کا اپنا کارڈ بنتا ہے۔</li>
                            <li>ہرے ڈبے میں <span class="en">Looks like …</span> نظر آئے تو <span class="en">Attach here</span>؛ نہ ملے تو <span class="en">Create entry from this scan</span> یا انٹری نمبر لکھ کر <span class="en">Link</span>۔</li>
                        </ol>
                        <figure><img src="{{ $shot('25-scan-inbox') }}" alt="اسکین ان باکس" loading="lazy"><figcaption>اسکین ان باکس۔ جتنی رسیدیں ابھی جڑی نہیں، ان کی گنتی <span class="en">Menu</span> کے بٹن پر آتی ہے</figcaption></figure>
                    </section>

                    <section id="register">
                        <h2>رجسٹر، ٹیب اور پاپ اپ</h2>
                        <h3>ٹیب</h3>
                        <p>آٹھ ٹیب ہمیشہ اسی ترتیب میں ہیں: <span class="en">All</span>، <span class="en">To classify</span>، <span class="en">Ready to post</span>، <span class="en">Waiting for approval</span>، <span class="en">Rejected</span>، <span class="en">Posted</span>، <span class="en">Reconciled</span>، <span class="en">Reversed</span>۔ آخر میں پیلا بٹن <span class="en">No receipt</span> وہ انٹریاں دکھاتا ہے جن کی رسید نہیں لگی؛ دوبارہ دبانے سے ہٹ جاتا ہے۔</p>
                        <p>ٹیبل کے اوپر موجودہ فہرست کے کل لکھے ہیں: انٹریاں، وصولی، ادائیگی اور نیٹ۔</p>
                        <h3>قطار کے بٹن</h3>
                        <table>
                            <tr><th>بٹن</th><th>کب نظر آتا ہے</th><th>کام</th></tr>
                            <tr><td><span class="en">Classify</span></td><td>کیٹیگری نہ لگی ہو</td><td>کمپنی اور کیٹیگری لگانے کا فارم۔</td></tr>
                            <tr><td><span class="en">Post</span></td><td><span class="en">Ready to post</span>، <span class="en">Rejected</span> یا <span class="en">Reversed</span></td><td>پوسٹ کرتا ہے؛ رقم حد سے زیادہ ہو تو منظوری کے لیے بھیجتا ہے۔</td></tr>
                            <tr><td><span class="en">Approve</span></td><td><span class="en">Waiting for approval</span>، اور آپ منظور کر سکتے ہوں</td><td>منظوری؛ آخری منظوری کے ساتھ ہی پوسٹ۔</td></tr>
                            <tr><td><span class="en">Edit</span></td><td>پوسٹ ہونے سے پہلے</td><td>درستی کا فارم۔</td></tr>
                            <tr><td>آنکھ کا نشان</td><td>ہمیشہ</td><td>ٹرانزیکشن کا پاپ اپ۔ قطار پر کہیں بھی کلک سے بھی کھلتا ہے۔</td></tr>
                        </table>
                        <h3>ٹرانزیکشن کا پاپ اپ</h3>
                        <p>پاپ اپ میں چار ٹیبل ہیں: <b>۱ - Entry</b> (تاریخ، اکاؤنٹ، رقم، رسید نمبر، پارٹی، کمپنی، کیٹیگری، ریفرنس، نریشن، نوٹ، جرنل)، <b>۲ - Approval</b> (کس نے کب بھیجا اور منظور کیا)، <b>۳ - Receipt &amp; attachments</b> (فائلیں) اور <b>۴ - History</b> (ہر قدم)۔ نیچے وہی بٹن ہیں جن کی آپ کو اجازت ہے۔ <span class="en">Esc</span> یا <span class="en">Close</span> سے بند ہوتا ہے، اور <span class="en">Print</span> سے صرف وہی ٹرانزیکشن چھپتی ہے۔</p>
                        <figure><img src="{{ $shot('03-transaction-modal') }}" alt="ٹرانزیکشن کا پاپ اپ" loading="lazy"><figcaption>ٹرانزیکشن کا پاپ اپ</figcaption></figure>
                    </section>

                    <section id="filters">
                        <h2>تمام فلٹر</h2>
                        <p><span class="en">Filters</span> دبائیں تو تلاش کا خانہ کھلتا ہے۔ پہلی لائن میں روز کے فلٹر ہیں؛ باقی <span class="en">More filters</span> میں۔ فلٹر چن کر <span class="en">Apply</span> دبائیں۔ فلٹر اس ٹیب پر بھی لاگو رہتے ہیں جو آپ نے کھولا ہو۔</p>
                        <figure><img src="{{ $shot('02-filters') }}" alt="فلٹر" loading="lazy"><figcaption>فلٹر کا خانہ، <span class="en">More filters</span> کھلا ہوا</figcaption></figure>
                        <table>
                            <tr><th>فلٹر</th><th>کیا ڈھونڈتا ہے</th></tr>
                            <tr><td><span class="en">Search</span></td><td>کوئی بھی لفظ: پارٹی، ریفرنس، رسید نمبر، نریشن، نوٹ یا لگی ہوئی فائل کا نام۔ اردو بھی چلتی ہے۔</td></tr>
                            <tr><td><span class="en">From</span> / <span class="en">To</span></td><td>تاریخ سے تاریخ تک۔</td></tr>
                            <tr><td><span class="en">Account</span></td><td>ایک بینک، کیش یا والٹ اکاؤنٹ۔</td></tr>
                            <tr><td><span class="en">Money</span></td><td><span class="en">Received</span> (رقم آئی) یا <span class="en">Paid out</span> (رقم گئی)۔</td></tr>
                            <tr><td><span class="en">Company / principal</span></td><td>ایک کمپنی، یا <span class="en">no company</span> یعنی جن پر کمپنی نہیں لگی۔</td></tr>
                            <tr><td><span class="en">Category</span></td><td>ایک کیٹیگری، یا <span class="en">no category</span>۔</td></tr>
                            <tr><td><span class="en">Amount from</span> / <span class="en">Amount to</span></td><td>رقم کی حد، مثلاً ۱۰ لاکھ سے اوپر کی سب انٹریاں۔</td></tr>
                            <tr><td><span class="en">Reference</span></td><td>بینک کا ریفرنس یا چیک نمبر۔</td></tr>
                            <tr><td><span class="en">Receipt no.</span></td><td>رجسٹر کا رسید نمبر، جیسے <span class="en">BR-2026-27-000001</span>۔</td></tr>
                            <tr><td><span class="en">Receipt file</span></td><td><span class="en">Has attachment</span> یا <span class="en">No attachment</span>۔</td></tr>
                            <tr><td><span class="en">Review flag</span></td><td>جن پر جائزے کا نشان لگا ہے یا نہیں لگا۔</td></tr>
                            <tr><td><span class="en">Statement file</span></td><td>کسی ایک امپورٹ کی ہوئی فائل کی لائنیں۔</td></tr>
                            <tr><td><span class="en">Maker</span></td><td>جس آدمی نے انٹری بھیجی۔</td></tr>
                        </table>
                        <ul>
                            <li><b>Quick dates:</b> ایک کلک میں <span class="en">Today</span>، <span class="en">Yesterday</span>، <span class="en">This week</span>، <span class="en">This month</span>، <span class="en">Last month</span>۔ <span class="en">× Clear</span> سب فلٹر ہٹا دیتا ہے۔</li>
                            <li><b>گولیاں:</b> جو فلٹر لگا ہو وہ ٹیب کے نیچے گولی کی شکل میں دکھتا ہے؛ × سے صرف وہی ہٹتا ہے۔</li>
                            <li><b>ایکسپورٹ:</b> فلٹر لگا کر <span class="en">Menu › Export to Excel</span> دبائیں تو وہی انٹریاں ایکسل میں آتی ہیں۔</li>
                        </ul>
                    </section>

                    <section id="classify">
                        <h2>کمپنی اور کیٹیگری لگانا</h2>
                        <div class="tip"><b>کمپنی</b> سے مراد پرنسپل / سپلائر ہے (نیسلے، اینگرو وغیرہ)؛ فہرست سسٹم کے <span class="en">Suppliers</span> سے آتی ہے۔ ہر ڈراپ ڈاؤن میں تلاش ہے: ڈبے پر کلک کریں، چند حروف لکھیں، نتیجہ چنیں۔</div>
                        <h3>ایک ایک کر کے</h3>
                        <p>قطار میں <span class="en">Classify</span> (یا <span class="en">Edit</span>) دبائیں، کمپنی اور کیٹیگری چنیں، <span class="en">Save</span> دبائیں۔ کیٹیگری لگتے ہی انٹری <span class="badge b-blue">Ready to post</span> ہو جاتی ہے۔ ہاتھ سے بنائی ہوئی انٹری میں رقم، تاریخ، سمت اور پارٹی بھی یہیں درست ہوتی ہیں؛ امپورٹ کی ہوئی انٹری میں صرف کمپنی، کیٹیگری اور نوٹ بدلتے ہیں۔</p>
                        <h3>بہت سی ایک ساتھ</h3>
                        <ol>
                            <li>قطاروں کے شروع میں ٹک لگائیں (سب کے لیے سب سے اوپر والا خانہ)۔</li>
                            <li>سکرین کے <b>نیچے</b> ایک پٹی آتی ہے۔ اس میں کمپنی اور/یا کیٹیگری چنیں۔</li>
                            <li><span class="en">Remember this party next time</span> پر ٹک لگائیں تو نظام اس پارٹی کا الیاس اور رول خود بنا لیتا ہے؛ اگلی بار یہی نام خود لگ جائے گا۔</li>
                            <li><span class="en">Apply</span> دبائیں۔ اسی پٹی میں <span class="en">Submit selected</span> سے ٹک کی ہوئی سب انٹریاں پوسٹ ہو جاتی ہیں۔</li>
                        </ol>
                        <div class="shots">
                            <figure><img src="{{ $shot('07-edit-entry') }}" alt="انٹری کی درستی" loading="lazy"><figcaption>ایک انٹری کا فارم</figcaption></figure>
                            <figure><img src="{{ $shot('06-classify-bulk') }}" alt="اکٹھی درجہ بندی" loading="lazy"><figcaption>ٹک لگانے پر نیچے آنے والی پٹی</figcaption></figure>
                        </div>
                        <div class="tip"><span class="en">Auto-classify</span> دبائیں تو نظام الیاس، رولز اور چیک رجسٹر تینوں کو تمام باقی انٹریوں پر چلا دیتا ہے۔</div>
                    </section>

                    <section id="attach">
                        <h2>رسید اور اٹیچمنٹ</h2>
                        <p>انٹری کے پاپ اپ یا صفحے پر ٹیبل <b>۳ - Receipt &amp; attachments</b> کے نیچے تین خانے ہیں: فائل چنیں، <span class="en">Kind of file</span> میں قسم (<span class="en">Receipt / Cheque / Deposit slip / Statement / Other</span>) چنیں، چاہیں تو وضاحت لکھیں، اور <span class="en">Attach</span> دبائیں۔ ایک ساتھ دس تک فائلیں لگتی ہیں (پی ڈی ایف، تصویر، ایکسل)۔</p>
                        <figure><img src="{{ $shot('08-attachments') }}" alt="اٹیچمنٹ" loading="lazy"><figcaption>رسید لگانے کے خانے، اور نیچے تاریخچہ اور بٹن</figcaption></figure>
                        <ul>
                            <li>فائلیں نجی ہیں؛ صرف وہی کھول سکتا ہے جسے رجسٹر کی اجازت ہو۔</li>
                            <li>ریکنسائل ہو جانے کے بعد اٹیچمنٹ نہ لگتی ہے نہ ہٹتی ہے۔</li>
                        </ul>
                    </section>

                    <section id="maker">
                        <h2>پوسٹ کرنا یا منظوری کے لیے بھیجنا</h2>
                        <p>کیٹیگری لگنے کے بعد قطار میں <span class="en">Post</span> دبائیں (یا بہت سی ٹک کر کے <span class="en">Submit selected</span>)۔ آگے کیا ہوتا ہے، یہ رقم پر منحصر ہے:</p>
                        <table>
                            <tr><th>رقم</th><th>کیا ہوتا ہے</th></tr>
                            <tr><td>۱۰ لاکھ سے کم</td><td>فوراً پوسٹ؛ رسید نمبر بن جاتا ہے۔ کسی منظوری کی ضرورت نہیں۔</td></tr>
                            <tr><td>۱۰ لاکھ یا زیادہ</td><td>چیکر کی منظوری ضروری ہے۔ حالت <span class="badge b-amber">Waiting for approval</span> ہو جاتی ہے۔</td></tr>
                            <tr><td>۱ کروڑ یا زیادہ</td><td>چیکر کے بعد سینئر منظور کنندہ کی دوسری منظوری بھی ضروری ہے۔</td></tr>
                        </table>
                        <div class="warn">انتظار والی انٹری کی <b>رقم، تاریخ، کمپنی یا کیٹیگری</b> بدلی جائے تو منظوری کی درخواست واپس ہو جاتی ہے اور دوبارہ بھیجنا پڑتا ہے۔ صرف نوٹ بدلنے سے کچھ نہیں بدلتا۔</div>
                    </section>

                    <section id="checker">
                        <h2>منظور یا مسترد کرنا</h2>
                        <p>منظوری کی الگ سکرین نہیں ہے۔ رجسٹر کا ٹیب <span class="en">Waiting for approval</span> ہی چیکر کا ان باکس ہے؛ اوپر کے مینو میں <span class="en">Bank Register</span> کے ساتھ انتظار والی انٹریوں کی گنتی بھی آتی ہے۔</p>
                        <ol>
                            <li>ٹیب <span class="en">Waiting for approval</span> کھولیں۔ اوپر نیلی پٹی میں منظوری کی حدیں لکھی ہیں۔</li>
                            <li>حالت کے نیچے لکھا ہے کہ چیکر (اور سینئر) کی منظوری ہو چکی یا باقی ہے، اور کس نے کب بھیجی۔</li>
                            <li><b>رسید دیکھیں:</b> <span class="en">Files</span> کے خانے میں فائل کے نام پر کلک کریں تو رسید نئے ٹیب میں کھلتی ہے۔ <span class="badge b-amber">No receipt</span> لکھا ہو تو منظوری سے پہلے رسید مانگیں۔</li>
                            <li>ٹھیک ہو تو <span class="en">Approve</span>۔ آخری ضروری منظوری کے ساتھ ہی انٹری پوسٹ ہو جاتی ہے۔</li>
                            <li>غلط ہو تو آنکھ کے نشان سے پاپ اپ کھولیں اور <span class="en">Reject</span> دبائیں؛ کھڑکی میں وجہ لکھنی ضروری ہے۔</li>
                            <li>بہت سی ایک ساتھ: ٹک لگائیں اور نیچے کی پٹی میں <span class="en">Approve selected</span> (ایک بار میں زیادہ سے زیادہ ۱۰۰)۔</li>
                        </ol>
                        <figure><img src="{{ $shot('10-approvals') }}" alt="منظوری کا ٹیب" loading="lazy"><figcaption>ٹیب <span class="en">Waiting for approval</span>۔ یہاں انٹری بھیجنے والا خود لاگ ان ہے، اس لیے <span class="en">Approve</span> کا بٹن نہیں</figcaption></figure>
                        <div class="tip">اپنی بھیجی ہوئی انٹری پر آپ کو <span class="en">Approve</span> کا بٹن نظر نہیں آتا؛ کوئی دوسرا آدمی منظور کرے گا۔</div>
                    </section>

                    <section id="posting">
                        <h2>پوسٹنگ: رقم اصل کھاتوں میں</h2>
                        <p>رجسٹر کا کوئی الگ حساب نہیں۔ ہر انٹری اصل <span class="en">Chart of Accounts</span> میں جاتی ہے، اس لیے <span class="en">Trial Balance</span>، <span class="en">Balance Sheet</span> اور <span class="en">Income Statement</span> میں شامل ہوتی ہے۔ بینک کی طرف ہمیشہ اسی بینک کا اپنا اکاؤنٹ (مثلاً <span class="en">1171 HBL Main Account</span>) ہوتا ہے، وہی جس میں سیل سیٹلمنٹ، سپلائر ادائیگی اور تنخواہ جاتی ہے۔</p>
                        <h3>ایک رقم دو بار نہیں: لنک یا پوسٹ</h3>
                        <ol>
                            <li><b>لنک</b> <span class="en">Link to book entry</span>: جو رقم کسی اور ماڈیول نے پہلے ہی کتاب میں لکھ دی (سیٹلمنٹ کا بینک ٹرانسفر، سلپ یا آن لائن ریکوری، سپلائر ادائیگی، تنخواہ)، اسے دوبارہ پوسٹ نہیں کیا جاتا۔ انٹری اس جرنل لائن سے جوڑ دی جاتی ہے؛ کوئی نیا جرنل نہیں بنتا۔</li>
                            <li><b>پوسٹ</b>: جو رقم کہیں اور نہیں لکھی گئی (بینک چارجز، بینک کا کاٹا ہوا ٹیکس، مالک کی رقم، ایک بینک سے دوسرے میں منتقلی، نامعلوم رقم)، اس کا متوازن <span class="en">Journal Entry</span> بنتا ہے اور رسید نمبر ملتا ہے: <span class="en">BR-2026-27-000001</span> (رقم آئی) یا <span class="en">BP-2026-27-000001</span> (رقم گئی)۔ مالی سال جولائی سے جون ہے۔</li>
                        </ol>
                        <p class="warn">اگر بینک کے اکاؤنٹ میں اسی رقم اور اسی سمت کی لائن تین دن کے اندر پہلے سے موجود ہو تو نظام پوسٹ نہیں کرنے دیتا اور پاپ اپ میں <span class="en">Already in the books?</span> کی فہرست دکھاتا ہے: وہاں <span class="en">Link</span> دبائیں۔ اگر واقعی الگ لین دین ہے تو <span class="en">confirm it is different</span> میں وجہ لکھ کر آگے بڑھیں؛ وجہ انٹری پر محفوظ رہتی ہے۔</p>
                        <h3>کیٹیگری کس کھاتے میں جاتی ہے</h3>
                        <table>
                            <tr><th>کیٹیگری</th><th>دوسری طرف کا کھاتہ</th></tr>
                            <tr><td><span class="en">Customer Receipt</span></td><td class="en-cell">1111 Debtors</td></tr>
                            <tr><td><span class="en">Principal / Supplier Payment</span></td><td class="en-cell">2111 Creditors</td></tr>
                            <tr><td><span class="en">Cash Deposit</span></td><td class="en-cell">1121 Cash</td></tr>
                            <tr><td><span class="en">Salary / Staff</span></td><td class="en-cell">5281 Salary</td></tr>
                            <tr><td><span class="en">Bank Charges</span></td><td class="en-cell">Bank Charges (5200 کے نیچے)</td></tr>
                            <tr><td><span class="en">Tax / Government</span></td><td class="en-cell">Advance Tax - Bank Deductions (1160 کے نیچے)</td></tr>
                            <tr><td><span class="en">Inter-bank / Own Transfer</span></td><td class="en-cell">Bank Transfers in Transit (1100 کے نیچے)</td></tr>
                            <tr><td><span class="en">Owner / Partner</span></td><td class="en-cell">Owner / Partner Current Account (3000 کے نیچے)</td></tr>
                            <tr><td><span class="en">Other</span> اور نامعلوم</td><td class="en-cell">Bank Suspense (2100 کے نیچے)</td></tr>
                        </table>
                        <p class="tip">یہ کھاتے <span class="en">Settings › Bank categories</span> میں ہر کیٹیگری پر بدلے جا سکتے ہیں۔ <span class="en">Bank Transfers in Transit</span> اور <span class="en">Bank Suspense</span> کا بیلنس مہینے کے آخر میں صفر ہونا چاہیے: ٹرانسفر کی دونوں طرف آ جائیں تو صفر، اور نامعلوم رقم پہچان کر صحیح کھاتے میں منتقل کریں۔</p>
                        <h3>سیل سیٹلمنٹ کی بینک رقم</h3>
                        <p>سیٹلمنٹ پوسٹ ہوتے ہی اس کی آن لائن ریکوری، بینک ٹرانسفر اور بینک سلپ رجسٹر میں <span class="en">Booked by settlement</span> بن کر آ جاتی ہیں، سیٹلمنٹ کے جرنل سے جڑی ہوئی۔ انہیں پوسٹ یا ریورس نہیں کیا جاتا؛ صرف بینک سٹیٹمنٹ سے ملایا (ریکنسائل) جاتا ہے۔ سٹیٹمنٹ امپورٹ میں وہی رقم آئے تو نئی لائن نہیں بنتی، یہی لائن تصدیق ہو جاتی ہے۔ سیٹلمنٹ ریورٹ ہو تو اس کی لائنیں بھی ریورس ہو جاتی ہیں؛ دوبارہ پوسٹ ہو تو نئے جرنل سے جڑ جاتی ہیں۔ چیک بینک کی رقم نہیں جب تک جمع نہ ہوں: وہ <span class="en">Cheques in Hand</span> میں رہتے ہیں اور <span class="en">Cheque Register</span> سے کلیئر ہوتے ہیں۔</p>
                    </section>

                    <section id="reverse">
                        <h2>ریورس اور ڈیلیٹ</h2>
                        <h3>پوسٹ شدہ انٹری غلط ہو: ریورس</h3>
                        <ol>
                            <li>انٹری کا پاپ اپ کھولیں اور <span class="en">Reverse entry</span> دبائیں۔</li>
                            <li>سرخ کھڑکی میں انٹری کی رقم، تاریخ، اکاؤنٹ، پارٹی اور رسید نمبر دکھتے ہیں۔ <b>وجہ لکھیں</b> اور <span class="en">Reverse entry</span> دبائیں۔</li>
                            <li>نظام الٹی جرنل انٹری پوسٹ کرتا ہے اور انٹری <span class="badge b-red">Reversed</span> ہو جاتی ہے۔ اب اسے <span class="en">Edit</span> سے درست کر کے دوبارہ <span class="en">Post</span> کریں؛ رسید نمبر وہی رہتا ہے۔</li>
                        </ol>
                        <div class="warn">جس انٹری کو پوسٹ ہونے کے لیے منظوری چاہیے تھی (۱۰ لاکھ یا زیادہ)، اسے <b>بھیجنے والا خود ریورس نہیں کر سکتا</b>؛ کوئی دوسرا آدمی جس کے پاس ریورس کی اجازت ہو، کرے گا۔ ریکنسائل شدہ انٹری ریورس نہیں ہوتی؛ پہلے ریکنسیلی ایشن واپس کرنی ہوگی۔</div>
                        <h3>غلطی سے بنی انٹری: ڈیلیٹ</h3>
                        <p>جو انٹری پوسٹ نہیں ہوئی اور منظوری کے انتظار میں بھی نہیں، اس کے پاپ اپ میں <span class="en">Delete entry</span> ہے۔ کھڑکی میں انٹری کی تفصیل دیکھ کر <span class="en">Delete entry</span> دبائیں۔ ڈیلیٹ کی ہوئی انٹری اس سکرین سے واپس نہیں آتی۔</p>
                        <div class="shots">
                            <figure><img src="{{ $shot('37-reverse-modal') }}" alt="ریورس کی کھڑکی" loading="lazy"><figcaption>ریورس: وجہ لکھنا ضروری ہے</figcaption></figure>
                            <figure><img src="{{ $shot('38-delete-modal') }}" alt="ڈیلیٹ کی کھڑکی" loading="lazy"><figcaption>ڈیلیٹ کی تصدیق</figcaption></figure>
                        </div>
                    </section>

                    <section id="recon">
                        <h2>بینک ریکنسیلی ایشن</h2>
                        <p>کتابی بیلنس بینک کے اپنے اکاؤنٹ (مثلاً <span class="en">1171</span>) سے آتا ہے، جس میں سیٹلمنٹ، سپلائر ادائیگی، تنخواہ اور رجسٹر سب کی رقم ہے۔ سیٹلمنٹ ایک بینک کی ساری سلپیں ایک جرنل لائن میں لکھتا ہے، اس لیے کئی سٹیٹمنٹ لائنیں ایک جرنل لائن سے مل سکتی ہیں؛ جتنا حصہ ابھی نہیں ملا وہی <span class="en">Deposits in transit</span> میں گنا جاتا ہے۔</p>
                        <ol>
                            <li><span class="en">Menu › Reconciliation</span> کھولیں اور <span class="en">+ New reconciliation</span> دبائیں۔ بینک اکاؤنٹ، مدت، اور سٹیٹمنٹ کی <span class="en">Opening / Closing balance</span> لکھ کر <span class="en">Start</span> دبائیں۔</li>
                            <li>جو انٹریاں رجسٹر سے پوسٹ ہو چکی ہیں وہ خود جوڑ دی جاتی ہیں (<span class="en">Auto-match posted lines</span> سے دوبارہ بھی چلا سکتے ہیں)۔</li>
                            <li>باقی سٹیٹمنٹ لائنیں بائیں طرف ہیں، ہر ایک کے ساتھ اس کی حالت۔ جو ابھی پوسٹ نہیں ہوئیں انہیں رجسٹر سے پوسٹ کریں۔ کتابوں کی وہ لائنیں جو سٹیٹمنٹ میں نہیں، دائیں طرف ہیں؛ <span class="en">Match</span> سے جوڑیں۔</li>
                            <li>اوپر <span class="en">Difference</span> صفر ہو جائے اور کوئی لائن باقی نہ ہو تو <span class="en">Complete &amp; lock period</span> دبائیں۔</li>
                        </ol>
                        <figure><img src="{{ $shot('12-reconciliation-show') }}" alt="ریکنسیلی ایشن" loading="lazy"><figcaption>اوپر بیلنس اور فرق، نیچے دونوں طرف کی لائنیں</figcaption></figure>
                        <div class="note">مکمل ہونے پر سب انٹریاں <span class="badge b-green">Reconciled</span> ہو کر لاک ہو جاتی ہیں۔ غلطی ملے تو <span class="en">Cancel reconciliation</span> سے مدت کھل جاتی ہے۔</div>
                    </section>

                    <section id="masters">
                        <h2>سیٹ اپ: کیٹیگری، الیاس، رولز، چیک</h2>
                        <p>یہ فہرستیں <span class="en">Menu › Setup</span> میں ہیں (چیک رجسٹر <span class="en">Menu › Daily work › Cheques</span> میں)۔ ہر ایک میں نیا بنانا، بدلنا اور (اجازت ہو تو) حذف کرنا ہے۔</p>
                        <div id="setup-categories">
                            <h3>کیٹیگریاں <span class="en">Categories</span></h3>
                            <p>جیسے <span class="en">Customer Receipt</span>، <span class="en">Principal Payment</span>، <span class="en">Bank Charges</span>۔ ہر کیٹیگری کے لیے طے کریں کہ وہ رقم آنے کے لیے ہے، جانے کے لیے، یا دونوں کے لیے؛ چاہیں تو اس کا اپنا اکاؤنٹ لگائیں۔ جو کیٹیگری استعمال ہو چکی ہو اسے حذف نہیں، غیر فعال کریں۔</p>
                            <div class="shots">
                                <figure><img src="{{ $shot('14-categories') }}" alt="کیٹیگریاں" loading="lazy"><figcaption>کیٹیگریوں کی فہرست</figcaption></figure>
                                <figure><img src="{{ $shot('22-category-form') }}" alt="کیٹیگری کا فارم" loading="lazy"><figcaption>نئی کیٹیگری کا فارم</figcaption></figure>
                            </div>
                        </div>
                        <div id="setup-aliases">
                            <h3>پارٹی الیاس <span class="en">Party aliases</span></h3>
                            <p>ایک ہی نام کئی طرح لکھا آتا ہے (<span class="en">JAMEEL AHMED / JAMEEL AHM</span>)۔ الیاس بتاتا ہے کہ یہ سب ایک ہی صحیح نام اور ایک ہی کمپنی ہیں۔ حروف کا چھوٹا بڑا ہونا اور فالتو جگہیں نظر انداز ہوتی ہیں۔</p>
                            <figure><img src="{{ $shot('15-aliases') }}" alt="الیاس" loading="lazy"><figcaption>الیاس کی فہرست</figcaption></figure>
                        </div>
                        <div id="setup-rules">
                            <h3>خودکار رولز <span class="en">Auto-classify rules</span></h3>
                            <p>"نریشن میں <span class="en">Service Charges</span> ہو تو کیٹیگری <span class="en">Bank Charges</span>" جیسے اصول۔ <span class="en">Priority</span> میں چھوٹا نمبر پہلے چلتا ہے۔ <span class="en">Remember this party next time</span> سے بنے رول <span class="en">Remembered: …</span> کے نام سے یہاں آتے ہیں۔</p>
                            <figure><img src="{{ $shot('16-rules') }}" alt="رولز" loading="lazy"><figcaption>رولز کی فہرست</figcaption></figure>
                        </div>
                        <div id="setup-cheques">
                            <h3>چیک رجسٹر <span class="en">Cheques</span></h3>
                            <p>جاری کیے گئے اور وصول ہونے والے چیک لکھیں۔ سٹیٹمنٹ کے ریفرنس میں وہی چیک نمبر اور وہی رقم آئے تو چیک خود <span class="en">Cleared</span> ہو کر اس لائن سے جڑ جاتا ہے۔</p>
                            <figure><img src="{{ $shot('17-cheques') }}" alt="چیک رجسٹر" loading="lazy"><figcaption>چیک رجسٹر</figcaption></figure>
                        </div>
                    </section>

                    <section id="reports">
                        <h2>رپورٹس</h2>
                        <p><span class="en">Menu › Reports</span> کھولیں۔ ہر رپورٹ میں اوپر فلٹر کا بٹن، نیچے ٹیبل، اور <span class="en">Excel</span> / <span class="en">PDF</span> / پرنٹ کے بٹن ہیں۔ ہر رپورٹ اسی کو نظر آتی ہے جسے اس کی اجازت ہو۔</p>
                        <table>
                            <tr><th>رپورٹ</th><th>کام</th></tr>
                            <tr><td><span class="en">Register entries</span></td><td>تمام انٹریوں کی فہرست۔ فلٹر: اکاؤنٹ، تاریخ، آمد / خرچ، کمپنی، کیٹیگری، حالت، رسید ہے یا نہیں۔ کمپنی چنیں تو اس کا کھاتہ بیلنس کے ساتھ ملتا ہے۔</td></tr>
                            <tr><td><span class="en">Register summary</span></td><td>کل رقوم کا خلاصہ۔ <span class="en">Group by</span>: کمپنی، کیٹیگری، بینک اکاؤنٹ، مہینہ، دن، یا "کمپنی بہ ماہ"۔</td></tr>
                            <tr><td><span class="en">Bank reconciliation statement</span></td><td>سٹیٹمنٹ بیلنس، بقایا چیزیں، کتابی بیلنس اور فرق۔</td></tr>
                            <tr><td><span class="en">Scanned receipts not yet linked</span></td><td>ان باکس کی وہ رسیدیں جو کسی انٹری سے نہیں جڑیں، پرانی پہلے۔</td></tr>
                            <tr><td><span class="en">Not yet in the live books</span></td><td>وہ رقمیں جو رجسٹر میں ہیں مگر سیٹلمنٹ / سپلائر ادائیگی کے لائیو حساب میں ابھی نہیں آئیں۔</td></tr>
                            <tr><td><span class="en">Audit log</span></td><td>کس نے کب کیا کیا۔</td></tr>
                        </table>
                        <div class="shots">
                            <figure><img src="{{ $shot('18-reports-index') }}" alt="رپورٹس" loading="lazy"><figcaption>بینک رجسٹر کی رپورٹس</figcaption></figure>
                            <figure><img src="{{ $shot('20-report-ledger') }}" alt="رجسٹر انٹریز" loading="lazy"><figcaption>رپورٹ <span class="en">Register entries</span></figcaption></figure>
                            <figure><img src="{{ $shot('19-report-company-month') }}" alt="رجسٹر خلاصہ" loading="lazy"><figcaption>رپورٹ <span class="en">Register summary</span> (کمپنی بہ ماہ)</figcaption></figure>
                            <figure><img src="{{ $shot('29-live-gap') }}" alt="لائیو حساب میں نہیں" loading="lazy"><figcaption>رپورٹ <span class="en">Not yet in the live books</span></figcaption></figure>
                        </div>
                    </section>

                    <section id="env">
                        <h2>تمام سیٹنگز (.env)</h2>
                        <p>یہ سیٹنگز کوڈ میں نہیں، سرور کی <span class="en">.env</span> فائل میں ہیں۔ ایڈمن قدر بدل کر <code class="en">php artisan config:clear</code> چلا دے۔</p>
                        <table>
                            <tr><th>سیٹنگ</th><th>موجودہ قدر</th><th>مطلب</th></tr>
                            <tr><td class="en-cell">BANK_MAKER_CHECKER_ENABLED</td><td class="en-cell">true</td><td><span class="en">false</span> کریں تو منظوری بند؛ ہر انٹری میکر خود پوسٹ کرے گا۔</td></tr>
                            <tr><td class="en-cell">BANK_APPROVAL_LEVEL1_THRESHOLD</td><td class="en-cell">1000000</td><td>اس رقم سے چیکر کی منظوری ضروری ہے۔</td></tr>
                            <tr><td class="en-cell">BANK_APPROVAL_LEVEL2_THRESHOLD</td><td class="en-cell">10000000</td><td>اس رقم سے سینئر کی دوسری منظوری بھی ضروری ہے۔</td></tr>
                            <tr><td class="en-cell">BANK_ALLOW_SELF_APPROVAL</td><td class="en-cell">false</td><td><span class="en">true</span> کریں تو بھیجنے والا اپنی انٹری خود منظور اور ریورس کر سکے گا۔</td></tr>
                            <tr><td class="en-cell">BANK_APPROVAL_APPLIES_TO</td><td class="en-cell">both</td><td>منظوری کس پر لگے: <span class="en">debit</span> (صرف ادائیگی)، <span class="en">credit</span> (صرف وصولی) یا <span class="en">both</span>۔</td></tr>
                            <tr><td class="en-cell">BANK_APPROVAL_REQUIRED_FOR_REVERSAL</td><td class="en-cell">true</td><td>منظوری والی انٹری کو بھیجنے والا خود ریورس نہ کر سکے۔</td></tr>
                            <tr><td class="en-cell">BANK_BULK_APPROVAL_MAX_ENTRIES</td><td class="en-cell">100</td><td>ایک بار میں زیادہ سے زیادہ کتنی انٹریاں اکٹھی منظور ہوں۔</td></tr>
                            <tr><td class="en-cell">BANK_ATTACHMENT_DISK</td><td class="en-cell">local</td><td>رسیدیں اور سٹیٹمنٹ فائلیں کہاں رکھی جائیں: <span class="en">local</span> (اسی سرور پر) یا <span class="en">s3</span>۔</td></tr>
                            <tr><td class="en-cell">BANK_ATTACHMENT_MAX_KB</td><td class="en-cell">10240</td><td>ایک فائل کا زیادہ سے زیادہ حجم، کلو بائٹ میں۔</td></tr>
                        </table>
                        <div class="note">"موجودہ قدر" وہ ہے جو اس رہنمائی کے لکھتے وقت تھی۔ اصل قدر سرور کی <span class="en">.env</span> میں دیکھیں؛ رجسٹر کے ٹیب <span class="en">Waiting for approval</span> پر بھی حدیں لکھی ہوتی ہیں۔</div>
                    </section>

                    <section id="print">
                        <h2>پرنٹ کرنا (لیزر پرنٹر، سیاہ و سفید)</h2>
                        <ul>
                            <li>کسی بھی سکرین پر <span class="en">Menu › Print this page</span>، یا <span class="en">Ctrl + P</span>۔</li>
                            <li>پرنٹ سیاہ و سفید ہے: کوئی رنگ نہیں، ہر ٹیبل سیاہ لکیروں والے خانوں میں۔ مینو، بٹن، فلٹر اور ٹک کے خانے خود ہٹ جاتے ہیں۔</li>
                            <li><b>رجسٹر:</b> فلٹر لگا کر پرنٹ کریں تو وہی انٹریاں چھپتی ہیں جو سکرین پر ہیں۔ صفحہ <span class="en">A4 Landscape</span> ہے۔</li>
                            <li><b>ایک ٹرانزیکشن:</b> پاپ اپ کے <span class="en">Print</span> سے؛ چاروں ٹیبل (انٹری، منظوری، رسیدیں، تاریخچہ) شامل ہوتے ہیں۔</li>
                            <li><b>رپورٹس:</b> ہر رپورٹ میں <span class="en">Excel</span> اور <span class="en">PDF</span> کے بٹن بھی ہیں۔</li>
                            <li><b>یہ رہنمائی:</b> اوپر کے <span class="en">Print</span> سے پوری چھپتی ہے۔</li>
                        </ul>
                    </section>

                    <section id="flows">
                        <h2>تمام فلو ایک نظر میں</h2>
                        <p>ہر سکرین کی <span class="en">Help</span> ونڈو کے آخر میں اسی سکرین کا فلو آتا ہے۔ یہاں سب ایک ساتھ ہیں۔</p>
                        @foreach (array_keys(\App\Support\HelpFlows::all()) as $flowKey)
                            <x-help-flow :flow="$flowKey" />
                        @endforeach
                    </section>

                    <section id="faq">
                        <h2>عام سوالات اور مسائل</h2>
                        <table>
                            <tr><th>مسئلہ</th><th>حل</th></tr>
                            <tr><td>امپورٹ <span class="en">Does not tally</span> کہتی ہے</td><td>پرنٹ شدہ سٹیٹمنٹ کے کل ڈیبٹ / کریڈٹ دوبارہ دیکھیں، یا فائل میں کوئی قطار رہ گئی ہو۔</td></tr>
                            <tr><td><span class="en">File layout was not recognised</span></td><td>فائل کی پہلی قطار میں <span class="en">Posting date</span> اور <span class="en">Debit</span> / <span class="en">Credit</span> کے عنوان لکھیں۔</td></tr>
                            <tr><td>قطار میں <span class="en">Post</span> کا بٹن نہیں ہے</td><td>پہلے کیٹیگری لگائیں؛ حالت <span class="en">Ready to post</span>، <span class="en">Rejected</span> یا <span class="en">Reversed</span> ہونی چاہیے۔</td></tr>
                            <tr><td><span class="en">You submitted this entry, so a different person must approve it</span></td><td>بھیجنے والا اپنی انٹری منظور نہیں کر سکتا؛ چیکر سے کہیں۔</td></tr>
                            <tr><td><span class="en">needs a senior approver</span></td><td>رقم دوسرے درجے کی حد سے اوپر ہے؛ سینئر منظور کنندہ منظور کرے۔</td></tr>
                            <tr><td>ریورس کا بٹن نہیں، لکھا ہے کہ کوئی اور ریورس کرے</td><td>انٹری منظوری والی تھی اور آپ نے بھیجی تھی؛ کوئی دوسرا آدمی ریورس کرے گا۔</td></tr>
                            <tr><td>انٹری بدل نہیں سکتی</td><td>پوسٹ شدہ انٹری لاک ہے؛ پہلے ریورس کریں۔</td></tr>
                            <tr><td>انٹری دو بار بن گئی</td><td>جو پوسٹ نہیں ہوئی اسے <span class="en">Delete entry</span> سے حذف کریں؛ پوسٹ ہو چکی ہو تو ریورس کریں۔</td></tr>
                            <tr><td>رجسٹر کہتا ہے یہ رقم پہلے سے کتاب میں ہے</td><td>وہی رقم ±3 دن میں کسی اور ماڈیول (سیٹلمنٹ، سپلائر پیمنٹ) سے بُک ہے؛ <span class="en">Link</span> کریں۔ واقعی الگ لین دین ہو تو وجہ لکھ کر پوسٹ کریں۔</td></tr>
                            <tr><td>ریکنسیلی ایشن مکمل نہیں ہو رہی</td><td>دیکھیں کہ کوئی سٹیٹمنٹ لائن بغیر جوڑ کے تو نہیں، اور <span class="en">Difference</span> صفر ہے۔</td></tr>
                            <tr><td>کیٹیگری، الیاس یا رولز کہاں ہیں؟</td><td><span class="en">Settings › Bank Register Setup</span> میں؛ باقی سب <span class="en">Bank Register › Menu</span> میں۔</td></tr>
                            <tr><td>کوئی بٹن یا سکرین نظر نہیں آتی</td><td>اجازت نہیں ہوگی؛ ایڈمن سے <span class="en">Settings › Roles</span> میں اجازت دلوائیں۔</td></tr>
                        </table>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

{{--
    Reports -> Cash Collection Distributor -> How this report works (/reports/cash-collection-distributor/help).
    Urdu, right-to-left guide: tables, the signed rows of the denominations, expenses, the notes check and permissions.
    Static page: no data is read. The Urdu styles (.bh) and the Inter / Noto Nastaliq Urdu fonts come from x-bank.help-style.
--}}
@php
    $sections = [
        'what' => 'رپورٹ کیا دکھاتی ہے', 'tables' => 'چھ ٹیبل', 'zero' => 'خالی ٹیبل اور صفر والی کمپنی', 'notes' => 'نوٹوں کا حساب: نیٹ = کیش ان ہینڈ',
        'signed' => 'نشان والی لائنوں کا مطلب', 'expenses' => 'اخراجات: کمپنی کے حساب سے، سیلز مین کے حساب سے نہیں',
        'clearing' => 'چیک کلیئرنگ', 'perm' => 'اجازتیں',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('reports.cash-collection-distributor.index') }}">Cash Collection Distributor</a><span aria-hidden="true">›</span><span>How it works</span>
                </nav>
                <h1 class="ak-title">How the Cash Collection Distributor report works</h1>
                <p class="ak-sub">The report guide, in Urdu.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('reports.cash-collection-distributor.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to the report</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-bank.help-style />
    <style>@media print { .bh-toc { display: none; } .bh section { box-shadow: none; break-inside: avoid; } }</style>

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bh" lang="ur" dir="rtl">
            <div class="bh-layout">
                <nav class="bh-toc" aria-label="فہرست">
                    <b>فہرست</b>
                    <ol>
                        @foreach ($sections as $id => $title)<li><a href="#{{ $id }}">{{ $title }}</a></li>@endforeach
                    </ol>
                </nav>

                <div class="bh-main">
                    <section id="what">
                        <h2>رپورٹ کیا دکھاتی ہے؟</h2>
                        <p>ایک دن میں جتنی رقم وصول ہوئی، وہ <b>کمپنی (ڈسٹری بیوشن)</b> کے حساب سے یہاں ملتی ہے۔ فلٹر کھولیں، کمپنی یا <span class="en">All Companies</span> چنیں، تاریخ لگائیں اور <span class="en">Apply</span> دبائیں۔ اس سے پہلے صفحہ خالی رہتا ہے۔</p>
                        <ul>
                            <li><b>تاریخ</b> سیٹلمنٹ کی تاریخ ہے۔ چیک کلیئرنگ اس دن کی گنی جاتی ہے جس دن چیک کلیئر ہوا۔</li>
                            <li>صرف <span class="en">Posted</span> سیٹلمنٹ گنی جاتی ہیں۔ ڈرافٹ سیٹلمنٹ رپورٹ میں نہیں آتی۔</li>
                            <li><b>عہدہ</b> (پہلے سے <span class="en">Salesman</span>) اور <b>سیلز مین</b> کا فلٹر سیٹلمنٹ والے ٹیبلوں کو چھوٹا کرتا ہے۔</li>
                            <li>ایک کمپنی کی رقم وہی ہے جو <span class="en">All Companies</span> میں اسی کمپنی کی لائن میں ہے۔</li>
                            <li>سکرین، پرنٹ (<span class="en">Ctrl/Cmd+P</span>) اور <span class="en">PDF</span> میں ایک ہی شیٹ آتی ہے۔</li>
                        </ul>
                        <div class="note">جس صارف کے پاس <span class="en">All Companies</span> کی اجازت نہیں، وہ صرف اپنی کمپنی دیکھ سکتا ہے۔ دوسری کمپنی یا <span class="en">All</span> مانگنے پر <span class="en">403</span> آتا ہے۔</div>
                    </section>

                    <section id="tables">
                        <h2>چھ ٹیبل</h2>
                        <table>
                            <tr><th>ٹیبل</th><th>کیا دکھاتا ہے</th></tr>
                            <tr><td><span class="en">1 - Collection by Distribution</span></td><td>ہر کمپنی کی ایک لائن، ایک ہی ٹیبل میں: <span class="en">Cash</span>، <span class="en">Cheque clearing</span>، <span class="en">Bank slips</span>، <span class="en">Online</span>، <span class="en">Cheques received</span>، <span class="en">Cleared into bank</span> اور <span class="en">Total</span>۔ ٹوٹل = کیش + بینک سلپ + آن لائن + اس دن کی سیٹلمنٹ میں ملے چیک۔ جو چیک بینک نے کلیئر کیے ان سے کیش واپس نہیں آیا، اس لیے وہ ٹوٹل میں نہیں جڑتے، الگ کالم میں آتے ہیں۔</td></tr>
                            <tr><td><span class="en">2 - Bank Slip Details</span></td><td>کمپنی اور سیلز مین کی لائن، اور ہر بینک اکاؤنٹ کا الگ کالم۔ دو اکاؤنٹ استعمال کرنے والے سیلز مین کی رقم دونوں کالموں میں آتی ہے۔</td></tr>
                            <tr><td><span class="en">3 - Cash Denominations</span></td><td>نوٹ اندر (<span class="en">Notes in</span>)، نوٹ باہر (<span class="en">Notes out</span>) اور نیٹ۔ اگلے حصے دیکھیں۔</td></tr>
                            <tr><td><span class="en">4 - Expenses</span></td><td>اس دن نقد نکلی ہوئی رقم: کیش اور ایڈوانس واؤچر، دکانداروں کو کیش ادائیگی اور کیش تنخواہ۔ کسی دوسرے دن ریورس ہونے والا واؤچر اسی ریورس کے دن <span class="en">Returned: …</span> کی منفی لائن بن کر آتا ہے۔</td></tr>
                            <tr><td><span class="en">5 - Cash Summary</span></td><td>دن کا حساب: نوٹ اور سکے + چیک کلیئرنگ = موصول کیش؛ اس میں سے نکلی رقم گھٹا کر <span class="en">Cash in hand</span>؛ پھر بینک سلپ، آن لائن، چیک اور آخر میں کل کلیکشن۔ واؤچر کی لائن کا نام <span class="en">net of returned</span> ہے، یعنی واپس آئی رقم اس میں سے پہلے ہی کم ہے۔</td></tr>
                            <tr><td><span class="en">6 - Salesman-wise Detail</span></td><td>صرف ایک کمپنی کے لیے: ہر سیلز مین کا کیش، سلپ، ٹرانسفر اور چیک۔</td></tr>
                        </table>
                    </section>

                    <section id="zero">
                        <h2>خالی ٹیبل اور صفر والی کمپنی</h2>
                        <ul>
                            <li>جس ٹیبل میں تمام رقمیں صفر ہوں، وہ صرف ایک سطر بن جاتا ہے، مثلاً <span class="en">No cash expenses for this day.</span> خالی خانے یا بھرتی کی خالی لائنیں نہیں چھپتیں۔</li>
                            <li>جس کمپنی کی ساری رقمیں صفر ہوں، اس کی لائن پہلے اور آخری ٹیبل میں چھپا دی جاتی ہے؛ باقی کمپنیوں کے نمبر دوبارہ ایک سے لگتے ہیں۔</li>
                            <li>ٹوٹل ہر حال میں پورا رہتا ہے؛ چھپی ہوئی لائن صفر تھی، اس لیے ٹوٹل نہیں بدلتا۔</li>
                            <li><span class="en">Cash Summary</span> ہمیشہ مکمل دکھتا ہے، چاہے رقمیں صفر ہوں۔</li>
                        </ul>
                    </section>

                    <section id="notes">
                        <h2>نوٹوں کا حساب: نیٹ = کیش ان ہینڈ</h2>
                        <p>ہر نوٹ کی گنتی تین حصوں میں ہے: <b>اندر</b> (سیٹلمنٹ اور چیک کلیئرنگ سے آئے نوٹ)، <b>باہر</b> (واؤچر کے وقت منظور کرنے والے کی گنی ہوئی رقم) اور <b>نیٹ</b> = اندر منفی باہر۔</p>
                        <div class="tip">آخری <span class="en">Net (Rs.)</span> ٹوٹل ہمیشہ <span class="en">Cash in hand</span> کے برابر ہونا چاہیے۔ اگر برابر نہ ہو تو کوئی واؤچر، سیٹلمنٹ یا چیک کلیئرنگ کی گنتی نامکمل ہے۔ اسے پہلے ٹھیک کریں۔</div>
                        <p>پرانے واؤچر جن پر نوٹوں کی گنتی نہیں تھی، ان کی رقم <span class="en">Paid out, no note count</span> میں جاتی ہے، اس لیے پرانے دن بھی توازن میں رہتے ہیں۔</p>
                    </section>

                    <section id="signed">
                        <h2>نشان والی لائنوں کا مطلب</h2>
                        <p>نوٹوں کی فہرست کے آخر میں چار لائنیں ہیں جن کی رقم مثبت یا منفی ہو سکتی ہے۔ ہر ایک کا مطلب الگ ہے:</p>
                        <table>
                            <tr><th>لائن</th><th>مطلب</th></tr>
                            <tr><td><span class="en">Coins</span></td><td><b>صفر ہو تو یہ چاروں لائنیں چھپ جاتی ہیں؛ غیر صفر (منفی بھی) ہو تو دکھتی ہیں۔</b> سیٹلمنٹ میں گنے ہوئے سکے؛ نوٹوں کی طرح اندر، باہر اور نیٹ میں جاتے ہیں۔</td></tr>
                            <tr><td><span class="en">Group clearing (other salesmen)</span></td><td>جب ایک ہی گروپ میں کئی سیلز مین کے چیک کلیئر ہوں تو نوٹ ایک بار، پورے گروپ کے ساتھ گنے جاتے ہیں۔ اگر رپورٹ میں صرف ایک سیلز مین ہو تو اس کے حصے کی رقم اس لائن میں آتی ہے، کیونکہ نوٹ دوسروں کے ساتھ گنے گئے تھے۔</td></tr>
                            <tr><td><span class="en">Cash not counted</span></td><td>موصول کیش اور گنے ہوئے نوٹوں کا فرق۔ مثبت ہو تو کیش لکھا ہے مگر نوٹ نہیں گنے گئے۔ منفی ہو تو نوٹ کیش سے زیادہ گنے گئے۔ دونوں صورتوں میں فرق یہاں آتا ہے تاکہ نیٹ ٹوٹل ٹھیک رہے۔</td></tr>
                            <tr><td><span class="en">Paid out, no note count</span></td><td>نکلی ہوئی رقم جس کے نوٹ منظور کرنے والے نے نہیں گنے (مثلاً پرانا واؤچر، دکاندار یا تنخواہ کی کیش ادائیگی)۔ یہ رقم باہر کے کالم میں ہے اور نیٹ سے گھٹتی ہے۔</td></tr>
                        </table>
                        <p>رقم کے آگے منفی کا نشان ہو تو وہ واپس آئی ہوئی رقم ہے، مثلاً کسی پرانے دن کا واؤچر آج ریورس ہوا۔ صفر کی جگہ <span class="en">-</span> لکھا جاتا ہے۔</p>
                    </section>

                    <section id="expenses">
                        <h2>اخراجات: کمپنی کے حساب سے، سیلز مین کے حساب سے نہیں</h2>
                        <p>اخراجات واؤچر پر لکھی <b>کمپنی</b> کے ہوتے ہیں، کسی سیلز مین کے نہیں۔ اس لیے <b>سیلز مین یا عہدہ</b> کا فلٹر لگانے سے اخراجات کم نہیں ہوتے؛ وہ پوری کمپنی کے اس دن کے اخراجات ہی رہتے ہیں۔</p>
                        <ul>
                            <li>ایک کمپنی چنیں تو صرف اسی کمپنی کے اخراجات آتے ہیں۔</li>
                            <li><span class="en">All Companies</span> میں وہ عمومی خرچ بھی آتے ہیں جو کسی کمپنی کے نہیں (<span class="en">Moon Traders (general)</span>)۔</li>
                            <li>صرف منظور شدہ واؤچر گنے جاتے ہیں۔ ڈرافٹ، منظوری کے منتظر اور مسترد واؤچر شامل نہیں۔</li>
                            <li>بینک ٹرانسفر یا چیک سے ادا واؤچر کیش خرچ نہیں، اس لیے یہاں نہیں آتے۔</li>
                        </ul>
                    </section>

                    <section id="clearing">
                        <h2>چیک کلیئرنگ</h2>
                        <p>سیلز مین چیک بینک لے جاتا ہے اور کیش لاتا ہے۔ یہ کیش کسی سیٹلمنٹ کا حصہ نہیں، اس لیے <b>چیک رجسٹر</b> میں درج ہوتا ہے۔ کلیئر ہونے کی تاریخ وہی دن ہے جس دن کیش آیا۔ یہ رقم <span class="en">Cheque clearing</span> کالم میں آتی ہے اور موصول کیش میں جڑتی ہے۔</p>
                        <ul>
                            <li>اگر چیک بینک نے سیدھا اپنے اکاؤنٹ میں کلیئر کیا تو کیش واپس نہیں آیا؛ وہ <span class="en">Cleared into bank</span> میں الگ دکھتا ہے۔</li>
                            <li>چیک کو دوبارہ <span class="en">Pending</span> یا <span class="en">Bounced</span> کرنے سے اس کی کلیئرنگ اس رپورٹ سے ہٹ جاتی ہے۔</li>
                        </ul>
                    </section>

                    <section id="perm">
                        <h2>اجازتیں</h2>
                        <table>
                            <tr><th>اجازت</th><th>کام</th></tr>
                            <tr><td><span class="en">report-audit-cash-collection-distributor</span></td><td>رپورٹ کھولنا (صرف اپنی کمپنی)۔</td></tr>
                            <tr><td><span class="en">report-audit-cash-collection-distributor-all-companies</span></td><td>کوئی بھی کمپنی اور <span class="en">All Companies</span> چننا۔</td></tr>
                        </table>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

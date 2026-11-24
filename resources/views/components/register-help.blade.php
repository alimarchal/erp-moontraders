{{-- Urdu help window of the Claim Register ("claims"), the Supplier Ledger Register ("ledger"), Goods Issues, Payroll and the Daily Business Summary, like the Bank
     Register's. Opened by the header Help button (event "rg-help"): it goes to the part that fits what is on screen
     (no supplier chosen: getting started; otherwise reading the register). The small ⓘ buttons on the page pass a
     topic and the window opens on that part, briefly highlighted. Each window ends with the page's flow. --}}
@props(['page', 'hasSupplier' => false])

<x-bank.help-style />

@php
    $isClaims = $page === 'claims';
    $defaultTopic = in_array($page, ['payroll', 'expense-vouchers', 'grn-correction', 'tickets', 'daily-business-summary'], true) ? 'what' : ($hasSupplier ? 'read' : 'start');
    $helpTitle = match ($page) {
        'claims' => 'Claim Register',
        'goods-issues' => 'Goods Issues',
        'tickets' => 'Tickets',
        'grn-correction' => 'GRN Correction',
        'payroll' => 'Payroll',
        'expense-vouchers' => 'Expense Vouchers',
        'cheque-register' => 'Cheque Register',
        'daily-business-summary' => 'Daily Business Summary',
        default => 'Supplier Ledger Register',
    };
    $helpSubtitle = match ($page) {
        'payroll' => 'How salaries work, in Urdu',
        'expense-vouchers' => 'How vouchers work, in Urdu',
        'cheque-register' => 'How cheques are cleared, in Urdu',
        'grn-correction' => 'How a posted GRN is corrected, in Urdu',
        'tickets' => 'How tickets work, in Urdu',
        'daily-business-summary' => 'How this report works, in Urdu',
        default => 'How this register works, in Urdu',
    };
@endphp

<div class="no-print" x-data="{
        open: false,
        go(topic) {
            this.open = true;
            // wait until the window is laid out, then jump (no animation) to the part and mark it
            setTimeout(() => {
                const body = this.$refs.body;
                const target = topic ? body.querySelector('[data-topic=' + topic + ']') : null;
                body.querySelectorAll('.rgh-hit').forEach((el) => el.classList.remove('rgh-hit'));
                const top = target ? target.getBoundingClientRect().top - body.getBoundingClientRect().top + body.scrollTop - 12 : 0;
                body.scrollTo({ top: top, behavior: 'instant' });
                if (target) { target.classList.add('rgh-hit'); }
                this.$refs.close.focus({ preventScroll: true });
            }, 60);
        },
    }"
    x-on:rg-help.window="go($event.detail && $event.detail.topic ? $event.detail.topic : @js($defaultTopic))"
    x-on:keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="rgh-scrim" x-transition.opacity.duration.200ms @click="open = false"></div>
    <div x-show="open" x-cloak class="rgh-wrap" role="dialog" aria-modal="true" aria-labelledby="rgh-title"
        x-transition:enter="duration-300 [transition-timing-function:cubic-bezier(.23,1,.32,1)]" x-transition:enter-start="opacity-0 translate-y-3 scale-[.97]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="rgh-box" @click.outside="open = false">
            <div class="rgh-head">
                <div>
                    <h3 id="rgh-title">Help: {{ $helpTitle }}</h3>
                    <p>{{ $helpSubtitle }}</p>
                </div>
                <button type="button" class="rgh-close" x-ref="close" @click="open = false" aria-label="Close">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="rgh-body" x-ref="body">
                <div class="bh" lang="ur" dir="rtl">
                    <div class="bh-main">
                        @if ($isClaims)
                            <section data-topic="what">
                                <h2>کلیم رجسٹر کیا ہے؟</h2>
                                <p>ہر سپلائر سے لینے والے کلیم (سکیم، ایکسپائری، ٹریڈ آفر، ڈیمج، رینٹل) اور ان کی وصولی کا حساب۔ <b>بیلنس</b> وہ رقم ہے جو ابھی سپلائر سے لینی ہے۔</p>
                            </section>
                            <section data-topic="start">
                                <h2>شروع کیسے کریں</h2>
                                <ol>
                                    <li>اوپر فلٹر کا بٹن دبائیں (یا <span class="en">Choose supplier</span>)۔</li>
                                    <li>سپلائر چنیں؛ تاریخیں خود اس مہینے کی لگی ہوتی ہیں، چاہیں تو بدل دیں۔</li>
                                    <li><span class="en">Apply Filters</span> دبائیں۔ سپلائر چنے بغیر کچھ نہیں دکھتا۔</li>
                                </ol>
                            </section>
                            <section data-topic="read">
                                <h2>رجسٹر کیسے پڑھیں</h2>
                                <table>
                                    <tr><th>کالم</th><th>مطلب</th></tr>
                                    <tr><td><span class="en">DR · Claim</span></td><td>کلیم بنا: سپلائر نے ہمیں یہ رقم دینی ہے۔</td></tr>
                                    <tr><td><span class="en">CR · Recovery</span></td><td>کلیم وصول ہوا (سپلائر کے لیجر میں ایڈجسٹ)۔</td></tr>
                                    <tr><td><span class="en">Closing Balance</span></td><td>اس لائن تک باقی کلیم۔ مثبت = ابھی لینا ہے۔</td></tr>
                                </table>
                                <p>اوپر کے کارڈ: کل کلیم، کل وصولی، اس مدت کا فرق، اور آخری بیلنس۔</p>
                            </section>
                            <section data-topic="columns">
                                <h2>ہر کالم کا مطلب</h2>
                                <div class="rgh-col" data-topic="col-type">
                                    <div class="rgh-col-head"><span class="en">Type</span><b>قسم</b></div>
                                    <p><bdi class="lt" dir="ltr">DR</bdi> = کلیم بنا، <bdi class="lt" dir="ltr">CR</bdi> = کلیم وصول ہوا۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>—</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd>—</dd>
                                    </dl>
                                </div>
                                <div class="rgh-col" data-topic="col-debit">
                                    <div class="rgh-col-head"><span class="en">Debit (Claim)</span><b>کلیم</b></div>
                                    <p>سکیم، ایکسپائری، ٹریڈ آفر یا ڈیمج کا کلیم جو سپلائر نے ہمیں دینا ہے۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>بیلنس بڑھتا ہے (+)</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd><bdi dir="ltr" class="rgh-gl"><span class="en">Dr 1112 Pending Claims</span> / <span class="en">Cr 4230 Claim Register Allowance</span></bdi></dd>
                                    </dl>
                                </div>
                                <div class="rgh-col" data-topic="col-credit">
                                    <div class="rgh-col-head"><span class="en">Credit (Recovery)</span><b>وصولی</b></div>
                                    <p>سپلائر نے کلیم اپنے لیجر میں ایڈجسٹ کر دیا۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>بیلنس گھٹتا ہے (−)</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd>یہاں کوئی جرنل نہیں؛ سپلائر لیجر کا <span class="en">Claim Adjust</span> کتاب میں لے جاتا ہے</dd>
                                    </dl>
                                </div>
                                <div class="rgh-col" data-topic="col-closing">
                                    <div class="rgh-col-head"><span class="en">Closing Balance</span><b>باقی کلیم</b></div>
                                    <p>اس لائن تک سپلائر سے ابھی لینے والا کلیم۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>مثبت = ابھی لینا ہے</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd>کتاب میں <span class="en">1112 Pending Claims</span></dd>
                                    </dl>
                                </div>
                            </section>
                            <section data-topic="entry">
                                <h2>انٹری، ترمیم اور پوسٹ</h2>
                                <ul>
                                    <li><span class="en">Add Entry</span> سے ٹیبل میں نئی لائن کھلتی ہے: تاریخ، قسم (<bdi class="lt" dir="ltr">Claim</bdi> یا <bdi class="lt" dir="ltr">Recovery</bdi>)، رقم، ریفرنس اور کلیم کا مہینہ۔</li>
                                    <li>پوسٹ سے پہلے پنسل سے ترمیم یا ڈبے سے حذف ہو سکتی ہے۔</li>
                                    <li><b><bdi class="lt" dir="ltr">Claim</bdi> پوسٹ:</b> <span class="en">1112 Pending Claims</span> ڈیبٹ، کلیم کی آمدن کریڈٹ۔</li>
                                    <li><b><bdi class="lt" dir="ltr">Recovery</bdi> پوسٹ:</b> کوئی الگ جرنل نہیں بنتا؛ اسے سپلائر لیجر کا کلیم ایڈجسٹ کتاب میں لے جاتا ہے، تاکہ کلیم دو بار نہ کٹے۔</li>
                                    <li>پوسٹ ہونے کے بعد انٹری لاک ہو جاتی ہے۔</li>
                                </ul>
                            </section>
                            <section data-topic="match">
                                <h2>سپلائر لیجر سے ملان</h2>
                                <p>سپلائر چننے پر ایک کارڈ آتا ہے: یہاں کی کل وصولی اور سپلائر لیجر کا کل کلیم ایڈجسٹ۔ دونوں ایک ہی واقعہ ہیں، اس لیے برابر ہونے چاہییں۔</p>
                                <div class="note"><b>فرق آئے تو:</b> کسی ایک رجسٹر میں رقم غلط لکھی گئی ہے۔ دونوں رجسٹر کھول کر وہ انٹری ڈھونڈیں اور درست رقم لکھیں۔ کتاب میں صرف سپلائر لیجر کی رقم جاتی ہے۔</div>
                            </section>
                            <section data-topic="print">
                                <h2>پرنٹ اور <bdi class="lt" dir="ltr">PDF</bdi></h2>
                                <p>پرنٹ کے بٹن سے براؤزر میں <span class="en">Portrait</span> یا <span class="en">Landscape</span> چن سکتے ہیں۔ ڈاؤن لوڈ کے بٹن سے پوری مدت کی <bdi class="lt" dir="ltr">A4 PDF</bdi> بنتی ہے ؛ عام طور پر <span class="en">Portrait</span>۔</p>
                            </section>
                            <section data-topic="flow"><x-help-flow flow="claims" /></section>
                        @elseif ($page === 'payroll')
                            @include('payroll.help._topics')
                        @elseif ($page === 'expense-vouchers')
                            @include('expense-vouchers.partials.help-topics')
                        @elseif ($page === 'cheque-register')
                            @include('reports.cheque-register.partials.help-topics')
                        @elseif ($page === 'tickets')
                            @include('tickets.partials.help-topics')
                        @elseif ($page === 'goods-issues')
                            <section data-topic="what">
                                <h2>گڈز ایشو کیا ہے؟</h2>
                                <p>گودام سے سیلز مین کی گاڑی پر جو مال لادا گیا، اس کا ریکارڈ۔ شام کو اسی ایشو کی <span class="en">Sales Settlement</span> بنتی ہے جس میں بکا، واپس آیا اور پیسہ لکھا جاتا ہے۔</p>
                            </section>
                            <section data-topic="start">
                                <h2>شروع کیسے کریں</h2>
                                <ol>
                                    <li>اوپر فلٹر کا بٹن دبائیں: تلاش (<bdi class="lt" dir="ltr">GI</bdi> نمبر، گاڑی یا سیلز مین)، تاریخیں، سپلائر اور ایک صفحے پر کتنی لائنیں۔</li>
                                    <li><span class="en">More filters</span> سے سیلز مین، گاڑی، گودام اور سیٹلمنٹ کی حالت۔</li>
                                    <li><span class="en">Quick dates</span> سے آج، کل، یہ ہفتہ یا یہ مہینہ ایک کلک میں۔ <span class="en">Apply</span> دبائیں۔</li>
                                </ol>
                            </section>
                            <section data-topic="tabs">
                                <h2>ٹیب</h2>
                                <div class="rgh-col" data-topic="tab-all">
                                    <div class="rgh-col-head"><span class="en">All</span><b>سب</b></div>
                                    <p>چنی ہوئی تاریخوں کے تمام ایشو۔</p>
                                </div>
                                <div class="rgh-col" data-topic="tab-draft">
                                    <div class="rgh-col-head"><span class="en">Draft</span><b>ڈرافٹ</b></div>
                                    <p>ابھی جاری نہیں ہوا: سٹاک نہیں ہلا، کتاب میں کچھ نہیں گیا۔ ترمیم یا حذف ہو سکتا ہے۔</p>
                                </div>
                                <div class="rgh-col" data-topic="tab-issued">
                                    <div class="rgh-col-head"><span class="en">Issued</span><b>جاری</b></div>
                                    <p>مال گاڑی پر چلا گیا: گودام کا سٹاک کم، گاڑی کا سٹاک زیادہ، اور کتاب میں جرنل بن گیا۔</p>
                                </div>
                                <div class="rgh-col" data-topic="tab-pending">
                                    <div class="rgh-col-head"><span class="en">Not settled</span><b>سیٹلمنٹ باقی</b></div>
                                    <p>جاری ہو چکا مگر اس کی سیٹلمنٹ ابھی تصدیق یا پوسٹ نہیں ہوئی۔ ساتھ لکھا ہوتا ہے مال کتنے دن سے گاڑی پر ہے؛ دو دن یا زیادہ ہو تو سرخ۔</p>
                                </div>
                                <div class="rgh-col" data-topic="tab-reversed">
                                    <div class="rgh-col-head"><span class="en">Reversed</span><b>ریورس</b></div>
                                    <p>ایشو واپس لیا گیا: مال گودام میں واپس اور جرنل الٹا۔</p>
                                </div>
                            </section>
                            <section data-topic="columns">
                                <h2>ہر کالم کا مطلب</h2>
                                <div class="rgh-col" data-topic="col-issue">
                                    <div class="rgh-col-head"><span class="en">Issue</span><b>ایشو نمبر</b></div>
                                    <p><bdi class="lt" dir="ltr">GI</bdi> نمبر اور کس نے بنایا۔ ساتھ کا بٹن اس ایشو کی پروڈکٹس کی لائنیں کھولتا ہے۔</p>
                                </div>
                                <div class="rgh-col" data-topic="col-salesman">
                                    <div class="rgh-col-head"><span class="en">Salesman · vehicle</span><b>سیلز مین اور گاڑی</b></div>
                                    <p>جس کو مال دیا، اس کی گاڑی کا نمبر اور گودام۔</p>
                                </div>
                                <div class="rgh-col" data-topic="col-value">
                                    <div class="rgh-col-head"><span class="en">Value (Rs)</span><b>قیمت</b></div>
                                    <p>سب لائنوں کا جوڑ: مقدار × فروخت کی قیمت۔ کتاب میں البتہ مال لاگت (<bdi class="lt" dir="ltr">cost</bdi>) پر جاتا ہے۔</p>
                                </div>
                                <div class="rgh-col" data-topic="col-stage">
                                    <div class="rgh-col-head"><span class="en">Stage</span><b>مرحلہ</b></div>
                                    <p>ایک ہی کیپسول بتاتا ہے ایشو کہاں ہے: <span class="en">Draft</span> (ابھی جاری نہیں)، <span class="en">On van</span> (مال گاڑی پر، سیٹلمنٹ باقی؛ ساتھ دن، دو دن یا زیادہ ہو تو سرخ)، <span class="en">Settlement draft</span> (سیٹلمنٹ بنی مگر تصدیق نہیں)، <span class="en">Settled</span> (سیٹلمنٹ تصدیق یا پوسٹ ہو گئی)، <span class="en">Reversed</span> (واپس)۔ نیچے سیٹلمنٹ کا نمبر؛ کلک سے کھلتی ہے۔</p>
                                </div>
                                <div class="rgh-col" data-topic="col-products">
                                    <div class="rgh-col-head"><span class="en">Products</span><b>پروڈکٹس</b></div>
                                    <p>پہلی دو پروڈکٹس اور مقدار، باقی کی گنتی۔</p>
                                </div>
                                <div class="rgh-col" data-topic="col-qty">
                                    <div class="rgh-col-head"><span class="en">Qty</span><b>مقدار</b></div>
                                    <p>کل جاری شدہ یونٹ۔</p>
                                </div>
                                <div class="rgh-col" data-topic="col-supplier">
                                    <div class="rgh-col-head"><span class="en">Supplier</span><b>سپلائر</b></div>
                                    <p>کس کمپنی کا مال۔</p>
                                </div>
                                <div class="rgh-col" data-topic="col-date">
                                    <div class="rgh-col-head"><span class="en">Date</span><b>تاریخ</b></div>
                                    <p>ایشو کی تاریخ اور کس وقت جاری (پوسٹ) ہوا؛ پورا سال اور دن اوپر لے جانے پر دکھتا ہے۔</p>
                                </div>
                            </section>
                            <section data-topic="books">
                                <h2>کتاب میں کیا جاتا ہے</h2>
                                <p>ایشو جاری ہوتے ہی لاگت پر ایک جرنل: <bdi dir="ltr" class="rgh-gl"><span class="en">Dr 1155 Van Stock</span> / <span class="en">Cr 1151 Stock In Hand</span></bdi>۔ بعد میں لائنیں جوڑیں تو ہر بار ایک الگ "<bdi class="lt" dir="ltr">Supplementary Goods Issue</bdi>" جرنل۔ ریورس ہو تو یہی جرنل الٹا۔</p>
                                <div class="tip">آمدن اور خرچ یہاں نہیں بنتے؛ وہ سیٹلمنٹ پوسٹ ہونے پر بنتے ہیں۔</div>
                            </section>
                            <section data-topic="actions">
                                <h2>بٹن</h2>
                                <ul>
                                    <li><span class="en">View</span>: ایشو کا پورا صفحہ، وہیں سے ریورس یا لائنیں جوڑنا۔</li>
                                    <li><span class="en">Edit</span> اور <span class="en">Delete</span>: صرف ڈرافٹ پر۔</li>
                                    <li><span class="en">Comfortable</span> / <span class="en">Compact</span>: لائنوں کی اونچائی۔</li>
                                </ul>
                            </section>
                            <section data-topic="detail">
                                <h2>ایک ایشو کا صفحہ</h2>
                                <div class="rgh-col" data-topic="gd-stage">
                                    <div class="rgh-col-head"><span class="en">Stage</span><b>مرحلہ</b></div>
                                    <p>اوپر کا کیپسول بتاتا ہے ایشو کہاں ہے: <span class="en">On van</span> یعنی مال گاڑی پر ہے اور سیٹلمنٹ نہیں بنی (دو دن یا زیادہ ہو تو سرخ)؛ <span class="en">Settlement draft</span> یعنی سیٹلمنٹ بنی مگر پوسٹ نہیں؛ <span class="en">Settled</span> یعنی کام مکمل۔ کیپسول پر کلک سے سیٹلمنٹ کھلتی ہے۔</p>
                                </div>
                                <div class="rgh-col" data-topic="gd-value">
                                    <div class="rgh-col-head"><span class="en">Value · Cost · Margin</span><b>رقم</b></div>
                                    <p>بڑی رقم بیچنے کی قیمت پر مال کی مالیت ہے۔ <span class="en">Cost</span> جرنل میں گئی لاگت ہے (ڈرافٹ پر اندازہ)، اور <span class="en">Margin</span> ان دونوں کا فرق۔</p>
                                </div>
                                <div class="rgh-col" data-topic="gd-batches">
                                    <div class="rgh-col-head"><span class="en">Batches × rate</span><b>بیچ</b></div>
                                    <p>ہر پروڈکٹ کس بیچ سے، کتنا اور کس ریٹ پر نکلا۔ پرانا یا جلد ختم ہونے والا مال پہلے نکلتا ہے۔ <span class="en">Promo</span> پروموشنل سٹاک ہے؛ <span class="en">Non-Promo</span> لائن پروموشنل مال نہیں لیتی۔</p>
                                </div>
                                <div class="rgh-col" data-topic="gd-draft">
                                    <div class="rgh-col-head"><span class="en">Draft · Stock Check</span><b>ڈرافٹ</b></div>
                                    <p>ڈرافٹ پر سٹاک چیک دکھتا ہے: سرخ یعنی مال کم ہے اور پوسٹ نہیں ہو گا؛ پیلا یعنی دوسرے ڈرافٹ بھی یہی مال مانگ رہے ہیں، جو پہلے پوسٹ ہو وہ لے جائے گا؛ سبز یعنی سب موجود۔</p>
                                </div>
                                <div class="rgh-col" data-topic="gd-accounting">
                                    <div class="rgh-col-head"><span class="en">Accounting · Timeline</span><b>کتاب</b></div>
                                    <p>نیچے جرنل نمبر اور <bdi dir="ltr" class="lt">Dr Van Stock / Cr Stock in Hand</bdi>، اور ٹائم لائن میں بننے سے سیٹلمنٹ تک ہر قدم۔</p>
                                </div>
                                <div class="rgh-col" data-topic="gd-remove">
                                    <div class="rgh-col-head"><span class="en">Add or remove items</span><b>کم کرنا</b></div>
                                    <p>سیٹلمنٹ سے پہلے کسی لائن کی پوری یا کچھ مقدار واپس گودام میں لی جا سکتی ہے۔ مال اسی گودام کے اسی بیچ میں جاتا ہے (جو بیچ آخر میں نکلا وہ پہلے)، انوینٹری لیجر اور وین سٹاک برابر ہوتے ہیں، اور ایک جرنل <bdi dir="ltr" class="lt">GI-…-R1</bdi> بنتا ہے: <bdi dir="ltr" class="lt">Dr Stock in Hand / Cr Van Stock</bdi>۔ وجہ اور پاس ورڈ ضروری ہیں؛ ایشو کی آخری مقدار کے لیے <span class="en">Reverse and re-issue</span> استعمال کریں۔</p>
                                </div>
                                <div class="rgh-col" data-topic="gd-reversed">
                                    <div class="rgh-col-head"><span class="en">Reverse and re-issue</span><b>ریورس</b></div>
                                    <p>سیٹلمنٹ سے پہلے غلط ایشو ریورس ہو سکتا ہے: مال گودام واپس، جرنل الٹا، اور لائنیں ایک نئے ڈرافٹ میں نقل ہو جاتی ہیں تاکہ درست کر کے دوبارہ پوسٹ کریں۔</p>
                                </div>
                            </section>
                            <section data-topic="print">
                                <h2>پرنٹ، <bdi class="lt" dir="ltr">PDF</bdi> اور <bdi class="lt" dir="ltr">Excel</bdi></h2>
                                <p>اوپر کے بٹن: <span class="en">Excel</span> فلٹر شدہ فہرست؛ ڈاؤن لوڈ سے <bdi class="lt" dir="ltr">A4 PDF</bdi> عام طور پر <span class="en">Portrait</span> یا <span class="en">Landscape</span>؛ پرنٹ کے بٹن سے براؤزر میں دونوں رخ۔</p>
                            </section>
                            <section data-topic="flow"><x-help-flow flow="goods-issues" /></section>
                        @elseif ($page === 'daily-business-summary')
                            <section data-topic="what">
                                <h2>یہ رپورٹ کیا ہے؟</h2>
                                <p>ایک کمپنی (یا سب کمپنیوں) کا ایک دن کا پورا کاروباری خلاصہ ایک صفحے پر: مہینے کی سیل، پیسہ کہاں لگا ہے، کمپنی سے آئی اور مارکیٹ کو دی گئی سکیم کا حساب، اور ہر سیل مین کا اس دن کا کام۔</p>
                                <div class="tip">سب رقمیں صرف <b>پوسٹ</b> ہوئی سیلز سیٹلمنٹ سے آتی ہیں۔ جو سیٹلمنٹ ابھی ڈرافٹ ہے وہ اس رپورٹ میں نہیں گنی جاتی۔</div>
                            </section>
                            <section data-topic="start">
                                <h2>شروع کیسے کریں</h2>
                                <ol>
                                    <li>اوپر فلٹر کا بٹن دبائیں۔ کمپنی چننے سے پہلے فلٹر خود کھلا ہوتا ہے۔</li>
                                    <li><span class="en">Date</span> چنیں: جس دن کی رپورٹ چاہیے۔ <span class="en">Quick dates</span> سے آج، کل یا پچھلے مہینے کا آخری دن ایک کلک میں۔</li>
                                    <li><span class="en">Company</span> چنیں، یا اجازت ہو تو <span class="en">All Companies</span>۔</li>
                                    <li><span class="en">Designation</span> عام طور پر <span class="en">Salesman</span> رہنے دیں؛ <span class="en">Salesmen</span> میں چند نام چن کر صرف ان کو دیکھ سکتے ہیں۔</li>
                                    <li><span class="en">Apply</span> دبائیں۔</li>
                                </ol>
                            </section>
                            <section data-topic="period">
                                <h2>کون سا حصہ کس مدت کا ہے</h2>
                                <table>
                                    <thead><tr><th>حصہ</th><th>مدت</th></tr></thead>
                                    <tbody>
                                        <tr><td><span class="en">Total Sales</span>، <span class="en">Category-wise Sales</span>، <span class="en">Category Group</span>، <span class="en">TTS Summary</span></td><td>مہینے کی پہلی تاریخ سے رپورٹ کی تاریخ تک</td></tr>
                                        <tr><td><span class="en">Today's Cash</span>، <span class="en">Total Discount</span>، سیل مین والا ٹیبل</td><td>صرف رپورٹ کی تاریخ</td></tr>
                                        <tr><td><span class="en">Credit</span> اور <span class="en">Investment Position</span></td><td>رپورٹ کی تاریخ تک کا بقایا (کلوزنگ)</td></tr>
                                    </tbody>
                                </table>
                            </section>
                            <section data-topic="kpis">
                                <h2>اوپر کے چار خانے</h2>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Total Sales</span><b>کل سیل</b></div>
                                    <p>مہینے کے شروع سے آج تک کی کل فروخت۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Today's Cash</span><b>آج کا کیش</b></div>
                                    <p>آج سیل مینوں نے جو نقد گن کر دیا، اس میں بینک سلپ کی رقم جمع۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Total Credit</span><b>کل ادھار</b></div>
                                    <p>تمام سیل مینوں کا مارکیٹ میں بقایا ادھار (کلوزنگ)۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Total Discount</span><b>آج کی رعایت</b></div>
                                    <p>آج دیا گیا <span class="en">%age</span> اور <span class="en">Scheme</span> ملا کر۔</p>
                                </div>
                            </section>
                            <section data-topic="ledger">
                                <h2>لیجر اماؤنٹ</h2>
                                <p>کمپنی (سپلائر) کے لیجر کا بیلنس، رپورٹ کی تاریخ تک: کمپنی کے پاس ہماری کتنی رقم پڑی ہے۔ یہ <span class="en">Supplier Ledger Register</span> کی پوسٹ شدہ لائنوں سے بنتا ہے:</p>
                                <div class="tip">اوپننگ بیلنس + آن لائن جمع − انوائس − اخراجات + <bdi class="lt" dir="ltr">ZA 0.5%</bdi> + کلیم ایڈجسٹ</div>
                                <p>مثبت رقم = کمپنی کے پاس ہمارا پیسہ؛ بریکٹ میں رقم = ہم نے کمپنی کو دینا ہے۔ <span class="en">All Companies</span> میں سب کمپنیوں کا جوڑ۔ یہی رقم انویسٹمنٹ پوزیشن کی <span class="en">Ledger Amount</span> لائن میں بھی ہے۔ <span class="en">Open Supplier Ledger</span> سے اس مہینے کا پورا لیجر کھلتا ہے۔</p>
                            </section>
                            <section data-topic="investment">
                                <h2>انویسٹمنٹ پوزیشن</h2>
                                <p>کمپنی کے کاروبار میں ہمارا پیسہ اس وقت کہاں کہاں لگا ہوا ہے:</p>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Powder / Liquid Expiry</span><b>ایکسپائری</b></div>
                                    <p>اس مہینے سیٹلمنٹ میں مارکیٹ سے واپس آیا ایکسپائر مال (پاؤڈر اور لیکوئڈ الگ) جو ابھی تلف یا نمٹایا نہیں گیا۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Claim Amount</span><b>کلیم</b></div>
                                    <p>کلیم رجسٹر کا بیلنس: کمپنی سے لینے والے کلیم، رپورٹ کی تاریخ تک۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Stock Amount</span><b>سٹاک</b></div>
                                    <p>گودام میں موجود مال کی مالیت (رپورٹ کی تاریخ پر)۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Credit Amount</span><b>ادھار</b></div>
                                    <p>مارکیٹ میں سیل مینوں کا بقایا ادھار۔ یہ نیچے سیل مین ٹیبل کے <span class="en">Credit</span> کا جوڑ ہے۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Ledger Amount</span><b>لیجر</b></div>
                                    <p>سپلائر لیجر رجسٹر کا بیلنس، رپورٹ کی تاریخ تک: کمپنی کے پاس ہماری رقم۔</p>
                                </div>
                                <div class="tip"><span class="en">Total Investment</span> = اوپر کی سب لائنوں کا جوڑ۔</div>
                            </section>
                            <section data-topic="tts">
                                <h2>ٹی ٹی ایس خلاصہ</h2>
                                <p>کمپنی نے سکیم کی جو رقم ہمیں دی (<b>وصول</b>) اور ہم نے مارکیٹ میں دکانداروں کو جو آگے دی (<b>پاس</b>)، اس مہینے میں اب تک:</p>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">TTS Received</span><b>ٹی ٹی ایس وصول</b></div>
                                    <p>کمپنی سے وصول ہونے والی ٹی ٹی ایس (<span class="en">Scheme Received</span> میں درج)۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Promo Received</span><b>پرومو وصول</b></div>
                                    <p>کمپنی سے وصول ہونے والی پروموشن کی رقم۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">TTS Passed</span><b>ٹی ٹی ایس پاس</b></div>
                                    <p>سیٹلمنٹ میں <bdi class="lt" dir="ltr">Scheme Discount (5292)</bdi> کے طور پر مارکیٹ کو دی گئی سکیم۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Promo Passed</span><b>پرومو پاس</b></div>
                                    <p>سیٹلمنٹ میں <bdi class="lt" dir="ltr">Promotion OFF (5288)</bdi> کے طور پر مارکیٹ کو دی گئی پروموشن۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Percentage Passed</span><b>پرسنٹیج پاس</b></div>
                                    <p>سیٹلمنٹ میں <bdi class="lt" dir="ltr">Percentage Expense (5223)</bdi> کے طور پر دی گئی رقم۔</p>
                                </div>
                                <div class="tip"><span class="en">Balance</span> = کل وصول − کل سکیم پاس − پرسنٹیج پاس۔ بریکٹ میں سرخ رقم کا مطلب ہے کہ کمپنی سے جتنا آیا اس سے زیادہ مارکیٹ کو دیا جا چکا ہے۔</div>
                            </section>
                            <section data-topic="categories">
                                <h2>کیٹیگری وار سیل اور گروپ</h2>
                                <p><span class="en">Category-wise Sales</span> میں ہر کیٹیگری کی اس مہینے کی سیل، بڑی سے چھوٹی۔ <span class="en">Category Group Summary</span> انہیں تین گروپ میں جوڑتا ہے:</p>
                                <ul>
                                    <li><span class="en">Nutrition</span>: نیوٹریشن اور بے بی فوڈ۔</li>
                                    <li><span class="en">Water</span>: پانی۔</li>
                                    <li><span class="en">F&amp;B</span>: باقی سب کیٹیگریاں۔</li>
                                </ul>
                                <p>دونوں ٹیبلوں کا کل برابر ہوتا ہے۔</p>
                            </section>
                            <section data-topic="salesmen">
                                <h2>سیل مین ٹیبل کے کالم</h2>
                                <p>رپورٹ کی تاریخ پر ہر سیل مین کی ایک لائن۔ موبائل پر ہر سیل مین ایک کارڈ بن جاتا ہے۔ <span class="en">All Companies</span> میں ساتھ کمپنی کا نام بھی آتا ہے۔</p>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Sales</span><b>سیل</b></div>
                                    <p>اس دن کی پوسٹ شدہ سیٹلمنٹ کی کل فروخت۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">%age</span><b>پرسنٹیج</b></div>
                                    <p>سیل پر کاٹا گیا ایڈوانس ٹیکس (<bdi class="lt" dir="ltr">1161</bdi>)۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Scheme</span><b>سکیم</b></div>
                                    <p>سکیم ڈسکاؤنٹ (<bdi class="lt" dir="ltr">5292</bdi>) اور پروموشن آف (<bdi class="lt" dir="ltr">5288</bdi>) ملا کر۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Total Discount</span><b>کل رعایت</b></div>
                                    <p><span class="en">%age</span> + <span class="en">Scheme</span>۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Expiry Liquid / Powder</span><b>ایکسپائری</b></div>
                                    <p>اس دن مارکیٹ سے واپس لیا ایکسپائر مال (<bdi class="lt" dir="ltr">5262</bdi> لیکوئڈ، <bdi class="lt" dir="ltr">5252</bdi> پاؤڈر)۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Credit</span><b>ادھار</b></div>
                                    <p>اس سیل مین کا مارکیٹ میں بقایا ادھار (کلوزنگ)، صرف اس دن کا نہیں۔</p>
                                </div>
                                <div class="rgh-col">
                                    <div class="rgh-col-head"><span class="en">Amount</span><b>رقم</b></div>
                                    <p>سیل مین نے جو نقد گن کر دیا، اس میں بینک سلپ کی رقم جمع۔</p>
                                </div>
                                <div class="tip">خالی جگہ پر <bdi class="lt" dir="ltr">–</bdi> کا مطلب صفر ہے۔</div>
                            </section>
                            <section data-topic="print">
                                <h2>پرنٹ</h2>
                                <p>پرنٹ میں رنگ نہیں آتے: بلیک اینڈ وائٹ پرنٹر کے لیے سادہ بارڈر والے ٹیبل، اسی ترتیب میں جو سکرین پر ہے۔ براؤزر کے پرنٹ ڈائیلاگ میں <span class="en">Portrait</span> یا <span class="en">Landscape</span> خود چن سکتے ہیں۔</p>
                                <p>اوپر ڈاؤن لوڈ کے بٹن سے <bdi class="lt" dir="ltr">A4 PDF</bdi> بنتی ہے، <span class="en">Portrait</span> یا <span class="en">Landscape</span>۔ سیل مین زیادہ ہوں تو اگلے صفحے پر ٹیبل کی سرخی دوبارہ آتی ہے۔</p>
                            </section>
                        @elseif ($page === 'grn-correction')
                            <section data-topic="what">
                                <h2>جی آر این کریکشن کیا ہے؟</h2>
                                <p>پوسٹ شدہ <span class="en">GRN</span> کی کسی لائن میں <b>کنورژن فیکٹر</b> غلط لگ گیا ہو (مثلاً ایک کارٹن میں 12 کی جگہ 1 لکھا گیا)، تو اسے یہاں درست کیا جاتا ہے، چاہے کچھ مال ایشو یا سیل بھی ہو چکا ہو۔ پرانا ریکارڈ مٹایا نہیں جاتا: فرق کی الگ اینٹری بنتی ہے اور پرانے اور نئے اعداد، وجہ اور آپ کا نام محفوظ ہو جاتے ہیں۔</p>
                            </section>
                            <section data-topic="when">
                                <h2>کب کریکشن، کب ریورس؟</h2>
                                <table>
                                    <tr><th>صورت</th><th>کیا کریں</th></tr>
                                    <tr><td>کوئی مال ایشو نہیں ہوا</td><td><span class="en">Reverse GRN</span> کر کے دوبارہ درست بنائیں، یا کریکشن۔</td></tr>
                                    <tr><td>مال ایشو یا سیل ہو چکا، صرف فیکٹر غلط</td><td><span class="en">GRN Correction</span>۔</td></tr>
                                    <tr><td>غلط پروڈکٹ، بیچ ابھی استعمال نہیں ہوا</td><td>کریکشن میں پروڈکٹ بدل دیں۔</td></tr>
                                    <tr><td>غلط پروڈکٹ، بیچ سے مال نکل چکا</td><td>کریکشن نہیں ہو سکتی؛ سٹاک ایڈجسٹمنٹ یا بیچ ٹرانسفر۔</td></tr>
                                    <tr><td>انوائس کی رقم غلط</td><td>یہ کریکشن رقم نہیں بدلتی؛ <span class="en">GRN</span> ریورس کریں۔</td></tr>
                                </table>
                            </section>
                            <section data-topic="how">
                                <h2>کیسے کریں</h2>
                                <ol>
                                    <li>جس لائن کا فیکٹر غلط ہے اس میں <span class="en">New Factor</span> لکھیں۔ <span class="en">New Qty</span> اور <span class="en">New Unit Cost</span> خود بدل جاتے ہیں۔</li>
                                    <li>ضرورت ہو تو پروڈکٹ بدلیں (صرف جب اس بیچ سے کچھ نہ نکلا ہو)۔</li>
                                    <li>نیچے وجہ لکھیں (کم از کم 10 حروف) اور اپنا پاس ورڈ ڈالیں۔</li>
                                    <li><span class="en">Post Correction</span> دبائیں۔ جن لائنوں میں کچھ نہیں بدلا وہ ویسی ہی رہتی ہیں۔</li>
                                </ol>
                            </section>
                            <section data-topic="effect">
                                <h2>پیچھے کیا ہوتا ہے</h2>
                                <ul>
                                    <li><b>رقم وہی:</b> لائن کی کل لاگت (انوائس) نہیں بدلتی؛ یونٹ لاگت = کل لاگت ÷ نئی مقدار۔ سپلائر کا کھاتہ نہیں بدلتا۔</li>
                                    <li><b>مقدار کا فرق:</b> ایک الگ سٹاک اینٹری <span class="en">GRN</span> کی تاریخ پر بنتی ہے، تاکہ اس دن سے سٹاک درست دکھے۔</li>
                                    <li><b>جو مال نکل چکا:</b> ایشو، سیل اور واپسی نئی لاگت پر آ جاتے ہیں، اور لاگت کا فرق آج کی تاریخ کے ایک جرنل <bdi dir="ltr" class="lt">MT-GRNC-…</bdi> میں جاتا ہے (<span class="en">Stock in Hand</span>، <span class="en">Van Stock</span>، <span class="en">COGS</span>)۔</li>
                                    <li><b>ریکارڈ:</b> <span class="en">GRN</span> کے صفحے پر <span class="en">Corrections</span> میں ہر کریکشن کا نمبر، تاریخ، نام، وجہ اور پرانا/نیا فیکٹر نظر آتا ہے۔</li>
                                </ul>
                            </section>
                            <section data-topic="limits">
                                <h2>کب نہیں ہوتی</h2>
                                <ul>
                                    <li>نئی مقدار اس سے کم ہو جو پہلے ہی نکل چکی ہے۔</li>
                                    <li>بیچ کا کچھ حصہ بیچ ٹرانسفر سے دوسری پروڈکٹ میں جا چکا ہو۔</li>
                                    <li>لائن کی کل لاگت اور مقدار × یونٹ لاگت آپس میں نہ ملتے ہوں۔</li>
                                    <li>صرف سپر ایڈمن کریکشن کر سکتا ہے۔</li>
                                </ul>
                            </section>
                            <section data-topic="flow"><x-help-flow flow="grn-correction" /></section>
                        @else
                            <section data-topic="what">
                                <h2>سپلائر لیجر رجسٹر کیا ہے؟</h2>
                                <p>ہر کمپنی کے ساتھ ہمارا کھاتہ، بالکل اسی ترتیب سے جیسے کمپنی کا لیجر۔ <b>بیلنس مثبت</b> ہو تو رقم ہماری کمپنی پر ہے (ایڈوانس)؛ <b>منفی</b> ہو تو ہم نے کمپنی کو دینی ہے۔ یہی بیلنس کتاب میں <span class="en">2111 Creditors</span> ہے۔</p>
                            </section>
                            <section data-topic="start">
                                <h2>شروع کیسے کریں</h2>
                                <ol>
                                    <li>اوپر فلٹر کا بٹن دبائیں (یا <span class="en">Choose supplier</span>)۔</li>
                                    <li>سپلائر اور تاریخیں چنیں؛ چاہیں تو دستاویز کی قسم بھی (کئی ایک ساتھ)۔</li>
                                    <li><span class="en">Apply Filters</span> دبائیں۔ سپلائر چنے بغیر کچھ نہیں دکھتا۔</li>
                                </ol>
                            </section>
                            <section data-topic="read">
                                <h2>کالم اور کتاب میں اثر</h2>
                                <table>
                                    <tr><th>کالم</th><th>مطلب</th><th>پوسٹ پر کتاب</th></tr>
                                    <tr><td><span class="en">Online</span></td><td>ہم نے بینک سے کمپنی کو بھیجا</td><td><bdi class="lt" dir="ltr">Creditors</bdi> ڈیبٹ / بینک کریڈٹ</td></tr>
                                    <tr><td><span class="en">Invoice</span></td><td>کمپنی کا بل (مال کا)</td><td><bdi class="lt" dir="ltr">GRNI</bdi> ڈیبٹ / <bdi class="lt" dir="ltr">Creditors</bdi> کریڈٹ</td></tr>
                                    <tr><td><span class="en">Expenses</span></td><td>کمپنی نے ہم پر خرچ ڈالا</td><td>خرچ ڈیبٹ / <bdi class="lt" dir="ltr">Creditors</bdi> کریڈٹ</td></tr>
                                    <tr><td><span class="en">ZA 0.5%</span></td><td>کمپنی کی انسینٹو</td><td><bdi class="lt" dir="ltr">Creditors</bdi> ڈیبٹ / آمدن کریڈٹ</td></tr>
                                    <tr><td><span class="en">Claim Adjust</span></td><td>کلیم کمپنی نے لیجر میں ایڈجسٹ کیا</td><td><bdi class="lt" dir="ltr">Creditors</bdi> ڈیبٹ / <bdi class="lt" dir="ltr">Pending Claims</bdi> کریڈٹ</td></tr>
                                </table>
                                <p><span class="en">Balance</span> ہر لائن کے بعد کا بیلنس ہے۔ قسمیں: <span class="en">DZ</span> آن لائن، <span class="en">DR</span> انوائس، <span class="en">DG</span> کلیم، <span class="en">ZA</span> <bdi class="lt" dir="ltr">0.5%</bdi>، <span class="en">OB</span> اوپننگ۔</p>
                            </section>
                            <section data-topic="columns">
                                <h2>ہر کالم کا مطلب</h2>
                                <p>بیلنس کا حساب: اوپننگ + آن لائن − انوائس − خرچ + <span class="en">ZA</span> + کلیم ایڈجسٹ۔ مثبت بیلنس = ہمارا ایڈوانس کمپنی کے پاس؛ منفی = ہم نے کمپنی کو دینا ہے۔</p>
                                <div class="rgh-col" data-topic="col-online">
                                    <div class="rgh-col-head"><span class="en">Online Amount</span><b>آن لائن ادائیگی</b></div>
                                    <p>وہ رقم جو ہم نے بینک سے کمپنی کو بھیجی۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>بیلنس بڑھتا ہے (+)</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd><bdi dir="ltr" class="rgh-gl"><span class="en">Dr 2111 Creditors</span> / <span class="en">Cr 1171 HBL Main</span></bdi></dd>
                                    </dl>
                                </div>
                                <div class="rgh-col" data-topic="col-opening">
                                    <div class="rgh-col-head"><span class="en">Opening Balance</span><b>اوپننگ بیلنس</b></div>
                                    <p>کمپنی کے ساتھ کھاتے کی شروع کی رقم؛ ایک ہی بار لگتی ہے۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>مثبت ہو تو بڑھتا، منفی ہو تو گھٹتا ہے</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd><bdi dir="ltr" class="rgh-gl"><span class="en">Dr 2111</span> / <span class="en">Cr 3300 Opening Balance Equity</span></bdi>؛ منفی ہو تو الٹ</dd>
                                    </dl>
                                </div>
                                <div class="rgh-col" data-topic="col-invoice">
                                    <div class="rgh-col-head"><span class="en">Invoice Amount</span><b>انوائس</b></div>
                                    <p>کمپنی کا مال کا بل۔ <bdi class="lt" dir="ltr">GRN</bdi> پوسٹ ہوتے ہی اس کی ڈرافٹ لائن خود بنتی ہے؛ رقم <bdi class="lt" dir="ltr">GRN</bdi> کی "وصول مگر بل نہیں" والی رقم ہوتی ہے۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>بیلنس گھٹتا ہے (−)</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd><bdi dir="ltr" class="rgh-gl"><span class="en">Dr 2142 Stock Received Not Billed</span> / <span class="en">Cr 2111 Creditors</span></bdi></dd>
                                    </dl>
                                </div>
                                <div class="rgh-col" data-topic="col-expenses">
                                    <div class="rgh-col-head"><span class="en">Expenses</span><b>خرچ</b></div>
                                    <p>کمپنی نے جو خرچ ہمارے کھاتے میں ڈالا۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>بیلنس گھٹتا ہے (−)</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd><bdi dir="ltr" class="rgh-gl"><span class="en">Dr 5210 Administrative Expenses</span> / <span class="en">Cr 2111</span></bdi></dd>
                                    </dl>
                                </div>
                                <div class="rgh-col" data-topic="col-za">
                                    <div class="rgh-col-head"><span class="en">ZA(0.5%)</span><b><bdi class="lt" dir="ltr">ZA 0.5%</bdi> انسینٹو</b></div>
                                    <p>کمپنی کی طرف سے آدھا فیصد انسینٹو، جو ہمارے کھاتے میں جمع ہوتا ہے۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>بیلنس بڑھتا ہے (+)</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd><bdi dir="ltr" class="rgh-gl"><span class="en">Dr 2111</span> / <span class="en">Cr 4240 ZA 0.5% Incentive Income</span></bdi></dd>
                                    </dl>
                                </div>
                                <div class="rgh-col" data-topic="col-claim">
                                    <div class="rgh-col-head"><span class="en">Claim Adjust</span><b>کلیم ایڈجسٹ</b></div>
                                    <p>کمپنی نے ہمارا کلیم اپنے لیجر میں مان کر ایڈجسٹ کیا۔ یہی کلیم کی وصولی ہے؛ کلیم رجسٹر کی <bdi class="lt" dir="ltr">Recovery</bdi> کا الگ جرنل نہیں بنتا۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>بیلنس بڑھتا ہے (+)</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd><bdi dir="ltr" class="rgh-gl"><span class="en">Dr 2111</span> / <span class="en">Cr 1112 Pending Claims</span></bdi>؛ منفی ہو تو الٹ</dd>
                                    </dl>
                                </div>
                                <div class="rgh-col" data-topic="col-balance">
                                    <div class="rgh-col-head"><span class="en">Balance</span><b>بیلنس</b></div>
                                    <p>اس لائن تک کمپنی کے ساتھ کھاتے کا بیلنس۔</p>
                                    <dl>
                                        <dt>بیلنس پر اثر</dt><dd>مثبت = ہمارا کمپنی پر؛ منفی = ہم نے دینا ہے</dd>
                                        <dt>پوسٹ پر کتاب</dt><dd>کتاب میں یہی <span class="en">2111 Creditors</span> کا بیلنس ہے</dd>
                                    </dl>
                                </div>
                            </section>
                            <section data-topic="types">
                                <h2>دستاویز کی قسمیں</h2>
                                <div class="note">قسم صرف پہچان ہے۔ کتاب میں کیا جائے گا، یہ اس سے طے ہوتا ہے کہ رقم کس کالم میں لکھی گئی (اوپر "ہر کالم کا مطلب")۔</div>
                                <div class="rgh-types">
                                    <div class="rgh-type" data-topic="type-MD"><span class="en">MD</span><b><bdi class="lt" dir="ltr">Extra Tax</bdi></b><small>کمپنی کی طرف سے اضافی ٹیکس کی لائن۔</small></div>
                                    <div class="rgh-type" data-topic="type-DR"><span class="en">DR</span><b><bdi class="lt" dir="ltr">Invoices</bdi></b><small>کمپنی کا مال کا بل؛ رقم <bdi class="lt" dir="ltr">Invoice</bdi> کالم میں۔</small></div>
                                    <div class="rgh-type" data-topic="type-DZ"><span class="en">DZ</span><b><bdi class="lt" dir="ltr">Online</bdi></b><small>ہماری بینک سے کمپنی کو ادائیگی؛ رقم <bdi class="lt" dir="ltr">Online</bdi> کالم میں۔</small></div>
                                    <div class="rgh-type" data-topic="type-ZA"><span class="en">ZA</span><b><bdi class="lt" dir="ltr">0.50%</bdi></b><small>آدھا فیصد انسینٹو؛ رقم <bdi class="lt" dir="ltr">ZA(0.5%)</bdi> کالم میں۔</small></div>
                                    <div class="rgh-type" data-topic="type-DG"><span class="en">DG</span><b><bdi class="lt" dir="ltr">Claims</bdi></b><small>کمپنی نے کلیم ایڈجسٹ کیا (کریڈٹ نوٹ)؛ رقم <bdi class="lt" dir="ltr">Claim Adjust</bdi> کالم میں۔</small></div>
                                    <div class="rgh-type" data-topic="type-MC"><span class="en">MC</span><b><bdi class="lt" dir="ltr">Manual Claims</bdi></b><small>ہاتھ سے بنایا گیا کلیم ایڈجسٹ؛ رقم <bdi class="lt" dir="ltr">Claim Adjust</bdi> کالم میں۔</small></div>
                                    <div class="rgh-type" data-topic="type-AB"><span class="en">AB</span><b><bdi class="lt" dir="ltr">Payment from Unidentified Account</bdi></b><small>کسی نامعلوم اکاؤنٹ سے کمپنی کو گئی ادائیگی؛ پہچان ہونے پر درست کریں۔</small></div>
                                    <div class="rgh-type" data-topic="type-MR"><span class="en">MR</span><b><bdi class="lt" dir="ltr">Abnormalities</bdi></b><small>کھاتے میں کوئی غیر معمولی فرق یا درستگی۔</small></div>
                                    <div class="rgh-type" data-topic="type-ZD"><span class="en">ZD</span><b><bdi class="lt" dir="ltr">Cash Discount/Rates Diff</bdi></b><small>کیش ڈسکاؤنٹ یا ریٹ کا فرق۔</small></div>
                                    <div class="rgh-type" data-topic="type-MI"><span class="en">MI</span><b><bdi class="lt" dir="ltr">Miscellaneous</bdi></b><small>متفرق: خرچ یا کلیم ایڈجسٹ جو کسی اور قسم میں نہ آئے۔</small></div>
                                    <div class="rgh-type" data-topic="type-OB"><span class="en">OB</span><b><bdi class="lt" dir="ltr">Opening Balance</bdi></b><small>کھاتے کی شروع کی رقم؛ رقم <bdi class="lt" dir="ltr">Opening Balance</bdi> کالم میں۔</small></div>
                                </div>
                            </section>
                            <section data-topic="entry">
                                <h2>انٹری اور پوسٹ</h2>
                                <ul>
                                    <li><b><bdi class="lt" dir="ltr">GRN</bdi> پوسٹ ہو</b> تو انوائس کی ڈرافٹ لائن خود بن جاتی ہے؛ بل آئے تو رقم جانچ کر پوسٹ کریں۔</li>
                                    <li><span class="en">Add Entry</span> سے آن لائن ادائیگی، خرچ، <bdi class="lt" dir="ltr">ZA</bdi> یا کلیم ایڈجسٹ لکھیں۔</li>
                                    <li>پوسٹ سے پہلے ترمیم یا حذف ہو سکتی ہے؛ پوسٹ کے بعد لائن لاک ہے اور درستی ریورس سے ہوتی ہے۔</li>
                                </ul>
                            </section>
                            <section data-topic="opening">
                                <h2>اوپننگ بیلنس</h2>
                                <p>جس سپلائر کا اوپننگ بیلنس نہیں لگا، وہ <span class="en">Opening balances</span> کارڈ میں گنا جاتا ہے۔ سپلائر، رقم اور تاریخ لکھ کر محفوظ کریں؛ نیچے <span class="en">OB</span> کی ڈرافٹ لائن بنتی ہے جسے پوسٹ کرنا ہے۔</p>
                                <div class="tip">مثبت رقم = سپلائر نے ہمیں دینا ہے؛ منفی = ہم نے سپلائر کو دینا ہے۔ یہ کارڈ صرف سپر ایڈمن، ایڈمن یا اجازت والے صارف کو دکھتا ہے۔</div>
                            </section>
                            <section data-topic="print">
                                <h2>پرنٹ اور <bdi class="lt" dir="ltr">PDF</bdi></h2>
                                <p>پرنٹ کے بٹن سے براؤزر میں <span class="en">Portrait</span> یا <span class="en">Landscape</span> چن سکتے ہیں۔ ڈاؤن لوڈ کے بٹن سے پوری مدت کی <bdi class="lt" dir="ltr">A4 PDF</bdi> بنتی ہے ؛ عام طور پر <span class="en">Portrait</span>۔</p>
                            </section>
                            <section data-topic="flow"><x-help-flow flow="supplier-ledger" /></section>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

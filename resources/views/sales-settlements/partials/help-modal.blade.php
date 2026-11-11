{{-- Help window of the settlement page, in Urdu: what each part of the settlement does in the books, with the
     settlement flow at the end. Opened by the Help button in the header (event "ss-help"). --}}
<x-bank.help-style />

<style>
    .ssh-scrim { position: fixed; inset: 0; z-index: 60; background: rgba(0, 0, 0, .32); -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px); }
    .ssh-wrap { position: fixed; inset: 0; z-index: 61; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .ssh-box { width: 100%; max-width: 920px; max-height: calc(100vh - 32px); display: flex; flex-direction: column; overflow: hidden; border-radius: 18px; background: #f5f5f7; box-shadow: 0 24px 64px rgba(0, 0, 0, .22); }
    .ssh-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 16px 18px 12px 22px; background: rgba(255, 255, 255, .88); border-bottom: 1px solid #e5e5ea; -webkit-backdrop-filter: saturate(180%) blur(16px); backdrop-filter: saturate(180%) blur(16px); }
    .ssh-head h3 { margin: 0; font-size: 19px; font-weight: 600; letter-spacing: -.02em; color: #1d1d1f; }
    .ssh-head p { margin: 2px 0 0; font-size: 13px; color: #6e6e73; }
    .ssh-close { display: inline-grid; place-items: center; width: 30px; height: 30px; border: 0; border-radius: 50%; background: #e8e8ed; color: #1d1d1f; cursor: pointer; }
    .ssh-close:hover { background: #dcdce1; }
    .ssh-close:active { transform: scale(.94); }
    .ssh-body { overflow-y: auto; padding: 16px 18px 20px; }
    .ssh-body .bh-main { gap: 14px; }
    .ssh-body .bh section { padding: 14px 20px; border-radius: 14px; box-shadow: none; }
    .ssh-body .bh h2 { font-size: 20px; }
    @media (max-width: 640px) { .ssh-wrap { padding: 0; align-items: flex-end; } .ssh-box { max-height: 92vh; border-radius: 18px 18px 0 0; } }
</style>

<div x-data="{ open: false }" x-on:ss-help.window="open = true; $nextTick(() => $refs.close.focus())" x-on:keydown.escape.window="open = false" class="no-print">
    <div x-show="open" x-cloak class="ssh-scrim" x-transition.opacity.duration.200ms @click="open = false"></div>
    <div x-show="open" x-cloak class="ssh-wrap" role="dialog" aria-modal="true" aria-labelledby="ssh-title"
        x-transition:enter="duration-300 [transition-timing-function:cubic-bezier(.23,1,.32,1)]" x-transition:enter-start="opacity-0 translate-y-3 scale-[.97]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="ssh-box" @click.outside="open = false">
            <div class="ssh-head">
                <div>
                    <h3 id="ssh-title">Help: Sales Settlement</h3>
                    <p>What this settlement does in the books, in Urdu</p>
                </div>
                <button type="button" class="ssh-close" x-ref="close" @click="open = false" aria-label="Close">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="ssh-body">
                <div class="bh" lang="ur" dir="rtl">
                    <div class="bh-main">
                        <section>
                            <h2>یہ صفحہ کیا ہے؟</h2>
                            <p>ایک سیلز مین کے ایک دن کا حساب: گاڑی پر جو مال نکلا (<span class="en">Goods Issue</span>)، اس میں سے کیا بکا، کیا واپس آیا، اور پیسہ کس شکل میں آیا۔ <span class="en">Draft</span> میں سب بدلا جا سکتا ہے؛ <span class="en">Post Settlement</span> کے بعد ایک جرنل بنتا ہے اور سٹاک اور کھاتے بدل جاتے ہیں۔</p>
                        </section>

                        <section>
                            <h2>پیسہ کہاں جاتا ہے</h2>
                            <table>
                                <tr><th>حصہ</th><th>کتاب میں</th><th>بینک رجسٹر</th></tr>
                                <tr><td>کیش (نوٹ گنے گئے)</td><td><span class="en">1121 Cash</span></td><td>نہیں؛ کیش کلیکشن رپورٹ میں</td></tr>
                                <tr><td>چیک</td><td><span class="en">1122 Cheques in Hand</span></td><td>نہیں؛ چیک رجسٹر میں</td></tr>
                                <tr><td>بینک سلپ (سیلز مین نے جمع کرایا)</td><td>چنا ہوا بینک</td><td>ہاں، خود</td></tr>
                                <tr><td>بینک ٹرانسفر / آن لائن کسٹمر سے</td><td>چنا ہوا بینک</td><td>ہاں، خود</td></tr>
                                <tr><td>ریکوری (آن لائن)</td><td>چنا ہوا بینک، ڈیبٹرز کم</td><td>ہاں، خود</td></tr>
                                <tr><td>ریکوری (کیش)</td><td>کیش، ڈیبٹرز کم</td><td>نہیں</td></tr>
                            </table>
                            <div class="tip">بینک رجسٹر کی یہ لائنیں <span class="en">Booked by settlement</span> کہلاتی ہیں: کتاب میں دوبارہ پوسٹ نہیں ہوتیں، صرف بینک سٹیٹمنٹ سے ملانے کے لیے ہیں۔ سٹیٹمنٹ امپورٹ ہو تو نئی لائن نہیں بنتی، یہی لائن کنفرم ہو جاتی ہے۔</div>
                        </section>

                        <section>
                            <h2>کیش زیادہ یا کم</h2>
                            <ul>
                                <li><b>زیادہ (<span class="en">Excess</span>):</b> پہلے <span class="en">4250 Settlement Excess Income</span> میں جاتا ہے؛ مہینے کی تنخواہ بنے تو سیلز مین کی کمائی (<span class="en">Settlement excess</span>) بن کر اسے ملتا ہے۔</li>
                                <li><b>کم (<span class="en">Shortage</span>):</b> خرچ میں جاتا ہے۔</li>
                                <li><b>ایڈوانس:</b> سیلز مین نے جو ایڈوانس لیا وہ تنخواہ سے کٹتا ہے۔</li>
                            </ul>
                        </section>

                        <section>
                            <h2>چیک بعد میں</h2>
                            <p>عموماً سیلز مین کیش لے آتا ہے اور چیک واپس لے جاتا ہے: <span class="en">Cheque Register</span> میں <span class="en">Cash brought back</span> چن کر نوٹ گنیں؛ رقم <span class="en">1122</span> سے نکل کر کیش میں آتی ہے اور اس دن کی کیش رپورٹ میں دکھتی ہے۔ کبھی چیک بینک میں جمع ہو کر کلیئر ہو تو <span class="en">Deposited in bank</span> چن کر بینک چنیں۔ مہینے کے آخر میں کئی چیک ایک ساتھ <span class="en">Select all</span> سے کلیئر ہوتے ہیں۔</p>
                        </section>

                        <section>
                            <h2>ریورس (<span class="en">Revert</span>)</h2>
                            <p>پوسٹ شدہ سیٹلمنٹ واپس <span class="en">Draft</span> ہو تو اس کا جرنل الٹا ہوتا ہے اور بینک رجسٹر کی لائنیں بھی ریورس ہو جاتی ہیں، تاکہ کتاب برابر رہے۔ یہ نہیں ہو سکتا اگر:</p>
                            <ul>
                                <li>اس کا کوئی چیک کلیئر ہو چکا ہو؛</li>
                                <li>اس کا زیادہ کیش منظور شدہ تنخواہ میں دیا جا چکا ہو؛</li>
                                <li>بینک رجسٹر میں اس کی لائن ری کنسائل ہو چکی ہو (لائن جانچ کے لیے نشان زد ہو جاتی ہے)۔</li>
                            </ul>
                        </section>

                        <section>
                            <x-help-flow flow="settlement" />
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

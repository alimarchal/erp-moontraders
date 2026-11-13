{{-- Settlement help, in Urdu, split by section so each one stays short. A "?" beside a section heading
     (<x-settlement-section-help section="..." />) opens that section's topic (event "ss-help-section");
     the Help button in the header (event "ss-help") opens the list of topics. Include once per page. --}}
@php
    $ssHelpTopics = [
        'items' => ['title' => 'مال کا حساب (Batch-wise)', 'lines' => [
            'گاڑی پر ہر بیچ کا مال: بکا + واپس + کمی + B/F Out = جتنا نکلا تھا۔',
            'واپس آیا مال اسی بیچ میں اسی گودام میں جاتا ہے۔',
            'کمی (Shortage) کی قیمت خرچ بنتی ہے؛ B/F In صرف معلومات کے لیے ہے۔',
            'عام غلطی: Sold بھرنے کے بعد Returned دیکھنا بھول جانا۔',
        ]],
        'credit' => ['title' => 'ادھار فروخت (Credit Sales)', 'lines' => [
            'مال ادھار پر دیا: رقم کسٹمر کے کھاتے (ڈیبٹرز) میں جاتی ہے، کیش نہیں آتا۔',
            'PB پچھلا بیلنس ہے، BAL اس سیلز مین کے ساتھ نیا بیلنس۔',
            'انوائس کی تصویر یا PDF لگائیں تاکہ بعد میں ثبوت رہے۔',
            'ادھار اور ٹرانسفر مل کر بکے ہوئے مال سے زیادہ نہیں ہو سکتے۔',
        ]],
        'recoveries' => ['title' => 'وصولی (Recoveries)', 'lines' => [
            'پرانے ادھار کی وصولی؛ ہر صورت میں کسٹمر کا بیلنس کم ہوتا ہے۔',
            'کیش وصولی 1121 Cash میں، آن لائن وصولی چنے ہوئے بینک میں جاتی ہے۔',
            'آن لائن وصولی کے لیے ٹرانسفر کا ثبوت لگائیں۔',
        ]],
        'cheques' => ['title' => 'چیک (Cheque Payments)', 'lines' => [
            'چیک 1122 Cheques in Hand میں جاتا ہے، بینک رجسٹر میں نہیں۔',
            'کلیئر ہونا یا کیش واپس آنا بعد میں Cheque Register سے ہوتا ہے۔',
            'جس سیٹلمنٹ کا کوئی چیک کلیئر ہو چکا ہو وہ ریورس نہیں ہو سکتی۔',
        ]],
        'bank' => ['title' => 'بینک ٹرانسفر اور بینک سلپ', 'lines' => [
            'ٹرانسفر: کسٹمر نے آن لائن بھیجا۔ سلپ: سیلز مین نے خود بینک میں جمع کرایا۔',
            'دونوں چنے ہوئے بینک میں جاتے ہیں اور بینک رجسٹر میں "Booked by settlement" لائن بنتی ہے۔',
            'صحیح بینک، تاریخ اور ریفرنس لکھیں؛ سٹیٹمنٹ آنے پر یہی لائن کنفرم ہوتی ہے۔',
        ]],
        'cash' => ['title' => 'کیش (نوٹوں کی گنتی)', 'lines' => [
            'ہر نوٹ کی تعداد لکھیں؛ کل کیش 1121 Cash میں جاتا ہے۔',
            'متوقع کیش سے زیادہ ہو تو 4250 Settlement Excess میں، کم ہو تو Shortage خرچ بنتا ہے۔',
            'ریزگاری کی رقم Loose Cash/Coins میں لکھیں۔',
        ]],
        'expenses' => ['title' => 'اخراجات (Expenses)', 'lines' => [
            'سیلز مین کے خرچ (ٹول، کھانا، لوڈر) متوقع کیش سے کم ہوتے ہیں۔',
            'AMR، ایڈوانس ٹیکس اور Percentage Expense نیلے لنک سے اپنے پاپ اپ میں بھریں۔',
            'خالی لائن چھوڑ سکتے ہیں؛ رقم ہو تو اکاؤنٹ ضروری ہے۔',
        ]],
        'summary' => ['title' => 'خلاصہ (Sales Summary)', 'lines' => [
            'یہ حصہ خود حساب کرتا ہے: Net Sale، وصولیاں، متوقع کیش، خرچ اور Short/Excess۔',
            'Short/Excess صفر کے قریب ہونا چاہیے؛ نہ ہو تو اوپر کی اینٹریاں دوبارہ دیکھیں۔',
            'Profit Analysis صرف دیکھنے کے لیے ہے، کھاتے میں نہیں جاتا۔',
        ]],
        'posting' => ['title' => 'Post اور Revert', 'lines' => [
            'Draft میں سب بدلا جا سکتا ہے؛ Post کرنے پر ایک جرنل بنتا ہے اور سٹاک، وین اور کسٹمر کھاتے بدلتے ہیں۔',
            'Revert سے سب الٹا ہو کر سیٹلمنٹ دوبارہ Draft بنتی ہے۔',
            'Revert نہیں ہوتا اگر کوئی چیک کلیئر ہو چکا ہو، زیادہ کیش تنخواہ میں دیا جا چکا ہو یا بینک لائن ری کنسائل ہو چکی ہو۔',
        ]],
    ];
@endphp

<style>
    .ssh-scrim { position: fixed; inset: 0; z-index: 60; background: rgba(0, 0, 0, .32); }
    .ssh-wrap { position: fixed; inset: 0; z-index: 61; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .ssh-box { width: 100%; max-width: 720px; max-height: calc(100vh - 32px); display: flex; flex-direction: column; overflow: hidden; border-radius: 18px; background: #fff; box-shadow: 0 24px 64px rgba(0, 0, 0, .22); font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", system-ui, "Segoe UI", Roboto, sans-serif; }
    .ssh-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 16px 10px 20px; }
    .ssh-head h3 { margin: 0; font-size: 20px; font-weight: 600; letter-spacing: -.02em; color: #1d1d1f; }
    .ssh-close { display: inline-grid; place-items: center; width: 30px; height: 30px; flex: none; border: 0; border-radius: 50%; background: #e8e8ed; color: #1d1d1f; cursor: pointer; transition: transform 160ms cubic-bezier(.23, 1, .32, 1); }
    .ssh-close:active { transform: scale(.94); }
    .ssh-close:focus-visible, .ssh-chip:focus-visible, .ssh-topic:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; }
    .ssh-chips { display: flex; gap: 6px; padding: 0 16px 10px 20px; overflow-x: auto; scrollbar-width: none; }
    .ssh-chips::-webkit-scrollbar { display: none; }
    .ssh-chip { flex: none; padding: 5px 11px; border: 0; border-radius: 980px; background: rgba(118, 118, 128, .12); color: #1d1d1f; font-size: 12.5px; font-weight: 500; cursor: pointer; white-space: nowrap; }
    .ssh-chip[aria-pressed="true"] { background: #1d1d1f; color: #fff; }
    .ssh-body { overflow-y: auto; padding: 6px 24px 26px; border-top: 1px solid rgba(60, 60, 67, .12); }
    .ssh-body h4 { margin: 16px 0 8px; font-size: 19px; font-weight: 600; color: #1d1d1f; }
    .ssh-body ul { margin: 0; padding: 0; list-style: none; }
    .ssh-body li { position: relative; padding: 9px 20px 9px 0; font-size: 16px; line-height: 1.75; color: #3a3a3c; }
    .ssh-body li::before { content: ""; position: absolute; right: 2px; top: 17px; width: 6px; height: 6px; border-radius: 50%; background: #0071e3; }
    .ssh-body li + li { border-top: 1px solid rgba(60, 60, 67, .08); }
    .ssh-topics { display: grid; gap: 8px; margin-top: 14px; }
    .ssh-topic { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 14px; border: 0; border-radius: 12px; background: #f5f5f7; color: #1d1d1f; font-size: 15px; text-align: right; cursor: pointer; transition: background-color 160ms ease; }
    @media (hover: hover) and (pointer: fine) { .ssh-topic:hover { background: #ebebf0; } .ssh-chip:hover { background: rgba(118, 118, 128, .2); } .ssh-chip[aria-pressed="true"]:hover { background: #1d1d1f; } }
    .ssh-topic svg { width: 16px; height: 16px; color: #86868b; transform: scaleX(-1); }
    .ssh-rtl { direction: rtl; font-family: "Noto Nastaliq Urdu", "Jameel Noori Nastaleeq", -apple-system, system-ui, sans-serif; }
    .ssh-chip, .ssh-topic { font-family: inherit; }
    @media (max-width: 640px) {
        .ssh-wrap { padding: 0; align-items: flex-end; }
        .ssh-box { max-width: none; max-height: 88vh; border-radius: 18px 18px 0 0; padding-bottom: env(safe-area-inset-bottom); }
    }
    @media (prefers-reduced-motion: reduce) { .ssh-close { transition: none; } }
    @media print { .ssh-root { display: none !important; } }
</style>

<div class="ssh-root no-print" x-data="{ open: false, topic: 'index', topics: @js(array_map(fn ($t) => $t['title'], $ssHelpTopics)) }"
    x-on:ss-help.window="topic = 'index'; open = true; $nextTick(() => $refs.close.focus())"
    x-on:ss-help-section.window="topic = $event.detail.section in topics ? $event.detail.section : 'index'; open = true; $nextTick(() => $refs.close.focus())"
    x-on:keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="ssh-scrim" @click="open = false"
        x-transition:enter="duration-200 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
    <div x-show="open" x-cloak class="ssh-wrap" role="dialog" aria-modal="true" aria-labelledby="ssh-title" @click.self="open = false"
        x-transition:enter="duration-200 [transition-timing-function:cubic-bezier(.23,1,.32,1)]" x-transition:enter-start="opacity-0 translate-y-2 scale-[.97]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="ssh-box" x-trap.noscroll="open">
            <div class="ssh-head">
                <h3 id="ssh-title" x-text="topic === 'index' ? 'Help: Sales Settlement' : 'Help'">Help: Sales Settlement</h3>
                <button type="button" class="ssh-close" x-ref="close" @click="open = false" aria-label="Close help">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>
            <div class="ssh-chips ssh-rtl" role="group" aria-label="Help topics">
                @foreach ($ssHelpTopics as $key => $topicHelp)
                    <button type="button" class="ssh-chip" :aria-pressed="topic === @js($key) ? 'true' : 'false'" @click="topic = @js($key)">{{ $topicHelp['title'] }}</button>
                @endforeach
            </div>
            <div class="ssh-body ssh-rtl">
                <div x-show="topic === 'index'">
                    <h4>کس حصے کے بارے میں جاننا ہے؟</h4>
                    <div class="ssh-topics">
                        @foreach ($ssHelpTopics as $key => $topicHelp)
                            <button type="button" class="ssh-topic" @click="topic = @js($key)">
                                <span>{{ $topicHelp['title'] }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                            </button>
                        @endforeach
                    </div>
                </div>
                @foreach ($ssHelpTopics as $key => $topicHelp)
                    <section x-show="topic === @js($key)" x-cloak data-help-topic="{{ $key }}">
                        <h4>{{ $topicHelp['title'] }}</h4>
                        <ul>
                            @foreach ($topicHelp['lines'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</div>

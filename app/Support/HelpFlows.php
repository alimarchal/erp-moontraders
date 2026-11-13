<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * The step-by-step flows shown at the end of each Help window (and together in the full Bank Register guide).
 * Each step is [Urdu title, Urdu note, English screen word or null]. Written once here so the guide, the Bank
 * Register windows and the settlement page always tell the same story.
 */
class HelpFlows
{
    /**
     * @return array<string, array{title: string, steps: array<int, array{0: string, 1: string, 2: ?string}>}>
     */
    public static function all(): array
    {
        return [
            'register' => ['title' => 'رجسٹر کا روزانہ فلو', 'steps' => [
                ['رقم آئی', 'سیٹلمنٹ پوسٹ، بینک سٹیٹمنٹ، یا ہاتھ سے انٹری', 'Add entry'],
                ['درجہ بندی', 'کمپنی اور کیٹیگری لگائیں', 'To classify'],
                ['جمع کرائیں', 'میکر بھیجتا ہے؛ حد سے کم رقم فوراً پوسٹ', 'Submit'],
                ['منظوری', 'بڑی رقم چیکر منظور کرتا ہے', 'Approve'],
                ['کتاب میں', 'پہلے سے بُک ہو تو لنک، ورنہ پوسٹ', 'Posted'],
                ['مہینے کا آخر', 'بینک سٹیٹمنٹ سے ملائیں اور لاک کریں', 'Reconciliation'],
            ]],
            'classify' => ['title' => 'درجہ بندی کا فلو', 'steps' => [
                ['نئی لائن', 'سٹیٹمنٹ یا انٹری سے آئی', 'To classify'],
                ['کمپنی', 'کس کمپنی کی رقم ہے', null],
                ['کیٹیگری', 'کس قسم کی رقم ہے، اسی سے کھاتہ بنتا ہے', null],
                ['تیار', 'لائن پوسٹ کے لیے تیار', 'Ready to post'],
            ]],
            'approval' => ['title' => 'میکر چیکر کا فلو', 'steps' => [
                ['میکر', 'رسید لگا کر جمع کراتا ہے', 'Submit'],
                ['حد', 'حد سے کم رقم سیدھی پوسٹ، زیادہ ہو تو منظوری', null],
                ['چیکر', 'منظور یا وجہ لکھ کر رد', 'Approve / Reject'],
                ['پوسٹ', 'جرنل بن جاتا ہے، لائن لاک', 'Posted'],
            ]],
            'posting' => ['title' => 'پوسٹنگ کا فلو', 'steps' => [
                ['چیک', 'کیا یہی رقم ±3 دن میں پہلے سے کتاب میں ہے؟', null],
                ['ہاں', 'دوبارہ پوسٹ نہیں: موجود لائن سے لنک کریں', 'Link'],
                ['نہیں', 'بینک بمقابلہ کیٹیگری کا کھاتہ پوسٹ', 'Post'],
                ['غلطی', 'لنک ہٹائیں یا جرنل ریورس کریں', 'Reverse'],
            ]],
            'reverse' => ['title' => 'ریورس کا فلو', 'steps' => [
                ['پوسٹ شدہ لائن', 'غلط پوسٹ ہوئی', 'Posted'],
                ['ریورس', 'الٹا جرنل بنتا ہے، اصل نہیں مٹتا', 'Reverse'],
                ['درست کریں', 'لائن دوبارہ درجہ بندی کے لیے کھل جاتی ہے', 'Ready to post'],
                ['سیٹلمنٹ کی لائن', 'یہاں ریورس نہیں؛ سیٹلمنٹ ریورس کریں', null],
            ]],
            'recon' => ['title' => 'ری کنسلیشن کا فلو', 'steps' => [
                ['سٹیٹمنٹ', 'مہینے کی سٹیٹمنٹ امپورٹ کریں', 'Import statement'],
                ['نئی ری کنسلیشن', 'تاریخیں اور سٹیٹمنٹ بیلنس لکھیں', 'Reconciliation'],
                ['ملائیں', 'ہر بینک لائن کو کتاب کی لائن سے', 'Match'],
                ['فرق صفر', 'باقی رقم ٹرانزٹ یا آؤٹ سٹینڈنگ', null],
                ['مکمل', 'مدت لاک ہو جاتی ہے', 'Complete'],
            ]],
            'import' => ['title' => 'سٹیٹمنٹ امپورٹ کا فلو', 'steps' => [
                ['فائل', 'بینک کی Excel، CSV یا PDF', 'Import statement'],
                ['پیش نظارہ', 'کالم اور بیلنس کی جانچ', 'Preview'],
                ['محفوظ', 'نئی لائنیں رجسٹر میں', 'Commit'],
                ['سیٹلمنٹ کی رقم', 'نئی لائن نہیں بنتی، سیٹلمنٹ کی لائن کنفرم', 'Booked by settlement'],
            ]],
            'manual' => ['title' => 'ہاتھ سے انٹری کا فلو', 'steps' => [
                ['نئی انٹری', 'بینک، تاریخ، رقم اور پارٹی', 'Add entry'],
                ['رسید', 'فوٹو یا PDF لگائیں', 'Attach'],
                ['کیٹیگری', 'کس کھاتے میں جائے', null],
                ['جمع کرائیں', 'منظوری کے بعد پوسٹ', 'Submit'],
            ]],
            'opening' => ['title' => 'اوپننگ بیلنس کا فلو', 'steps' => [
                ['نئی انٹری', 'جس دن سے رجسٹر شروع ہو', 'Add entry'],
                ['کیٹیگری', 'Opening Balance چنیں', 'Opening Balance'],
                ['رقم', 'بینک میں رقم ہو تو Received، اوور ڈرافٹ ہو تو Paid out', null],
                ['پوسٹ', 'بینک بمقابلہ 3300 Opening Balance Equity', 'Posted'],
                ['ایک بار', 'ہر بینک کا ایک ہی اوپننگ بیلنس', null],
            ]],
            'scans' => ['title' => 'رسید سکین کا فلو', 'steps' => [
                ['فوٹو', 'موبائل سے رسید کی تصویر', 'Receipt scans'],
                ['جوڑیں', 'سکین کو انٹری سے ملائیں', 'Attach'],
                ['مکمل', 'رجسٹر میں No receipt ختم', null],
            ]],
            'setup' => ['title' => 'سیٹ اپ کا فلو', 'steps' => [
                ['کیٹیگری', 'ہر کیٹیگری کا کتاب کا کھاتہ', 'Bank Categories'],
                ['پارٹی کے نام', 'سٹیٹمنٹ کے نام کو کمپنی سے جوڑیں', 'Party Aliases'],
                ['خودکار اصول', 'الفاظ دیکھ کر کیٹیگری خود لگے', 'Auto-classify Rules'],
                ['نتیجہ', 'نئی لائنیں خود درجہ بند', 'To classify'],
            ]],
            'reports' => ['title' => 'رپورٹس کا فلو', 'steps' => [
                ['رپورٹ', 'بینک، تاریخیں اور فلٹر چنیں', 'Reports'],
                ['دیکھیں', 'سکرین پر جانچ', null],
                ['پرنٹ یا ایکسپورٹ', 'PDF یا Excel', 'Print'],
            ]],
            'claims' => ['title' => 'کلیم کا فلو', 'steps' => [
                ['کلیم بنا', 'سکیم، ایکسپائری، ٹریڈ آفر یا ڈیمج کا کلیم', 'Claim Register'],
                ['انٹری', 'Add Entry سے Claim (DR) لکھیں', 'Add Entry'],
                ['پوسٹ', 'Pending Claims بمقابلہ کلیم کی آمدن', 'Post'],
                ['کمپنی نے ایڈجسٹ کیا', 'لیجر میں DG / DZ کلیم ایڈجسٹ', 'Supplier Ledger'],
                ['ریکوری', 'یہاں Recovery (CR) لکھیں؛ کتاب میں نہیں جاتی', 'Recovery'],
                ['ملان', 'دونوں رجسٹر کی رقم برابر ہو', 'Reconciled'],
            ]],
            'supplier-ledger' => ['title' => 'سپلائر لیجر کا فلو', 'steps' => [
                ['GRN', 'مال آیا، انوائس کی ڈرافٹ لائن خود بن گئی', 'GRN'],
                ['انوائس', 'کمپنی کا بل: Invoice (DR)', 'Invoice'],
                ['آن لائن ادائیگی', 'ہم نے بینک سے بھیجا: Online (DZ)', 'Online'],
                ['کٹوتیاں', 'Expenses، ZA 0.5%، کلیم ایڈجسٹ', 'Claim Adjust'],
                ['پوسٹ', 'ہر رقم Creditors کے مقابل کتاب میں', 'Post'],
                ['بیلنس', '+ ہمارا کمپنی پر، − ہم نے دینا ہے', 'Balance'],
            ]],
            'goods-issues' => ['title' => 'گڈز ایشو کا فلو', 'steps' => [
                ['نیا ایشو', 'سیلز مین، گاڑی، گودام اور پروڈکٹس', 'Draft'],
                ['جاری', 'مال گاڑی پر؛ لاگت پر جرنل بنتا ہے', 'Issued'],
                ['اضافی مال', 'ضرورت ہو تو بعد میں لائنیں جوڑیں', 'Supplementary'],
                ['شام', 'اسی ایشو کی سیٹلمنٹ بنائیں', 'Sales Settlement'],
                ['پوسٹ', 'سیٹلمنٹ پوسٹ ہو تو ایشو سیٹل', 'Posted'],
                ['غلطی', 'سیٹلمنٹ سے پہلے ایشو ریورس ہو سکتا ہے', 'Reversed'],
            ]],
            'payroll' => ['title' => 'ماہانہ تنخواہ کا فلو', 'steps' => [
                ['سیٹ اپ', 'ہر ملازم کی بنیادی تنخواہ، الاؤنس اور مستقل کٹوتی (ایک بار)', 'Employee salaries'],
                ['ایڈوانس', 'مہینے میں ایکسپنس واؤچر سے ایڈوانس؛ قسط خود کٹتی ہے', 'Expense Voucher'],
                ['سیٹلمنٹ', 'شارٹ 5293 میں کٹوتی، ایکسس 4250 میں کمائی', 'Sales Settlement'],
                ['نیا پے رول', 'مہینہ چنیں؛ ہر ملازم کی سلپ خود بنتی ہے', 'Draft'],
                ['ویری ایبل', 'گرڈ میں MOP، POS وغیرہ اور غیر حاضری', 'Enter variable pay'],
                ['منظوری کو', 'میکر بھیجتا ہے؛ سب سلپس دوبارہ بنتی ہیں', 'Pending approval'],
                ['منظور', 'دوسرا شخص منظور کرے؛ جرنل پوسٹ', 'Approved'],
                ['ادائیگی', 'کیش یا بینک سے؛ 2112 صاف', 'Paid'],
                ['سیلری شیٹ', 'ہر ملازم کی ایک لائن کی PDF یا Excel', 'Salary sheet'],
            ]],
            'payroll-setup' => ['title' => 'تنخواہ کے سیٹ اپ کا فلو', 'steps' => [
                ['مد بنائیں', 'الاؤنس یا کٹوتی؛ ہر مہینے یا ہر مہینے الگ', 'Categories'],
                ['ملازم چنیں', 'ایک، یا عہدے سے فلٹر کر کے سب', 'Employee salaries'],
                ['تنخواہ لگائیں', 'بائیں آمدن، دائیں کٹوتی؛ مقررہ، % بیسک یا % کسی اور الاؤنس', 'Set salary'],
                ['محفوظ', 'اگلے پے رول سے یہی لگے گا', 'Save'],
            ]],
            'settlement' => ['title' => 'سیل سیٹلمنٹ کا فلو', 'steps' => [
                ['گڈز ایشو', 'گاڑی پر مال نکلا', 'Goods Issue'],
                ['سیٹلمنٹ', 'سیل، واپسی، کیش، چیک، سلپ، آن لائن، ریکوری', 'Draft'],
                ['پوسٹ', 'ایک جرنل: کیش 1121، چیک 1122، بینک کی رقم بینک میں', 'Post Settlement'],
                ['بینک رجسٹر', 'سلپ، ٹرانسفر اور آن لائن ریکوری خود آ جاتی ہیں', 'Booked by settlement'],
                ['چیک کلیئر', 'کیش واپس آئے تو کیش میں، بینک سے کلیئر ہو تو بینک میں', 'Cheque Register'],
                ['زیادہ کیش', '4250 میں، مہینے کی تنخواہ میں سیلز مین کی کمائی', 'Payroll'],
                ['ریورس', 'جرنل اور رجسٹر کی لائنیں واپس؛ کلیئر چیک یا منظور تنخواہ ہو تو نہیں', 'Revert'],
            ]],
        ];
    }

    /**
     * Urdu text with English words, codes and numbers in it, ready for an RTL page: each Latin or numeric run is
     * isolated as left-to-right (<bdi dir="ltr">), so "Invoice (DR)" or "0.5%" never come out reversed or with
     * their brackets flipped. Everything else is escaped.
     */
    public static function bidi(string $text): HtmlString
    {
        $parts = preg_split('/([A-Za-z0-9](?:[A-Za-z0-9 .,%&\/+\-]*[A-Za-z0-9%])?(?:\s?\([A-Za-z0-9 .%\/]+\))?)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';

        foreach ($parts as $index => $part) {
            $html .= $index % 2 === 1 ? '<bdi class="lt" dir="ltr">'.e($part).'</bdi>' : e($part);
        }

        return new HtmlString($html);
    }

    /**
     * @return array{title: string, steps: array<int, array{0: string, 1: string, 2: ?string}>}|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}

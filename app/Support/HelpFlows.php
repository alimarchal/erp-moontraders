<?php

namespace App\Support;

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
     * @return array{title: string, steps: array<int, array{0: string, 1: string, 2: ?string}>}|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}

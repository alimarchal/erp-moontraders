@php
    $urduFont = "'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', 'Noto Naskh Arabic', Tahoma, serif";
    $latinFont = "'Segoe UI', Arial, Helvetica, sans-serif";

    $sectionStyles = [
        'draft_settlements' => [
            'urdu' => 'سیل سیٹلمنٹ ڈرافٹ میں ہے، پوسٹ نہیں ہوئی',
            'english' => 'Sales settlement saved as draft, not posted',
            'note' => 'فروخت درج ہو چکی ہے مگر کھاتوں میں نہیں گئی۔ سیٹلمنٹ کھول کر چیک کریں اور Post کریں۔',
            'number' => 'سیٹلمنٹ',
            'amount' => 'فروخت (روپے)',
            'owner' => 'بنانے والا',
            'link' => 'سیٹلمنٹ کھولیں',
            'accent' => '#b91c1c',
            'tint' => '#fef2f2',
        ],
        'unsettled_issues' => [
            'urdu' => 'گڈز اِشو پوسٹ ہو چکا، مگر سیل سیٹلمنٹ نہیں بنی',
            'english' => 'Goods issued to the van, no settlement yet',
            'note' => 'مال گودام سے گاڑی میں جا چکا ہے مگر اس کی فروخت کا کوئی حساب نہیں بنا۔ سیٹلمنٹ بنا کر Post کریں۔',
            'number' => 'گڈز اِشو',
            'amount' => 'مال کی مالیت (روپے)',
            'owner' => 'جاری کرنے والا',
            'link' => 'گڈز اِشو کھولیں',
            'accent' => '#c2410c',
            'tint' => '#fff7ed',
        ],
        'draft_issues' => [
            'urdu' => 'گڈز اِشو ابھی ڈرافٹ میں ہے',
            'english' => 'Goods issue still in draft (for information)',
            'note' => 'صرف معلومات کے لیے: مال ابھی گودام سے نہیں نکلا۔ اگر گاڑی جا چکی ہے تو گڈز اِشو Post کریں، ورنہ ضرورت نہ ہو تو حذف کر دیں۔',
            'number' => 'گڈز اِشو',
            'amount' => 'مالیت (روپے)',
            'owner' => 'بنانے والا',
            'link' => 'گڈز اِشو کھولیں',
            'accent' => '#475569',
            'tint' => '#f8fafc',
        ],
    ];

    $allRows = collect($sections)->flatten(1);
    $owners = $allRows->groupBy('owner')->map->count()->sortDesc();
    $days = fn (int $count) => match (true) { $count <= 0 => 'آج', $count === 1 => 'کل سے', default => $count.' دن سے' };
@endphp
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .ltr { direction: ltr; unicode-bidi: isolate; font-family: {!! $latinFont !!}; }
        @media (max-width: 620px) {
            .card { padding: 18px 14px !important; }
            .grid td, .grid th { font-size: 12px !important; padding: 6px 4px !important; }
        }
    </style>
</head>
<body dir="rtl" style="margin:0; padding:0; background:#eef2f7; font-family:{!! $urduFont !!}; color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;">
    <tr>
        <td align="center" style="padding:24px 10px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:720px;">

                {{-- Header --}}
                <tr>
                    <td dir="rtl" style="background:#0f2a4a; border-radius:10px 10px 0 0; padding:20px 24px; color:#ffffff; text-align:right;">
                        <div style="font-size:22px; font-weight:700; line-height:2;">نوٹس: سیل سیٹلمنٹ پوسٹ نہیں ہوئی</div>
                        <div class="ltr" dir="ltr" style="font-size:13px; color:#cbd5e1; text-align:right;">
                            {{ config('app.name') }} · Pending settlements notice · {{ now()->format('d M Y, h:i A') }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="card" dir="rtl" style="background:#ffffff; padding:24px; text-align:right; border-radius:0 0 10px 10px;">

                        <p style="font-size:18px; line-height:2.2; margin:0 0 6px;">السلام علیکم {{ preg_replace('/^(Mr|Mrs|Ms|Miss)\.?\s+/i', '', $recipientName) }} صاحب،</p>
                        <p style="font-size:15px; line-height:2.3; margin:0 0 18px; color:#334155;">
                            @if ($isOverview)
                                یہ صبح کی خودکار رپورٹ ہے۔ آج صبح سسٹم نے چیک کیا تو پورے سسٹم میں کل اور اس سے پہلے کے یہ کام ابھی تک نامکمل ہیں۔
                            @else
                                یہ پیغام آپ کی معلومات اور فوری توجہ کے لیے ہے۔ آج صبح سسٹم نے چیک کیا تو کل اور اس سے پہلے کے آپ کے یہ کام ابھی تک نامکمل ہیں۔
                            @endif
                        </p>

                        {{-- Overview: counts per condition and who they belong to --}}
                        @if ($isOverview)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="grid" style="border-collapse:collapse; margin:0 0 8px; font-size:14px;">
                                <tr style="background:#f1f5f9;">
                                    <th style="padding:8px 10px; text-align:right; border:1px solid #e2e8f0; line-height:2;">خلاصہ</th>
                                    <th style="padding:8px 10px; text-align:center; border:1px solid #e2e8f0; line-height:2;">تعداد</th>
                                    <th style="padding:8px 10px; text-align:center; border:1px solid #e2e8f0; line-height:2;">مالیت (روپے)</th>
                                </tr>
                                @foreach ($sectionStyles as $key => $style)
                                    <tr>
                                        <td style="padding:8px 10px; border:1px solid #e2e8f0; line-height:2.1;">
                                            <span style="color:{{ $style['accent'] }}; font-weight:700;">{{ ['draft_settlements' => '①', 'unsettled_issues' => '②', 'draft_issues' => '③'][$key] }}</span>
                                            {{ $style['urdu'] }}
                                        </td>
                                        <td class="ltr" dir="ltr" style="padding:8px 10px; border:1px solid #e2e8f0; text-align:center; font-weight:700;">{{ count($sections[$key]) }}</td>
                                        <td class="ltr" dir="ltr" style="padding:8px 10px; border:1px solid #e2e8f0; text-align:center;">{{ number_format(collect($sections[$key])->sum('amount')) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            <p style="font-size:14px; line-height:2.2; margin:0 0 18px; color:#334155;">
                                <strong>کن یوزرز کے:</strong>
                                @foreach ($owners as $name => $count)
                                    <span dir="ltr" style="display:inline-block; margin:2px 0 2px 6px; padding:0 10px; border-radius:12px; background:#e2e8f0; font-family:{!! $latinFont !!}; font-size:13px; line-height:24px;">{{ $name }} · {{ $count }}</span>
                                @endforeach
                            </p>
                        @endif

                        {{-- One block per condition that has documents --}}
                        @foreach ($sectionStyles as $key => $style)
                            @continue (count($sections[$key]) === 0)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; border-right:4px solid {{ $style['accent'] }}; background:{{ $style['tint'] }}; border-radius:6px;">
                                <tr>
                                    <td style="padding:12px 14px 4px; text-align:right;">
                                        <div style="font-size:17px; font-weight:700; color:{{ $style['accent'] }}; line-height:2.1;">
                                            {{ ['draft_settlements' => '①', 'unsettled_issues' => '②', 'draft_issues' => '③'][$key] }}
                                            {{ $style['urdu'] }}
                                        </div>
                                        <div class="ltr" dir="ltr" style="font-size:12px; color:#64748b; text-align:right;">{{ $style['english'] }}</div>
                                        <p style="font-size:14px; line-height:2.2; margin:6px 0 10px; color:#334155;">{{ $style['note'] }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 10px 12px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" class="grid" style="border-collapse:collapse; background:#ffffff; font-size:13px;">
                                            <tr style="background:#f1f5f9;">
                                                <th style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; line-height:2;">{{ $style['number'] }}</th>
                                                @if ($key === 'draft_settlements')
                                                    <th style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; line-height:2;">گڈز اِشو</th>
                                                @endif
                                                <th style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; line-height:2;">تاریخ</th>
                                                <th style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; line-height:2;">سیلز مین</th>
                                                <th style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; line-height:2;">گاڑی</th>
                                                <th style="padding:7px 6px; border:1px solid #e2e8f0; text-align:left; line-height:2;">{{ $style['amount'] }}</th>
                                                <th style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; line-height:2;">{{ $style['owner'] }}</th>
                                                <th style="padding:7px 6px; border:1px solid #e2e8f0; text-align:center; line-height:2;">کب سے</th>
                                            </tr>
                                            @foreach ($sections[$key] as $row)
                                                <tr>
                                                    <td class="ltr" dir="ltr" style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; white-space:nowrap;">
                                                        <a href="{{ $row['url'] }}" style="color:#1d4ed8; font-weight:700; text-decoration:none;">{{ $row['number'] }}</a>
                                                    </td>
                                                    @if ($key === 'draft_settlements')
                                                        <td class="ltr" dir="ltr" style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; white-space:nowrap;">{{ $row['goods_issue'] }}</td>
                                                    @endif
                                                    <td class="ltr" dir="ltr" style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; white-space:nowrap;">{{ $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') : '—' }}</td>
                                                    <td class="ltr" dir="ltr" style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right;">{{ $row['salesman'] ?? '—' }}</td>
                                                    <td class="ltr" dir="ltr" style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right; white-space:nowrap;">{{ $row['vehicle'] ?? '—' }}</td>
                                                    <td class="ltr" dir="ltr" style="padding:7px 6px; border:1px solid #e2e8f0; text-align:left; white-space:nowrap; font-weight:700;">{{ number_format($row['amount']) }}</td>
                                                    <td class="ltr" dir="ltr" style="padding:7px 6px; border:1px solid #e2e8f0; text-align:right;">{{ $row['owner'] }}</td>
                                                    <td style="padding:7px 6px; border:1px solid #e2e8f0; text-align:center; white-space:nowrap; line-height:2; {{ $row['days'] >= 2 ? 'color:#b91c1c; font-weight:700;' : '' }}">{{ $days($row['days']) }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        @endforeach

                        {{-- Why it matters --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 18px; background:#fffbeb; border:1px solid #fde68a; border-radius:6px;">
                            <tr>
                                <td style="padding:12px 16px; text-align:right;">
                                    <div style="font-size:16px; font-weight:700; color:#92400e; line-height:2.1;">⚠️ اس سے سسٹم غیر متوازن کیوں رہتا ہے؟</div>
                                    <ul style="margin:4px 0 0; padding:0 20px 0 0; font-size:14px; line-height:2.3; color:#334155;">
                                        <li>گڈز اِشو پوسٹ ہوتے ہی مال گودام سے گاڑی <span class="ltr" dir="ltr">(Van Stock)</span> میں منتقل ہو جاتا ہے۔</li>
                                        <li>جب تک سیٹلمنٹ پوسٹ نہ ہو، فروخت، کیش، چیک، کسٹمر کا اُدھار، ریٹرن اور شارٹیج کھاتوں میں درج نہیں ہوتے۔</li>
                                        <li>نتیجہ یہ کہ وین سٹاک میں مال دکھتا رہتا ہے، سیل اور منافع کی رپورٹیں کم دکھتی ہیں، کیش اور بینک کا حساب نہیں ملتا، اور گاڑی اگلے گڈز اِشو کے لیے لاک رہتی ہے۔</li>
                                    </ul>
                                </td>
                            </tr>
                        </table>

                        {{-- What to do --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px;">
                            <tr>
                                <td style="padding:12px 16px; text-align:right;">
                                    <div style="font-size:16px; font-weight:700; color:#166534; line-height:2.1;">✅ براہِ کرم آج ہی:</div>
                                    <ol style="margin:4px 0 0; padding:0 22px 0 0; font-size:14px; line-height:2.3; color:#334155;">
                                        <li>سیٹلمنٹ کھول کر ہر لائن، سپلیمنٹری سمیت، کا <span class="ltr" dir="ltr">Sold / Return / Shortage</span> چیک کریں۔</li>
                                        <li>کیش، چیک اور اخراجات ملائیں۔</li>
                                        <li><span class="ltr" dir="ltr"><strong>Post</strong></span> کا بٹن دبا دیں۔</li>
                                    </ol>
                                </td>
                            </tr>
                        </table>

                        <p style="font-size:15px; line-height:2.2; margin:0;">شکریہ،<br><span class="ltr" dir="ltr" style="font-weight:700;">{{ config('app.name') }} ERP</span></p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:14px 10px; text-align:center; font-size:12px; color:#64748b; line-height:2;">
                        یہ ایک خودکار پیغام ہے، جواب نہ دیں۔<br>
                        <span class="ltr" dir="ltr">Automated notice sent every morning at 7:00 for documents dated before today. Please do not reply.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

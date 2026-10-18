<?php

namespace App\Helpers;

/**
 * Small server-side SVG charts, so a report shows the same graph on screen and in the PDF.
 *
 * Only plain shapes and text are used (rect, path, line, text), which DomPDF can draw from an
 * <img> data URI and a browser can draw inline. Money is written the Pakistani way (Cr / Lac).
 */
class SvgChart
{
    /**
     * Colours used across the reports, in the order series and slices take them.
     *
     * @var array<int, string>
     */
    public const PALETTE = ['#2563eb', '#f97316', '#16a34a', '#7c3aed', '#0891b2', '#e11d48', '#f59e0b', '#64748b'];

    private const FONT = 'Lato, Helvetica, Arial, sans-serif';

    private const INK = '#0f172a';

    private const MUTED = '#64748b';

    private const GRID = '#e2e8f0';

    public static function money(float $value): string
    {
        $abs = abs($value);
        $sign = $value < 0 ? '-' : '';

        return $sign.match (true) {
            $abs >= 10000000 => rtrim(rtrim(number_format($abs / 10000000, 2), '0'), '.').' Cr',
            $abs >= 100000 => rtrim(rtrim(number_format($abs / 100000, 1), '0'), '.').' Lac',
            $abs >= 1000 => number_format($abs / 1000, 0).'k',
            default => number_format($abs, 0),
        };
    }

    /**
     * Grouped columns: one group per label, one bar per series.
     *
     * @param  array<int, string>  $labels
     * @param  array<int, array{name: string, color: string, values: array<int, float>}>  $series
     */
    public static function columns(array $labels, array $series, int $width = 520, int $height = 240, bool $legend = true): string
    {
        $top = $legend && count($series) > 1 ? 30 : 16;
        $left = 46;
        $bottom = 34;
        $plotW = $width - $left - 8;
        $plotH = $height - $top - $bottom;
        $max = max(1.0, ...array_merge(...array_map(fn ($s) => array_map('floatval', $s['values']), $series)));
        [$max, $step] = self::niceScale($max);

        $svg = '';
        for ($i = 0; $i <= 4; $i++) {
            $y = $top + $plotH - $plotH * $i / 4;
            $svg .= self::line($left, $y, $left + $plotW, $y, $i === 0 ? '#94a3b8' : self::GRID);
            $svg .= self::text($left - 6, $y + 3, self::money($step * $i), 9, self::MUTED, 'end');
        }

        $groups = max(1, count($labels));
        $groupW = $plotW / $groups;
        $barW = min(34, ($groupW * 0.7) / max(1, count($series)));
        foreach ($labels as $g => $label) {
            $groupLeft = $left + $groupW * $g + ($groupW - $barW * count($series)) / 2;
            foreach ($series as $s => $serie) {
                $value = (float) ($serie['values'][$g] ?? 0);
                $barH = $max > 0 ? $plotH * $value / $max : 0;
                $x = $groupLeft + $barW * $s;
                if ($barH > 0) {
                    $svg .= self::rect($x + 1, $top + $plotH - $barH, $barW - 2, $barH, $serie['color'], 3);
                    if ($barW >= 20 || count($series) === 1) {
                        $svg .= self::text($x + $barW / 2, $top + $plotH - $barH - 4, self::money($value), 9, self::INK, 'middle', true);
                    }
                }
            }
            $svg .= self::text($left + $groupW * ($g + 0.5), $top + $plotH + 15, self::clip((string) $label, (int) ($groupW / 5.2)), 9.5, '#334155', 'middle');
        }

        if ($legend && count($series) > 1) {
            $x = $left;
            foreach ($series as $serie) {
                $svg .= self::rect($x, 6, 10, 10, $serie['color'], 2);
                $svg .= self::text($x + 15, 15, $serie['name'], 10, '#334155');
                $x += 28 + strlen($serie['name']) * 5.6;
            }
        }

        return self::wrap($svg, $width, $height);
    }

    /**
     * Columns where every bar has its own colour (one value per label).
     *
     * @param  array<int, string>  $labels
     * @param  array<int, float>  $values
     * @param  array<int, string>  $colors
     */
    public static function colorColumns(array $labels, array $values, array $colors = self::PALETTE, int $width = 520, int $height = 240): string
    {
        $top = 20;
        $left = 46;
        $bottom = 34;
        $plotW = $width - $left - 8;
        $plotH = $height - $top - $bottom;
        [$max, $step] = self::niceScale(max(1.0, ...array_map('floatval', $values ?: [1])));

        $svg = '';
        for ($i = 0; $i <= 4; $i++) {
            $y = $top + $plotH - $plotH * $i / 4;
            $svg .= self::line($left, $y, $left + $plotW, $y, $i === 0 ? '#94a3b8' : self::GRID);
            $svg .= self::text($left - 6, $y + 3, self::money($step * $i), 9, self::MUTED, 'end');
        }

        $slot = $plotW / max(1, count($labels));
        $barW = min(46, $slot * 0.62);
        foreach ($labels as $i => $label) {
            $value = (float) ($values[$i] ?? 0);
            $barH = $max > 0 ? $plotH * $value / $max : 0;
            $x = $left + $slot * $i + ($slot - $barW) / 2;
            if ($barH > 0) {
                $svg .= self::rect($x, $top + $plotH - $barH, $barW, $barH, $colors[$i % count($colors)], 4);
                $svg .= self::text($x + $barW / 2, $top + $plotH - $barH - 4, self::money($value), 9.5, self::INK, 'middle', true);
            }
            $svg .= self::text($x + $barW / 2, $top + $plotH + 15, self::clip((string) $label, (int) ($slot / 5.4)), 9.5, '#334155', 'middle');
        }

        return self::wrap($svg, $width, $height);
    }

    /**
     * Horizontal bars with the label on the left and the value at the end of each bar.
     *
     * @param  array<int, string>  $labels
     * @param  array<int, float>  $values
     * @param  array<int, string>  $colors  one colour per bar, repeated when shorter
     * @param  (callable(float): string)|null  $format
     */
    public static function bars(array $labels, array $values, array $colors = self::PALETTE, int $width = 520, ?int $height = null, ?callable $format = null, int $labelChars = 22): string
    {
        $rows = max(1, count($labels));
        $height ??= max(80, $rows * 26 + 12);
        $rowH = min(26, ($height - 12) / $rows);
        $left = 8 + $labelChars * 5.3;
        $right = 64;
        $plotW = $width - $left - $right;
        $max = max(1.0, ...array_map('floatval', $values ?: [1]));
        $format ??= fn (float $v) => self::money($v);

        $svg = '';
        foreach ($labels as $i => $label) {
            $y = 6 + $i * $rowH;
            $value = (float) ($values[$i] ?? 0);
            $w = max(0, $plotW * $value / $max);
            $color = $colors[$i % count($colors)];
            $svg .= self::text($left - 8, $y + 14, self::clip((string) $label, $labelChars), 10, '#1e293b', 'end');
            $svg .= self::rect($left, $y + 3, $plotW, 15, '#f1f5f9', 7.5);
            if ($w > 0) {
                $svg .= self::rect($left, $y + 3, max($w, 8), 15, $color, 7.5);
            }
            $svg .= self::text($left + max($w, 8) + 6, $y + 14.5, $format($value), 10, self::INK, 'start', true);
        }

        return self::wrap($svg, $width, $height);
    }

    /**
     * Percent of target per row: green at 100% and over, amber from 70%, red below. A line marks 100%.
     *
     * @param  array<int, string>  $labels
     * @param  array<int, float>  $percentages
     */
    public static function achievement(array $labels, array $percentages, int $width = 520, ?int $height = null, int $labelChars = 22): string
    {
        $rows = max(1, count($labels));
        $height ??= max(90, $rows * 26 + 28);
        $rowH = min(26, ($height - 28) / $rows);
        $left = 8 + $labelChars * 5.3;
        $right = 46;
        $plotW = $width - $left - $right;
        $scaleMax = max(100.0, ...array_map('floatval', $percentages ?: [100]));
        $hundredX = $left + $plotW * 100 / $scaleMax;

        $svg = '';
        foreach ($labels as $i => $label) {
            $y = 18 + $i * $rowH;
            $pct = (float) ($percentages[$i] ?? 0);
            $w = max(0, $plotW * $pct / $scaleMax);
            $color = $pct >= 100 ? '#16a34a' : ($pct >= 70 ? '#f59e0b' : '#e11d48');
            $svg .= self::text($left - 8, $y + 14, self::clip((string) $label, $labelChars), 10, '#1e293b', 'end');
            $svg .= self::rect($left, $y + 3, $plotW, 15, '#f1f5f9', 7.5);
            if ($w > 0) {
                $svg .= self::rect($left, $y + 3, max($w, 8), 15, $color, 7.5);
            }
            $svg .= self::text($left + max($w, 8) + 6, $y + 14.5, number_format($pct, 0).'%', 10, self::INK, 'start', true);
        }
        $svg .= self::line($hundredX, 12, $hundredX, $height - 6, '#0b2a6f', 1.2, '3,3');
        $svg .= self::text($hundredX, 9, 'Target', 9, '#0b2a6f', 'middle', true);

        return self::wrap($svg, $width, $height);
    }

    /**
     * Donut with a legend on the right and a headline figure in the hole.
     *
     * @param  array<int, string>  $labels
     * @param  array<int, float>  $values
     * @param  array<int, string>  $colors
     */
    public static function donut(array $labels, array $values, array $colors = self::PALETTE, int $width = 360, int $height = 210, string $centerValue = '', string $centerLabel = ''): string
    {
        $total = array_sum(array_map('floatval', $values));
        $r = min($height / 2 - 8, 86);
        $cx = $r + 12;
        $cy = $height / 2;
        $inner = $r * 0.6;

        $svg = '';
        if ($total <= 0) {
            $svg .= self::text($width / 2, $cy, 'No data', 12, self::MUTED, 'middle');

            return self::wrap($svg, $width, $height);
        }

        $angle = -M_PI / 2;
        foreach ($values as $i => $value) {
            $value = (float) $value;
            if ($value <= 0) {
                continue;
            }
            $color = $colors[$i % count($colors)];
            $sweep = $value / $total * 2 * M_PI;
            if ($sweep >= 2 * M_PI - 0.001) {
                $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="'.$color.'"/>';
                $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$inner.'" fill="#ffffff"/>';
                break;
            }
            $end = $angle + $sweep;
            $large = $sweep > M_PI ? 1 : 0;
            $svg .= sprintf(
                '<path d="M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 0 %.2f %.2f Z" fill="%s" stroke="#ffffff" stroke-width="1.5"/>',
                $cx + $r * cos($angle), $cy + $r * sin($angle), $r, $r, $large, $cx + $r * cos($end), $cy + $r * sin($end),
                $cx + $inner * cos($end), $cy + $inner * sin($end), $inner, $inner, $large, $cx + $inner * cos($angle), $cy + $inner * sin($angle),
                $color
            );
            if ($value / $total >= 0.06) {
                $mid = $angle + $sweep / 2;
                $lx = $cx + ($r + $inner) / 2 * cos($mid);
                $ly = $cy + ($r + $inner) / 2 * sin($mid);
                $svg .= self::text($lx, $ly + 3, number_format($value / $total * 100, 0).'%', 9.5, '#ffffff', 'middle', true);
            }
            $angle = $end;
        }

        if ($centerValue !== '') {
            $svg .= self::text($cx, $cy + ($centerLabel !== '' ? 1 : 5), $centerValue, 13, self::INK, 'middle', true);
            if ($centerLabel !== '') {
                $svg .= self::text($cx, $cy + 14, $centerLabel, 8.5, self::MUTED, 'middle');
            }
        }

        $legendX = $cx + $r + 22;
        $legendY = max(14, $cy - count($labels) * 15);
        foreach ($labels as $i => $label) {
            $y = $legendY + $i * 30;
            $svg .= self::rect($legendX, $y, 10, 10, $colors[$i % count($colors)], 2);
            $svg .= self::text($legendX + 16, $y + 9, self::clip((string) $label, 20), 10, '#1e293b', 'start', true);
            $svg .= self::text($legendX + 16, $y + 21, self::money((float) ($values[$i] ?? 0)).'  ·  '.number_format(((float) ($values[$i] ?? 0)) / $total * 100, 1).'%', 9, self::MUTED);
        }

        return self::wrap($svg, $width, $height);
    }

    /**
     * An <img> for the PDF: DomPDF draws SVG from a data URI at the given width in points.
     */
    public static function img(string $svg, int $widthPt): string
    {
        return '<img src="data:image/svg+xml;base64,'.base64_encode($svg).'" style="width:'.$widthPt.'pt" alt="">';
    }

    /**
     * @return array{0: float, 1: float} the top of the scale and the size of one of its four steps
     */
    private static function niceScale(float $max): array
    {
        $rough = $max / 4;
        $magnitude = 10 ** floor(log10(max($rough, 1)));
        $step = 10 * $magnitude;
        foreach ([1, 2, 2.5, 5, 10] as $multiple) {
            if ($rough <= $multiple * $magnitude) {
                $step = $multiple * $magnitude;
                break;
            }
        }

        return [$step * 4, (float) $step];
    }

    private static function wrap(string $inner, int $width, int $height): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" font-family="'.self::FONT.'" style="width:100%;height:auto;display:block">'.$inner.'</svg>';
    }

    private static function rect(float $x, float $y, float $w, float $h, string $fill, float $radius = 0): string
    {
        return sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.1f" fill="%s"/>', $x, $y, $w, $h, $radius, $fill);
    }

    private static function line(float $x1, float $y1, float $x2, float $y2, string $stroke, float $width = 1, ?string $dash = null): string
    {
        return sprintf('<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="%s" stroke-width="%s"%s/>', $x1, $y1, $x2, $y2, $stroke, $width, $dash ? ' stroke-dasharray="'.$dash.'"' : '');
    }

    private static function text(float $x, float $y, string $text, float $size, string $fill, string $anchor = 'start', bool $bold = false): string
    {
        return sprintf(
            '<text x="%.2f" y="%.2f" font-size="%s" fill="%s" text-anchor="%s"%s>%s</text>',
            $x, $y, $size, $fill, $anchor, $bold ? ' font-weight="bold"' : '', htmlspecialchars($text, ENT_QUOTES | ENT_XML1)
        );
    }

    private static function clip(string $text, int $chars): string
    {
        return mb_strlen($text) > $chars ? rtrim(mb_substr($text, 0, max(1, $chars - 1))).'…' : $text;
    }
}

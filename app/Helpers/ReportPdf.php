<?php

namespace App\Helpers;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

/**
 * A4 report PDFs with the shared look: half inch margin on every side, Lato, a banner, coloured
 * figure tiles, charts and a table. Portrait or landscape, with "Page x of y" in the margin.
 */
class ReportPdf
{
    /** Half an inch, in points. */
    public const MARGIN_PT = 36;

    /**
     * Width of the printable area in points (A4 minus the margins).
     */
    public static function contentWidth(string $orientation): int
    {
        return ($orientation === 'landscape' ? 842 : 595) - 2 * self::MARGIN_PT;
    }

    public static function orientation(?string $requested): string
    {
        return $requested === 'landscape' ? 'landscape' : 'portrait';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(string $view, array $data, string $orientation, string $footerLabel): DomPdfWrapper
    {
        $fontDir = storage_path('app/dompdf-fonts');
        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0775, true);
        }

        $pdf = Pdf::setOption(['fontDir' => $fontDir, 'fontCache' => $fontDir, 'defaultFont' => 'Lato', 'dpi' => 96])
            ->loadView($view, $data + ['orientation' => $orientation, 'contentWidth' => self::contentWidth($orientation)])
            ->setPaper('a4', $orientation);

        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Lato') ?: $dompdf->getFontMetrics()->getFont('Helvetica');
        $width = $canvas->get_width();
        $baseline = $canvas->get_height() - self::MARGIN_PT + 14;

        $canvas->page_text(self::MARGIN_PT, $baseline, $footerLabel, $font, 7.5, [0.39, 0.45, 0.55]);
        $canvas->page_text($width - self::MARGIN_PT - 52, $baseline, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.39, 0.45, 0.55]);

        return $pdf;
    }
}

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Photo optimisation for every attachment upload
    |--------------------------------------------------------------------------
    |
    | Phone photos of receipts and slips are often 3 to 8 MB. They are shrunk before they are stored (the long side is
    | limited and the picture is saved again at a normal quality), which usually cuts the size by 70 to 90 percent and
    | keeps the disk or S3 bucket small and downloads quick. PDFs and spreadsheets are never touched. If anything goes
    | wrong the original file is kept. The database only ever holds the file's path, name, type and size.
    |
    */

    'enabled' => (bool) env('ATTACHMENT_IMAGE_OPTIMIZE', true),

    // Longest side in pixels. 1600 keeps a receipt readable at full screen at a fraction of the size.
    'max_px' => (int) env('ATTACHMENT_IMAGE_MAX_PX', 1600),

    // JPEG / WEBP quality, 1 to 100.
    'quality' => (int) env('ATTACHMENT_IMAGE_QUALITY', 75),

    // Longest side of the small copy shown in lists and previews.
    'thumb_px' => (int) env('ATTACHMENT_IMAGE_THUMB_PX', 320),

    // Pictures already smaller than this (KB) and within max_px are left as they are.
    'min_kb' => (int) env('ATTACHMENT_IMAGE_MIN_KB', 300),
];

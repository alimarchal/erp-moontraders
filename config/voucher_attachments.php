<?php

return [
    /*
    | Optional attachment (PDF or image) on an expense voucher, kept under
    | Vouchers/{dd-mm-yyyy of the voucher}/. When disabled, the upload field and the
    | paperclip are hidden; stored files are left untouched.
    */
    'enabled' => (bool) env('VOUCHER_ATTACHMENTS_ENABLED', true),

    // Any disk from config/filesystems.php: 'local' (private) or 's3'.
    'disk' => env('VOUCHER_ATTACHMENTS_DISK', 'local'),

    'max_kb' => (int) env('VOUCHER_ATTACHMENTS_MAX_KB', 5120),

    'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],

    'root_folder' => 'Vouchers',
];

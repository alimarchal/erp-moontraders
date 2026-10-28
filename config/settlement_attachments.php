<?php

return [
    /*
    | Optional attachments (PDF/images) on sales settlement credit sales, cheques,
    | bank transfers, bank slips and bank recoveries. When disabled, upload fields
    | and paperclip icons are hidden; stored files are left untouched.
    */
    'enabled' => (bool) env('SETTLEMENT_ATTACHMENTS_ENABLED', true),

    // Any disk from config/filesystems.php: 'local' (private) or 's3'.
    'disk' => env('SETTLEMENT_ATTACHMENTS_DISK', 'local'),

    'max_kb' => (int) env('SETTLEMENT_ATTACHMENTS_MAX_KB', 5120),

    'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],

    'root_folder' => 'sales-settlement',
];

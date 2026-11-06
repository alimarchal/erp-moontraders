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

    /*
    | Make the attachment compulsory for a section: a settlement cannot be posted while any row of that
    | section has no file. Off by default (attachments stay optional). Has no effect while
    | SETTLEMENT_ATTACHMENTS_ENABLED is false. Cash recoveries need none; only bank recoveries do.
    */
    'required' => [
        'credit_sales' => (bool) env('SS_CREDIT_SALES_DETAIL_ATTACHMENT', false),
        'recoveries' => (bool) env('SS_RECOVERIES_DETAIL_ATTACHMENT', false),
        'cheques' => (bool) env('SS_CHEQUE_PAYMENTS_ATTACHMENT', false),
        'bank_transfers' => (bool) env('SS_BANK_TRANSFERS_ATTACHMENT', false),
        'bank_slips' => (bool) env('SS_BANK_SLIPS_DEPOSITS_ATTACHMENT', false),
    ],
];

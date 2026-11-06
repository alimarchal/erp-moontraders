<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maker-checker (Bank Statement Register)
    |--------------------------------------------------------------------------
    |
    | A classified bank entry is submitted by a maker. Entries below the level-1
    | threshold are posted straight away; from level 1 a checker must approve;
    | from level 2 a senior approver must approve as well. All values come from
    | .env so the limits can change without a code change.
    |
    */

    'maker_checker' => [
        'enabled' => (bool) env('BANK_MAKER_CHECKER_ENABLED', true),
        'level1_threshold' => (float) env('BANK_APPROVAL_LEVEL1_THRESHOLD', 1000000),
        'level2_threshold' => (float) env('BANK_APPROVAL_LEVEL2_THRESHOLD', 10000000),
        'allow_self_approval' => (bool) env('BANK_ALLOW_SELF_APPROVAL', false),
        'applies_to' => env('BANK_APPROVAL_APPLIES_TO', 'both'), // debit | credit | both
        'reversal_requires_approval' => (bool) env('BANK_APPROVAL_REQUIRED_FOR_REVERSAL', true),
        'bulk_approval_max_entries' => (int) env('BANK_BULK_APPROVAL_MAX_ENTRIES', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk' => env('BANK_ATTACHMENT_DISK', 'local'),
        'max_kb' => (int) env('BANK_ATTACHMENT_MAX_KB', 10240),
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'csv'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt numbers
    |--------------------------------------------------------------------------
    |
    | BR-2024-25-000001 for money received, BP-2024-25-000001 for money paid.
    | The fiscal year runs July to June.
    |
    */

    'receipts' => [
        'receipt_prefix' => 'BR',
        'payment_prefix' => 'BP',
        'fiscal_year_start_month' => 7,
    ],

];

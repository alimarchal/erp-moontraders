<?php

namespace App\Support;

/**
 * The upload and form limits PHP applies to a single request. A save that goes past one of them is cut short by PHP
 * itself (fields silently dropped, "field is required" on a form that was filled in), so the forms read them to warn
 * the user before sending.
 */
class PhpLimits
{
    /** Bytes in one request body (post_max_size); 0 means no limit. */
    public static function postMaxBytes(): int
    {
        return self::bytes((string) ini_get('post_max_size'));
    }

    /** Bytes in one uploaded file (upload_max_filesize); 0 means no limit. */
    public static function uploadMaxBytes(): int
    {
        return self::bytes((string) ini_get('upload_max_filesize'));
    }

    /** Files PHP accepts in one request (max_file_uploads). */
    public static function maxFileUploads(): int
    {
        return max(1, (int) ini_get('max_file_uploads'));
    }

    /** Form fields PHP accepts in one request (max_input_vars); extra fields are dropped without any error. */
    public static function maxInputVars(): int
    {
        return max(1, (int) ini_get('max_input_vars'));
    }

    /**
     * Turn PHP's shorthand ("8M", "512K", "1G") into bytes.
     */
    public static function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}

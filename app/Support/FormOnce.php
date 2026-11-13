<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A one-time token for forms that change the books (add or remove goods issue items): the page renders a fresh
 * token in a hidden `_once` field, and the server accepts each token once, so a double click, a browser resubmit
 * or back-and-refresh cannot run the same change twice.
 */
class FormOnce
{
    public const FIELD = '_once';

    private const TTL_HOURS = 12;

    public static function token(): string
    {
        return (string) Str::uuid();
    }

    /**
     * True the first time a token is seen, false for every repeat. A request without a token (an older page or an
     * API client) is let through, so the guard never blocks a legitimate first submission.
     */
    public static function claim(?string $token): bool
    {
        if ($token === null || $token === '') {
            return true;
        }

        return Cache::add('form-once:'.sha1($token), true, now()->addHours(self::TTL_HOURS));
    }

    public static function alreadySentMessage(string $record = 'issue'): string
    {
        return "This form has already been sent, so nothing was changed again. Check the {$record} before trying once more.";
    }
}

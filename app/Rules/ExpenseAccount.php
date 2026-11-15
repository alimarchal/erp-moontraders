<?php

namespace App\Rules;

use App\Models\ChartOfAccount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An expense voucher may only be booked to an active, postable (non-group) expense account: a 52xx code.
 */
class ExpenseAccount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::allows($value)) {
            $fail('Choose an active expense account (code 52...).');
        }
    }

    public static function allows(mixed $accountId): bool
    {
        if (! is_numeric($accountId)) {
            return false;
        }

        return ChartOfAccount::query()
            ->whereKey((int) $accountId)
            ->where('is_active', true)
            ->where('is_group', false)
            ->where('account_code', 'like', '52%')
            ->exists();
    }
}

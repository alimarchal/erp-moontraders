<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Running document numbers (MT-VP-2026-000001) from a per type and year counter row. The row is locked for the
 * length of the surrounding transaction, so two documents never get the same number.
 */
class DocumentNumberService
{
    public function next(string $type, string $label): string
    {
        $year = (int) now()->format('Y');

        DB::table('document_sequences')->insertOrIgnore(['type' => $type, 'year' => $year, 'last_number' => 0]);

        $last = (int) DB::table('document_sequences')->where('type', $type)->where('year', $year)->lockForUpdate()->value('last_number');
        $next = $last + 1;

        DB::table('document_sequences')->where('type', $type)->where('year', $year)->update(['last_number' => $next]);

        $code = Company::query()->orderBy('id')->value('abbr') ?: 'MT';

        return strtoupper($code).'-'.$label.'-'.$year.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}

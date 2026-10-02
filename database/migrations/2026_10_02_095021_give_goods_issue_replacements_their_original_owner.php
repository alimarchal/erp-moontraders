<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replacement drafts used to be owned by whoever reversed the issue, which hid them from
     * the original owner when that user may only see their own issues. Who reversed it stays
     * on the reversed issue's reversed_by.
     */
    public function up(): void
    {
        DB::table('goods_issues as replacement')
            ->join('goods_issues as reversed', 'reversed.id', '=', 'replacement.replaces_goods_issue_id')
            ->whereColumn('replacement.issued_by', '!=', 'reversed.issued_by')
            ->select('replacement.id', 'reversed.issued_by')
            ->orderBy('replacement.id')
            ->get()
            ->each(fn (object $row) => DB::table('goods_issues')->where('id', $row->id)->update(['issued_by' => $row->issued_by]));
    }

    /**
     * The previous owners are not recorded, so there is nothing to put back.
     */
    public function down(): void
    {
        //
    }
};

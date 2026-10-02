<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replacement drafts used to be owned by whoever reversed the issue, which hid them from
     * the original owner when that user may only see their own issues. Who reversed it stays
     * on the reversed issue's reversed_by.
     *
     * A replacement always has a higher id than the issue it replaces, so walking them in id
     * order fixes each parent before its own replacement copies the owner, and a chain of
     * reversals ends with the first issue's owner.
     */
    public function up(): void
    {
        DB::table('goods_issues')
            ->whereNotNull('replaces_goods_issue_id')
            ->orderBy('id')
            ->get(['id', 'issued_by', 'replaces_goods_issue_id'])
            ->each(function (object $replacement): void {
                $owner = DB::table('goods_issues')->where('id', $replacement->replaces_goods_issue_id)->value('issued_by');

                if ($owner !== null && (int) $owner !== (int) $replacement->issued_by) {
                    DB::table('goods_issues')->where('id', $replacement->id)->update(['issued_by' => $owner]);
                }
            });
    }

    /**
     * The previous owners are not recorded, so there is nothing to put back.
     */
    public function down(): void
    {
        //
    }
};

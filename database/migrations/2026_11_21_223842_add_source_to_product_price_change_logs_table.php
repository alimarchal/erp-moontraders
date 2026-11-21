<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a price change came from (price ticket, stock adjustment, product edit, Excel import) and
     * the ticket or adjustment number, written when the change is logged. Rows logged before this are left null.
     */
    public function up(): void
    {
        Schema::table('product_price_change_logs', function (Blueprint $table) {
            $table->string('source', 30)->nullable()->after('impacted_batch_count')->index();
            $table->string('source_reference', 50)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('product_price_change_logs', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'source_reference']);
        });
    }
};

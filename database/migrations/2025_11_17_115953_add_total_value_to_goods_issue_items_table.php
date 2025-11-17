<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('goods_issue_items', function (Blueprint $table) {
            $table->decimal('total_value', 15, 2)->default(0)->after('unit_cost');
            $table->integer('line_no')->default(0)->after('goods_issue_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_issue_items', function (Blueprint $table) {
            $table->dropColumn(['total_value', 'line_no']);
        });
    }
};

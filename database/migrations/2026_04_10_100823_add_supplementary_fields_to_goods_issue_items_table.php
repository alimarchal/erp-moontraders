<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('goods_issue_items', function (Blueprint $table) {
            $table->boolean('is_supplementary')->default(false)->after('exclude_promotional');
            $table->timestamp('supplementary_posted_at')->nullable()->after('is_supplementary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_issue_items', function (Blueprint $table) {
            $table->dropColumn(['is_supplementary', 'supplementary_posted_at']);
        });
    }
};

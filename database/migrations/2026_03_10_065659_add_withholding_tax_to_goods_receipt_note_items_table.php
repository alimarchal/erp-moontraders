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
        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->decimal('withholding_tax', 15, 2)->default(0)->after('other_charges');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->dropColumn('withholding_tax');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('qty_entry_mode', ['pieces', 'carton'])->default('pieces')->after('is_advance_tax_income');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('pieces_per_carton')->nullable()->after('uom_conversion_factor')->comment('Pieces (cans) in one carton for carton-mode suppliers');
            $table->unsignedInteger('units_per_piece')->nullable()->after('pieces_per_carton')->comment('Pouches per can, used to derive TH quantity');
        });

        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->decimal('entered_cartons', 15, 3)->nullable()->after('qty_in_stock_uom');
            $table->unsignedInteger('entered_loose_pieces')->nullable()->after('entered_cartons');
            $table->decimal('rate_per_th', 15, 4)->nullable()->after('entered_loose_pieces');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->dropColumn(['entered_cartons', 'entered_loose_pieces', 'rate_per_th']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pieces_per_carton', 'units_per_piece']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('qty_entry_mode');
        });
    }
};

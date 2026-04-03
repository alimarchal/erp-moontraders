<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Increase decimal precision on stock-related tables to reduce float drift.
     *
     * unit_cost / average_cost  → decimal(15,6)  — per-unit rates that get divided
     * total_value / stock_value → decimal(15,4)  — calculated amounts
     */
    public function up(): void
    {
        // current_stock_by_batch
        Schema::table('current_stock_by_batch', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 6)->change();
            $table->decimal('total_value', 15, 4)->change();
        });

        // stock_valuation_layers
        Schema::table('stock_valuation_layers', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 6)->change();
            $table->decimal('total_value', 15, 4)->change();
            $table->decimal('value_remaining', 15, 4)->default(0)->change();
        });

        // current_stock
        Schema::table('current_stock', function (Blueprint $table) {
            $table->decimal('average_cost', 15, 6)->change();
            $table->decimal('total_value', 15, 4)->change();
        });

        // stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 6)->change();
            $table->decimal('total_value', 15, 4)->change();
        });

        // stock_ledger_entries
        Schema::table('stock_ledger_entries', function (Blueprint $table) {
            $table->decimal('valuation_rate', 15, 6)->change();
            $table->decimal('stock_value', 15, 4)->change();
        });

        // goods_receipt_note_items — unit_cost & total_cost are source of truth
        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 6)->change();
            $table->decimal('total_cost', 15, 4)->change();
        });

        // stock_adjustment_items
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 6)->change();
            $table->decimal('adjustment_value', 15, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('current_stock_by_batch', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->change();
            $table->decimal('total_value', 15, 2)->change();
        });

        Schema::table('stock_valuation_layers', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->change();
            $table->decimal('total_value', 15, 2)->change();
            $table->decimal('value_remaining', 15, 2)->default(0)->change();
        });

        Schema::table('current_stock', function (Blueprint $table) {
            $table->decimal('average_cost', 15, 2)->change();
            $table->decimal('total_value', 15, 2)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->change();
            $table->decimal('total_value', 15, 2)->change();
        });

        Schema::table('stock_ledger_entries', function (Blueprint $table) {
            $table->decimal('valuation_rate', 15, 2)->change();
            $table->decimal('stock_value', 15, 2)->change();
        });

        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->change();
            $table->decimal('total_cost', 15, 2)->change();
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->change();
            $table->decimal('adjustment_value', 15, 2)->change();
        });
    }
};

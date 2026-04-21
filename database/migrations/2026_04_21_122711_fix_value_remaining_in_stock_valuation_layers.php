<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix historical drift: value_remaining was never updated when stock was issued.
        // Recalculate from quantity_remaining * unit_cost which is always accurate.
        DB::statement('UPDATE stock_valuation_layers SET value_remaining = ROUND(quantity_remaining * unit_cost, 4)');
    }

    public function down(): void
    {
        // Cannot restore previous drifted values — no-op.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-number existing records with date-based unique format,
     * then add unique constraints to prevent future duplicates.
     */
    public function up(): void
    {
        $this->renumberExistingRecords();

        Schema::table('sales_settlement_credit_sales', function (Blueprint $table) {
            $table->unique('invoice_number');
        });

        Schema::table('sales_settlement_recoveries', function (Blueprint $table) {
            $table->unique('recovery_number');
        });

        Schema::table('sales_settlement_advance_taxes', function (Blueprint $table) {
            $table->unique('invoice_number');
        });

        Schema::table('sales_settlement_percentage_expenses', function (Blueprint $table) {
            $table->unique('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('sales_settlement_credit_sales', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
        });

        Schema::table('sales_settlement_recoveries', function (Blueprint $table) {
            $table->dropUnique(['recovery_number']);
        });

        Schema::table('sales_settlement_advance_taxes', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
        });

        Schema::table('sales_settlement_percentage_expenses', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
        });
    }

    private function renumberExistingRecords(): void
    {
        $this->renumberTable(
            'sales_settlement_credit_sales',
            'invoice_number',
            'CSI'
        );

        $this->renumberTable(
            'sales_settlement_recoveries',
            'recovery_number',
            'REC'
        );

        $this->renumberTable(
            'sales_settlement_advance_taxes',
            'invoice_number',
            'ATI'
        );

        $this->renumberTable(
            'sales_settlement_percentage_expenses',
            'invoice_number',
            'PEI'
        );
    }

    /**
     * @param  array<string, int>  $counters
     */
    private function renumberTable(string $table, string $column, string $prefix): void
    {
        $records = DB::table($table)
            ->join('sales_settlements', "{$table}.sales_settlement_id", '=', 'sales_settlements.id')
            ->select("{$table}.id", 'sales_settlements.settlement_date')
            ->orderBy("{$table}.id")
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        $counters = [];

        foreach ($records as $record) {
            $dateCode = date('ymd', strtotime($record->settlement_date));
            $key = "{$prefix}-{$dateCode}-";

            $counters[$key] = ($counters[$key] ?? 0) + 1;

            DB::table($table)
                ->where('id', $record->id)
                ->update([$column => sprintf('%s%05d', $key, $counters[$key])]);
        }
    }
};

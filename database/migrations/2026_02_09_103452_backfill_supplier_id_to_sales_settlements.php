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
        $settlements = \App\Models\SalesSettlement::whereNull('supplier_id')->with('employee')->get();
        foreach ($settlements as $settlement) {
            if ($settlement->employee && $settlement->employee->supplier_id) {
                $settlement->supplier_id = $settlement->employee->supplier_id;
                $settlement->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlements', function (Blueprint $table) {
            //
        });
    }
};

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
        Schema::table('customer_credit_sales', function (Blueprint $table) {
            $table->decimal('recovery_amount', 15, 2)->default(0)->after('sale_amount');
            $table->decimal('previous_balance', 15, 2)->default(0)->after('recovery_amount');
            $table->decimal('new_balance', 15, 2)->default(0)->after('previous_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_credit_sales', function (Blueprint $table) {
            $table->dropColumn(['recovery_amount', 'previous_balance', 'new_balance']);
        });
    }
};

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
            $table->string('status')->default('draft')->after('notes')
                ->comment('Status: draft, posted, cancelled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_credit_sales', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

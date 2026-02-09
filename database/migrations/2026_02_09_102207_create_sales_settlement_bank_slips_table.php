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
        Schema::create('sales_settlement_bank_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('bank_account_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->string('attachment')->nullable();
            // supplier_id removed as per latest instruction to only have it on main table
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_bank_slips');
    }
};

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
        Schema::create('sales_settlement_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained('sales_settlements')->cascadeOnDelete();
            $table->date('expense_date');
            $table->enum('expense_type', ['fuel', 'toll', 'parking', 'meal', 'repair', 'other'])->default('other');
            $table->decimal('amount', 15, 2);
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->string('receipt_number')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->timestamps();

            $table->index('sales_settlement_id');
            $table->index('expense_type');
            $table->index('expense_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_expenses');
    }
};

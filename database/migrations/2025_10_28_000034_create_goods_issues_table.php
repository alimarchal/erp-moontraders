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
        Schema::create('goods_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_number')->unique();
            $table->date('issue_date');
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('restrict');
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            // GL account references for transfer
            $table->foreignId('stock_in_hand_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('van_stock_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->enum('status', ['draft', 'issued', 'settled', 'cancelled'])->default('draft');
            $table->decimal('total_quantity', 15, 3)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['issue_date', 'status']);
            $table->index('employee_id');
            $table->index('vehicle_id');
            $table->index('stock_in_hand_account_id');
            $table->index('van_stock_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_issues');
    }
};

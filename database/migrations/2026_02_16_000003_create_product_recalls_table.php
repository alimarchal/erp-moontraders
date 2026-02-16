<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_recalls', function (Blueprint $table) {
            $table->id();
            $table->string('recall_number')->unique();
            $table->date('recall_date');
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('grn_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();
            $table->enum('recall_type', ['supplier_initiated', 'quality_issue', 'expiry', 'other'])->default('supplier_initiated');
            $table->enum('status', ['draft', 'posted', 'completed', 'cancelled'])->default('draft');
            $table->decimal('total_quantity_recalled', 15, 3)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->text('reason');
            $table->timestamp('supplier_notification_sent_at')->nullable();
            $table->foreignId('claim_register_id')->nullable()->constrained('claim_registers')->nullOnDelete();
            $table->foreignId('stock_adjustment_id')->nullable()->constrained('stock_adjustments')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'recall_date']);
            $table->index('status');
            $table->index('recall_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recalls');
    }
};

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
        Schema::create('sales_settlement_amr_powders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained('sales_settlements')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->string('batch_code', 100)->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_disposed')->default(false);
            $table->timestamp('disposed_at')->nullable();
            $table->timestamps();

            $table->index('sales_settlement_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_amr_powders');
    }
};

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
        //     Schema::create('batches', function (Blueprint $table) {
        //         $table->id();
        //         $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
        //         $table->string('batch_no');
        //         $table->date('manufacturing_date')->nullable();
        //         $table->date('expiry_date')->nullable();
        //         $table->text('notes')->nullable();
        //         $table->timestamps();

        //         // Unique per product for batch number (short name for MySQL)
        //         $table->unique(['product_id', 'batch_no'], 'batch_prod_no_unq');
        //         $table->index(['expiry_date'], 'batch_exp_idx');
        //     });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};

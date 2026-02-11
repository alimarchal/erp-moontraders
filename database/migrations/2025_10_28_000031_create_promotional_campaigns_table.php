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
        Schema::create('promotional_campaigns', function (Blueprint $table) {
            $table->id();

            // Campaign Identity
            $table->string('campaign_code', 50)->unique()->comment('e.g., EID-2025, RAMADAN-2025');
            $table->string('campaign_name');
            $table->text('description')->nullable();

            // Duration
            $table->date('start_date');
            $table->date('end_date');

            // Discount Configuration
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'special_price', 'buy_x_get_y'])->default('percentage');
            $table->decimal('discount_value', 15, 2)->default(0)->comment('Percentage or amount based on discount_type');
            $table->decimal('buy_quantity', 10, 2)->nullable()->comment('Buy this quantity (e.g., 11 for "11+1")');
            $table->decimal('get_quantity', 10, 2)->nullable()->comment('Get this quantity free (e.g., 1 for "11+1")');

            // Conditions
            $table->decimal('minimum_quantity', 10, 2)->default(0)->comment('Minimum qty to qualify for promotion');
            $table->decimal('maximum_discount_amount', 15, 2)->nullable()->comment('Cap on discount amount');

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_auto_apply')->default(false)->comment('Auto-apply discount when conditions met');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('start_date');
            $table->index('end_date');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotional_campaigns');
    }
};

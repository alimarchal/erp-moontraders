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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique()->index();
            $table->string('customer_name')->index();
            $table->string('business_name')->nullable();

            // Contact information
            $table->string('phone')->nullable();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('sub_locality')->nullable()->comment('Sub area/locality within city');
            $table->string('city')->nullable()->index();
            $table->string('state')->nullable()->default('Azad Kashmir');
            $table->string('country')->default('Pakistan');

            // Customer classification
            $table->enum('channel_type', [
                'General Store',
                'Wholesale',
                'Pharmacy',
                'Bakery',
                'Minimart',
                'Hotel & Accommodation',
                'Petromart',
                '3rd Party',
                'Other',
            ])->default('General Store')->index()->comment('Business channel/category');
            $table->enum('customer_category', ['A', 'B', 'C', 'D'])->default('C')->comment('Customer tier/priority');

            // Credit management
            $table->decimal('credit_limit', 15, 2)->default(50000.00);
            $table->integer('payment_terms')->default(30)->comment('Payment terms in days');
            $table->decimal('credit_used', 15, 2)->default(0)->comment('Current credit utilized');

            // Balance tracking - REMOVED: Now calculated dynamically from customer_employee_account_transactions
            // receivable_balance, payable_balance, lifetime_value - calculated on demand

            // Double-entry COA links
            $table->foreignId('receivable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->comment('AR account (1201-XXX)');
            $table->foreignId('payable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->comment('AP account (2101-XXX)');

            // Additional fields
            $table->text('notes')->nullable();
            $table->date('last_sale_date')->nullable();
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete()->comment('Assigned salesperson');

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_code', 'is_active']);
            $table->index(['channel_type', 'is_active']);
            $table->index(['city', 'sub_locality']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

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
        Schema::create('customer_employee_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->unique()->index()->comment('Unique account identifier (e.g., ACC-000001)');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('opened_date')->comment('Date when account was first created');
            $table->enum('status', ['active', 'closed', 'suspended'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: One account per (customer + employee) pair
            $table->unique(['customer_id', 'employee_id'], 'unique_customer_employee_account');

            // Indexes for performance
            $table->index(['employee_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_employee_accounts');
    }
};

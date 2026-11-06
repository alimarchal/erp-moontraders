<?php

use App\Support\MigrationHelpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'expense-vendor-payment-list', 'expense-vendor-payment-create', 'expense-vendor-payment-approve',
        'expense-vendor-payment-view-all', 'expense-vendor-payment-view-company',
        'employee-advance-list',
    ];

    /**
     * Month-end payments of credit vouchers, and the advance each employee has taken.
     */
    public function up(): void
    {
        // One running counter per document type and year (payments, payroll runs).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['type', 'year']);
        });

        Schema::create('expense_vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('payment_number', 30)->unique();
            $table->date('payment_date')->index();
            $table->foreignId('expense_vendor_id')->constrained('expense_vendors')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('method', 10)->default('cash');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reference', 120)->nullable();
            $table->text('narration')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_vendor_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_vendor_payment_id')->constrained('expense_vendor_payments', 'id', 'evpa_payment_fk')->cascadeOnDelete();
            $table->foreignId('expense_voucher_id')->constrained('expense_vouchers', 'id', 'evpa_voucher_fk')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('expense_voucher_id')->nullable()->unique()->constrained('expense_vouchers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->date('advance_date')->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('installment_amount', 15, 2)->nullable();
            $table->decimal('recovered_amount', 15, 2)->default(0);
            $table->string('status', 12)->default('open')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        MigrationHelpers::grantPermissions(self::PERMISSIONS);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advances');
        Schema::dropIfExists('expense_vendor_payment_allocations');
        Schema::dropIfExists('expense_vendor_payments');
        Schema::dropIfExists('document_sequences');
        MigrationHelpers::dropPermissions(self::PERMISSIONS);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Claim Register - Supplier Claims & Recoveries
     *
     * Tracks claims raised against suppliers (Debtors A/C 1111) and
     * recoveries received via bank transfer (HBL Main Account).
     */
    public function up(): void
    {
        Schema::create('claim_registers', function (Blueprint $table) {
            $table->id();

            // Supplier & Transaction Details
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('transaction_date')->index();
            $table->string('reference_number')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('claim_month')->nullable()->index();
            $table->date('date_of_dispatch')->nullable();

            // Transaction Type & Double-Entry Amounts
            $table->enum('transaction_type', ['claim', 'recovery'])->default('claim')->index()
                ->comment('claim = DR (supplier owes us), recovery = CR (we received payment)');
            $table->decimal('debit', 15, 2)->default(0)->comment('Claim amount (DR)');
            $table->decimal('credit', 15, 2)->default(0)->comment('Recovery amount (CR)');

            // GL Accounts (auto-set: debit=1111 Debtors, credit=HBL Main Bank COA)
            $table->foreignId('debit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Payment (always bank_transfer to HBL Main)
            $table->string('payment_method')->default('bank_transfer');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();

            // Notes
            $table->text('notes')->nullable();

            // Posting Info
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            // System Fields
            $table->userTracking();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['supplier_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_registers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds bank reconciliation tracking to journal entry details.
     * This allows marking individual journal lines as reconciled against bank statements.
     */
    public function up(): void
    {
        Schema::table('journal_entry_details', function (Blueprint $table) {
            // Reconciliation status for bank transactions
            $table->enum('reconciliation_status', ['unreconciled', 'cleared', 'reconciled'])
                ->default('unreconciled')
                ->after('description');

            // Date when this line was reconciled
            $table->timestamp('reconciled_at')->nullable()->after('reconciliation_status');

            // User who performed the reconciliation
            $table->foreignId('reconciled_by')
                ->nullable()
                ->after('reconciled_at')
                ->constrained('users')
                ->nullOnDelete();

            // Reference to the bank statement this was reconciled against
            $table->string('bank_statement_reference', 100)->nullable()->after('reconciled_by');

            // Index for querying unreconciled items
            $table->index('reconciliation_status', 'idx_jed_reconciliation_status');
        });

        // Create bank reconciliations table to track reconciliation sessions
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 15, 2);
            $table->decimal('book_balance', 15, 2);
            $table->decimal('difference', 15, 2)->default(0);
            $table->enum('status', ['in_progress', 'completed', 'cancelled'])->default('in_progress');
            $table->text('notes')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('statement_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');

        Schema::table('journal_entry_details', function (Blueprint $table) {
            $table->dropIndex('idx_jed_reconciliation_status');
            $table->dropForeign(['reconciled_by']);
            $table->dropColumn([
                'reconciliation_status',
                'reconciled_at',
                'reconciled_by',
                'bank_statement_reference',
            ]);
        });
    }
};

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
        // STEP 1: Changed table name to 'journal_entry_details'
        Schema::create('journal_entry_details', function (Blueprint $table) {
            $table->id();

            // This correctly links to the 'journal_entries' (header) table
            $table->foreignId('journal_entry_id')->constrained()->onDelete('cascade')->comment('Links to the journal_entries header.');

            $table->unsignedInteger('line_no')->comment('Line number within the journal entry. Must be set explicitly by application.');

            // This correctly links to your Chart of Accounts
            $table->foreignId('chart_of_account_id')->constrained()->onDelete('restrict')->comment('Links to the specific account.');

            // Optional cost center / project for analytics
            $table->foreignId('cost_center_id')->nullable()->constrained()->onDelete('restrict')->comment('Optional: Links to cost center or project for analytics.');

            // STEP 2: Removed the redundant 'transaction_date' column.
            // The date is already in the 'journal_entries' header.

            // These are perfect.
            $table->decimal('debit', 15, 2)->default(0.00)->comment('Debit amount.');
            $table->decimal('credit', 15, 2)->default(0.00)->comment('Credit amount.');
            $table->string('description')->nullable()->comment('A note about this specific line.');

            // Reconciliation status for bank transactions
            $table->enum('reconciliation_status', ['unreconciled', 'cleared', 'reconciled'])->default('unreconciled');
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bank_statement_reference', 100)->nullable();

            $table->timestamps();

            $table->unique(['journal_entry_id', 'line_no'], 'ux_journal_line');
            $table->index('chart_of_account_id');
            $table->index('cost_center_id');
            $table->index('reconciliation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_details');
    }
};

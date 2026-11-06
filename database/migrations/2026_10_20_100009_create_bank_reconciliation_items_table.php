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
        Schema::create('bank_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->foreignId('bank_statement_entry_id')->constrained('bank_statement_entries')->cascadeOnDelete();
            $table->foreignId('journal_entry_detail_id')->nullable()->constrained('journal_entry_details')->nullOnDelete();
            $table->string('match_type', 10)->default('manual')->comment('auto or manual');
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->useCurrent();

            $table->unique(['bank_reconciliation_id', 'bank_statement_entry_id'], 'bank_recon_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_items');
    }
};

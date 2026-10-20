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
        Schema::create('bank_cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->string('cheque_no', 50);
            $table->string('direction', 10)->comment('issued or received');
            $table->date('cheque_date');
            $table->decimal('amount', 15, 2);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->nullableMorphs('counterparty');
            $table->string('payee_name')->nullable();
            $table->string('status', 15)->default('pending')->comment('pending, cleared, bounced, cancelled or stale');
            $table->date('cleared_date')->nullable();
            $table->foreignId('bank_statement_entry_id')->nullable()->constrained('bank_statement_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['bank_account_id', 'direction', 'cheque_no']);
            $table->index('status');
            $table->index('cheque_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_cheques');
    }
};

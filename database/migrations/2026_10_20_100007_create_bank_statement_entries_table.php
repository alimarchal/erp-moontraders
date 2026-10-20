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
        Schema::create('bank_statement_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('import_id')->nullable()->constrained('bank_statement_imports')->nullOnDelete();
            $table->foreignId('bank_reconciliation_id')->nullable()->constrained('bank_reconciliations')->nullOnDelete();
            $table->unsignedInteger('statement_ref_no')->nullable()->comment('Row number / Statement ID in the source sheet.');

            $table->date('posting_date');
            $table->date('value_date')->nullable();
            $table->string('reference')->nullable();
            $table->text('narration')->nullable();
            $table->text('description')->nullable()->comment('Accountant note, editable.');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('running_balance', 15, 2)->nullable();

            $table->string('party_name_in_statement')->nullable();
            $table->foreignId('bank_party_alias_id')->nullable()->constrained('bank_party_aliases')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->nullableMorphs('counterparty');
            $table->foreignId('bank_entry_category_id')->nullable()->constrained('bank_entry_categories')->nullOnDelete();
            $table->string('classification_source', 20)->default('unresolved')->comment('original, client_applied or unresolved');
            $table->boolean('needs_review')->default(false);

            $table->string('receipt_no', 40)->nullable()->unique();
            $table->string('status', 20)->default('unclassified');
            $table->unsignedTinyInteger('approval_level_required')->default(0);
            $table->unsignedSmallInteger('approval_round')->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('journal_entry_detail_id')->nullable()->constrained('journal_entry_details')->nullOnDelete();

            $table->string('source_statement')->nullable();
            $table->unsignedInteger('pdf_page')->nullable();
            $table->string('dedupe_hash', 64)->unique();

            $table->foreignId('classified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('classified_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['bank_account_id', 'posting_date']);
            $table->index('status');
            $table->index('reference');
            $table->index('party_name_in_statement');
            $table->index('debit');
            $table->index('credit');
            $table->index('needs_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_statement_entries');
    }
};

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
        Schema::create('bank_receipt_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->date('scan_date');
            $table->decimal('amount', 15, 2)->nullable()->comment('Optional: helps to find the matching bank line.');
            $table->string('direction', 10)->nullable()->comment('credit (received) or debit (paid), when known');
            $table->text('note')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 10)->default('pending')->comment('pending or linked');
            $table->foreignId('bank_statement_entry_id')->nullable()->constrained('bank_statement_entries')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scan_date']);
            $table->index('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_receipt_scans');
    }
};

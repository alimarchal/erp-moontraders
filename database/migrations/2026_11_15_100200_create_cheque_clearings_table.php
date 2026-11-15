<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One clearing batch groups the cheques cleared together (into a bank or as cash) and holds the notes received.
     */
    public function up(): void
    {
        Schema::create('cheque_clearings', function (Blueprint $table) {
            $table->id();
            $table->date('cleared_date')->index();
            $table->string('cleared_via', 10);
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            foreach ([5000, 1000, 500, 100, 50, 20, 10] as $note) {
                $table->unsignedInteger('denom_'.$note)->default(0);
            }
            $table->decimal('denom_coins', 10, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('sales_settlement_cheques', function (Blueprint $table) {
            $table->foreignId('cheque_clearing_id')->nullable()->constrained('cheque_clearings')->nullOnDelete();
            $table->foreignId('bounce_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_settlement_cheques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bounce_journal_entry_id');
            $table->dropConstrainedForeignId('cheque_clearing_id');
        });

        Schema::dropIfExists('cheque_clearings');
    }
};

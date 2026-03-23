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
        Schema::table('customer_employee_account_transactions', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->after('notes')->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('journal_entry_id');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_employee_account_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn('posted_at');
            $table->dropConstrainedForeignId('journal_entry_id');
        });
    }
};

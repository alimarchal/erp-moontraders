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
        Schema::table('revenue_details', function (Blueprint $table) {
            if (Schema::hasColumn('revenue_details', 'debit_account_id')) {
                $table->dropConstrainedForeignId('debit_account_id');
            }

            if (Schema::hasColumn('revenue_details', 'credit_account_id')) {
                $table->dropConstrainedForeignId('credit_account_id');
            }

            if (Schema::hasColumn('revenue_details', 'journal_entry_id')) {
                $table->dropConstrainedForeignId('journal_entry_id');
            }

            $columns = array_filter([
                Schema::hasColumn('revenue_details', 'debit') ? 'debit' : null,
                Schema::hasColumn('revenue_details', 'credit') ? 'credit' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('revenue_categories', function (Blueprint $table) {
            if (Schema::hasColumn('revenue_categories', 'income_account_id')) {
                $table->dropConstrainedForeignId('income_account_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revenue_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('revenue_categories', 'income_account_id')) {
                $table->foreignId('income_account_id')->nullable()->after('slug')->constrained('chart_of_accounts')->nullOnDelete();
            }
        });

        Schema::table('revenue_details', function (Blueprint $table) {
            if (! Schema::hasColumn('revenue_details', 'debit')) {
                $table->decimal('debit', 15, 2)->default(0)->after('amount');
            }

            if (! Schema::hasColumn('revenue_details', 'credit')) {
                $table->decimal('credit', 15, 2)->default(0)->after('debit');
            }

            if (! Schema::hasColumn('revenue_details', 'debit_account_id')) {
                $table->foreignId('debit_account_id')->nullable()->after('credit')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('revenue_details', 'credit_account_id')) {
                $table->foreignId('credit_account_id')->nullable()->after('debit_account_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('revenue_details', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')->nullable()->after('notes')->constrained('journal_entries')->nullOnDelete();
            }
        });
    }
};

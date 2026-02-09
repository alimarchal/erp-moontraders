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
        // This is the "Header" table for the entire transaction
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->onUpdate('cascade')->onDelete('restrict')->comment('Foreign key to the currencies table.');
            $table->foreignId('accounting_period_id')->nullable()->constrained()->onDelete('restrict')->comment('Links to the accounting period for this entry.');
            $table->decimal('fx_rate_to_base', 15, 6)->default(1.000000)->comment('Exchange rate to base currency at time of posting.');
            $table->date('entry_date')->comment('The date the transaction occurred.');
            $table->text('description')->nullable()->comment('Memo for the entire journal entry (e.g., "Paid monthly rent").');
            $table->string('reference')->nullable()->comment('Optional reference like invoice #, check #, etc.');
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft')->comment('e.g., "Posted", "Pending".');
            $table->boolean('is_closing_entry')->default(false);
            $table->foreignId('closes_period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index('entry_date');
            $table->index('status');
            $table->index(['status', 'entry_date']);
            $table->index('accounting_period_id');
            $table->index('is_closing_entry');
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->foreign('closing_journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->dropForeign(['closing_journal_entry_id']);
        });
        Schema::dropIfExists('journal_entries');
    }
};

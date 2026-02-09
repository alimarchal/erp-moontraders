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
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('e.g., "Fiscal Year 2025" or "Q1 2025"');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed', 'archived'])->default('open')->comment('Controls if transactions can be posted.');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('closing_journal_entry_id')->nullable();
            $table->decimal('closing_total_debits', 17, 2)->nullable();
            $table->decimal('closing_total_credits', 17, 2)->nullable();
            $table->decimal('closing_net_income', 17, 2)->nullable();
            $table->timestamps();

            // Add indexes for quick lookups by date
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};

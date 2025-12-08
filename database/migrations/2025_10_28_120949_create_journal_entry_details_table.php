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
        // STEP 1: Changed table name to 'journal_entry_details'
        Schema::create('journal_entry_details', function (Blueprint $table) {
            $table->id();

            // This correctly links to the 'journal_entries' (header) table
            $table->foreignId('journal_entry_id')->constrained()->onDelete('cascade')->comment('Links to the journal_entries header.');

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

            $table->timestamps();
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

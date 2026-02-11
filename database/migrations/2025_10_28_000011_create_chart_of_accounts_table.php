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
        // --- 1. Create the Table Structure ---
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();

            // --- Hierarchy & Type ---
            $table->foreignId('parent_id')->nullable()->comment('Self-referencing key for parent account.')->constrained('chart_of_accounts')->onDelete('restrict');
            $table->foreignId('account_type_id')->constrained()->onUpdate('cascade')->onDelete('restrict')->comment('Foreign key to the account_types table.');
            $table->foreignId('currency_id')->constrained()->onUpdate('cascade')->onDelete('restrict')->comment('Foreign key to the currencies table.');

            // --- Account Details ---
            $table->string('account_code', 20)->unique()->comment('The unique number/code for the account (e.g., 1110).');
            $table->string('account_name')->comment('The human-readable name (e.g., Cash and Bank).');
            $table->enum('normal_balance', ['debit', 'credit'])->comment('Crucial for transaction validation.');
            $table->text('description')->nullable()->comment('Optional notes for the account.');

            // --- Behavior Flags ---
            $table->boolean('is_group')->default(false)->comment('True if this is a grouping account (not postable).');
            $table->boolean('is_active')->default(true)->comment('If false, no new transactions can be posted.');

            $table->timestamps();

            $table->index('is_active', 'idx_coa_active');
            $table->index(['account_type_id', 'is_active'], 'idx_coa_type_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};

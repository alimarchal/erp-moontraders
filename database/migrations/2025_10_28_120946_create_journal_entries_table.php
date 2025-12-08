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
        // This is the "Header" table for the entire transaction
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->onUpdate('cascade')->onDelete('restrict')->comment('Foreign key to the currencies table.');
            $table->date('entry_date')->comment('The date the transaction occurred.');
            $table->text('description')->nullable()->comment('Memo for the entire journal entry (e.g., "Paid monthly rent").');
            $table->string('reference')->nullable()->comment('Optional reference like invoice #, check #, etc.');
            $table->string('status')->comment('e.g., "Posted", "Pending".');
            $table->timestamps();

            $table->index('entry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};

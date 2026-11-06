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
        Schema::create('bank_receipt_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10)->comment('BR for receipts, BP for payments');
            $table->string('fiscal_year', 9)->comment('e.g. 2024-25 (July to June)');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'fiscal_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_receipt_sequences');
    }
};

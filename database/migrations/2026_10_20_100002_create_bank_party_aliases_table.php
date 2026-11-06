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
        Schema::create('bank_party_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias_name')->unique()->comment('Normalized spelling as it appears on the bank statement.');
            $table->string('canonical_name');
            $table->foreignId('company_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->nullableMorphs('counterparty');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_party_aliases');
    }
};

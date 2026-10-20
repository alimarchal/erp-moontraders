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
        Schema::create('bank_entry_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('match_field', 20)->default('narration')->comment('narration, party or reference');
            $table->string('match_type', 20)->default('contains')->comment('contains, starts_with or equals');
            $table->string('match_value');
            $table->string('direction', 10)->default('any')->comment('any, debit or credit');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('bank_entry_category_id')->nullable()->constrained('bank_entry_categories')->nullOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_entry_rules');
    }
};

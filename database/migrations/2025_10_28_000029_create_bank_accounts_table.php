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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name')->comment('Bank account name/title');
            $table->string('account_number')->unique()->comment('Bank account number');
            $table->string('bank_name')->nullable()->comment('Name of the bank');
            $table->string('branch')->nullable()->comment('Branch name/code');
            $table->string('iban')->nullable()->comment('IBAN if applicable');
            $table->string('swift_code')->nullable()->comment('SWIFT/BIC code');
            $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->comment('Linked Chart of Account');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};

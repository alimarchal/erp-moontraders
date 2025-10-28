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
        /*
         -- Raw SQL to Create Table (MySQL/MariaDB Dialect)
         CREATE TABLE account_types (
             id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
             type_name VARCHAR(255) NOT NULL,
             report_group VARCHAR(255) NOT NULL,
             created_at TIMESTAMP NULL,
             updated_at TIMESTAMP NULL
         );
         */
        Schema::create('account_types', function (Blueprint $table) {
            $table->id()->comment('Primary key.');
            $table->string('type_name')->comment('Name of the account type (e.g., Asset, Liability).');
            $table->string('report_group')
                ->comment('Financial report group (e.g., BalanceSheet, IncomeStatement).');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_types');
    }
};

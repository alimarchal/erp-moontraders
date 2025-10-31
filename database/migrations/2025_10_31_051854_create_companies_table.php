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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('company_name')->unique();
            $table->string('abbr')->nullable();
            $table->string('country')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('domain')->nullable();

            // Contact Details
            $table->string('phone_no')->nullable();
            $table->string('email')->nullable();
            $table->string('fax')->nullable();
            $table->string('website')->nullable();

            // Company Details
            $table->text('company_logo')->nullable();
            $table->text('company_description')->nullable();
            $table->text('registration_details')->nullable();
            $table->date('date_of_establishment')->nullable();
            $table->date('date_of_incorporation')->nullable();
            $table->date('date_of_commencement')->nullable();

            // Hierarchy (Nested Set Model)
            $table->foreignId('parent_company_id')->nullable()->constrained('companies')->onDelete('set null');
            $table->boolean('is_group')->default(false);
            $table->integer('lft')->default(0)->index();
            $table->integer('rgt')->default(0)->index();

            // Financial Defaults
            $table->foreignId('default_currency_id')->nullable()->constrained('currencies')->onDelete('set null');
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->onDelete('set null');

            // Accounting Accounts (Foreign keys to chart_of_accounts)
            $table->foreignId('default_bank_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('default_cash_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('default_receivable_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('default_payable_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('default_expense_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('default_income_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('write_off_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('round_off_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');

            // Inventory Settings
            $table->boolean('enable_perpetual_inventory')->default(true);
            $table->foreignId('default_inventory_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('stock_adjustment_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');

            // Other Settings
            $table->boolean('allow_account_creation_against_child_company')->default(false);
            $table->decimal('credit_limit', 21, 2)->default(0);
            $table->decimal('monthly_sales_target', 21, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

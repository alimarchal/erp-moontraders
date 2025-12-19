<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop customer_ledgers table - replaced by customer_employee_account_transactions
        Schema::dropIfExists('customer_ledgers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate customer_ledgers if needed for rollback
        // (Structure omitted for brevity - refer to original migration if needed)
    }
};

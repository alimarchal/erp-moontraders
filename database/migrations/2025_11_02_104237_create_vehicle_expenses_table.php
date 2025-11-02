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
        Schema::create('vehicle_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('expense_date');
            $table->enum('expense_type', ['fuel', 'toll', 'tax', 'maintenance', 'misc'])->default('fuel');
            $table->decimal('amount', 15, 2);
            $table->decimal('odometer_reading', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_number')->nullable();

            // Double-entry accounting integration
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()
                ->comment('Links to journal entry: Debit Vehicle Expense (by type), Credit Cash/Bank');
            $table->enum('posting_status', ['draft', 'posted', 'cancelled'])->default('draft')
                ->comment('draft=not posted to accounting, posted=journal entry created, cancelled=voided');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['expense_date', 'posting_status']);
            $table->index(['vehicle_id', 'expense_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_expenses');
    }
};

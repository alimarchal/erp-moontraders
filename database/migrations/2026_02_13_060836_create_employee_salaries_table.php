<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee Salaries — Salary Structure / Configuration
     *
     * Defines the salary structure per employee per effective period.
     * Historical records are maintained via effective_from / effective_to dates.
     * Each employee can have multiple salary records but only one active at a time.
     */
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();

            // ── Employee & Supplier Reference ─────────────────────────────
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete()
                ->comment('Employee whose salary structure this defines');

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete()
                ->comment('Supplier the employee belongs to (denormalized for supplier-wise filtering)');

            // ── Salary Components ─────────────────────────────────────────
            $table->decimal('basic_salary', 15, 2)->default(0)
                ->comment('Base monthly salary amount');

            $table->decimal('allowances', 15, 2)->default(0)
                ->comment('Total monthly allowances (house rent, transport, medical, etc.)');

            $table->decimal('deductions', 15, 2)->default(0)
                ->comment('Total monthly standard deductions (provident fund, EOBI, etc.)');

            $table->decimal('net_salary', 15, 2)->default(0)
                ->comment('Net salary = basic_salary + allowances - deductions');

            // ── Effective Period ───────────────────────────────────────────
            $table->date('effective_from')->index()
                ->comment('Date from which this salary structure applies');

            $table->date('effective_to')->nullable()->index()
                ->comment('Date until which this salary structure applies (null = current/ongoing)');

            $table->boolean('is_active')->default(true)->index()
                ->comment('Whether this is the current active salary structure');

            // ── Additional Info ───────────────────────────────────────────
            $table->text('notes')->nullable()
                ->comment('Notes about salary revision, reason for change, etc.');

            // ── System Fields ─────────────────────────────────────────────
            $table->userTracking();
            $table->softDeletes();
            $table->timestamps();

            // ── Composite Indexes for Report Performance ──────────────────
            $table->index(['employee_id', 'is_active'], 'idx_emp_salary_active');
            $table->index(['employee_id', 'effective_from'], 'idx_emp_salary_effective');
            $table->index(['supplier_id', 'is_active'], 'idx_supplier_salary_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};

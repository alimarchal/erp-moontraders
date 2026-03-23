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
        Schema::create('leger_registers', function (Blueprint $table) {
            $table->id();

            // Supplier & Transaction Details
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('transaction_date')->index();
            $table->string('document_type')->nullable()->index();
            $table->string('document_number')->nullable()->index();
            $table->string('sap_code')->nullable()->index();
            $table->decimal('online_amount', 15, 2)->default(0);
            $table->decimal('invoice_amount', 15, 2)->default(0);
            $table->decimal('expenses_amount', 15, 2)->default(0);
            $table->decimal('za_point_five_percent_amount', 15, 2)->default(0);
            $table->decimal('claim_adjust_amount', 15, 2)->default(0);
            $table->decimal('advance_tax_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('remarks')->nullable();

            // System Fields
            $table->userTracking();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leger_registers');
    }
};

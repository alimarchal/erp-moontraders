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
        Schema::create('claim_registers', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date')->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade')->name('fk_supplier_id');
            $table->string('reference_number')->nullable()->index()->comment('Claim number, cheque number, etc.');
            $table->text('description')->nullable();
            $table->date('claim_month_start')->index()->comment('Start date of the claim month');
            $table->date('claim_month_end')->index()->comment('End date of the claim month');
            $table->date('date_of_dispatch')->index();
            // Double-entry 
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);


            $table->string('payment_method')->nullable()->comment('Cash, Cheque, BankTransfer');
            $table->string('cheque_number')->nullable();
            $table->date('cheque_date')->nullable();


            $table->string('status')->index()->comment('Type of transaction'); // 'Pending','PartialAdjust','Adjusted',
            $table->string('amount_transferred');
            $table->date('adjusted_date')->index();




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
        Schema::dropIfExists('claim_registers');
    }
};

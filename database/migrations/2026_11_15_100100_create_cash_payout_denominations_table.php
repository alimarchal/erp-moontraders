<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notes handed out for a cash payment (an expense voucher), so the cash sheet can show which notes left the till.
     */
    public function up(): void
    {
        Schema::create('cash_payout_denominations', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            foreach ([5000, 1000, 500, 100, 50, 20, 10] as $note) {
                $table->unsignedInteger('denom_'.$note)->default(0);
            }
            $table->decimal('denom_coins', 10, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_payout_denominations');
    }
};

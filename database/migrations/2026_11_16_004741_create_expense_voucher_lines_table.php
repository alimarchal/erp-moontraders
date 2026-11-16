<?php

use App\Models\ExpenseVoucherLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One shop bill on one voucher can hold several expense lines, each booked to its own expense account.
     * Every voucher made before this gets one line of its own account, category, vehicle, narration and amount.
     */
    public function up(): void
    {
        if (! Schema::hasTable('expense_voucher_lines')) {
            Schema::create('expense_voucher_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expense_voucher_id')->constrained('expense_vouchers')->cascadeOnDelete();
                $table->unsignedSmallInteger('line_no');
                $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
                $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
                $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
                $table->string('description', 255)->nullable();
                $table->decimal('amount', 15, 2);
                $table->timestamps();

                $table->unique(['expense_voucher_id', 'line_no']);
            });
        }

        if (! Schema::hasColumn('expense_vouchers', 'vendor_bill_date')) {
            Schema::table('expense_vouchers', function (Blueprint $table) {
                $table->date('vendor_bill_date')->nullable()->after('vendor_bill_no');
            });
        }

        ExpenseVoucherLine::backfillMissing();
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_voucher_lines');

        if (Schema::hasColumn('expense_vouchers', 'vendor_bill_date')) {
            Schema::table('expense_vouchers', function (Blueprint $table) {
                $table->dropColumn('vendor_bill_date');
            });
        }
    }
};

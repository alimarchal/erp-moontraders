<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cheques gain the amount actually cleared, and every status change is kept in a history table.
     * Existing non-pending cheques get one history row so nothing already recorded is lost.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('sales_settlement_cheques', 'cleared_amount')) {
            Schema::table('sales_settlement_cheques', function (Blueprint $table) {
                $table->decimal('cleared_amount', 15, 2)->nullable()->after('cleared_date');
            });
        }

        Schema::dropIfExists('sales_settlement_cheque_histories');

        Schema::create('sales_settlement_cheque_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_cheque_id')->constrained('sales_settlement_cheques', 'id', 'cheque_history_cheque_fk')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->date('cleared_date')->nullable();
            $table->decimal('cleared_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users', 'id', 'cheque_history_user_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_settlement_cheque_id', 'created_at'], 'cheque_history_lookup');
        });

        DB::table('sales_settlement_cheques')->where('status', 'cleared')->whereNull('cleared_amount')->update(['cleared_amount' => DB::raw('amount')]);

        $now = now();
        DB::table('sales_settlement_cheques')
            ->where('status', '!=', 'pending')
            ->orderBy('id')
            ->chunkById(500, function ($cheques) use ($now) {
                DB::table('sales_settlement_cheque_histories')->insert($cheques->map(fn ($cheque) => [
                    'sales_settlement_cheque_id' => $cheque->id,
                    'from_status' => 'pending',
                    'to_status' => $cheque->status,
                    'cleared_date' => $cheque->cleared_date,
                    'cleared_amount' => $cheque->cleared_amount,
                    'notes' => 'Recorded before status history existed.',
                    'changed_by' => $cheque->updated_by,
                    'created_at' => $cheque->status_updated_at ?? $cheque->updated_at ?? $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_cheque_histories');

        Schema::table('sales_settlement_cheques', function (Blueprint $table) {
            $table->dropColumn('cleared_amount');
        });
    }
};

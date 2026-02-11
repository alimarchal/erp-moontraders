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
        Schema::create('accounting_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->index()->comment('Table being audited');
            $table->unsignedBigInteger('record_id')->index()->comment('ID of the record changed');
            $table->string('action', 20)->comment('INSERT, UPDATE, DELETE');
            $table->json('old_values')->nullable()->comment('Previous values (for UPDATE/DELETE)');
            $table->json('new_values')->nullable()->comment('New values (for INSERT/UPDATE)');
            $table->json('changed_fields')->nullable()->comment('List of fields that changed');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->comment('User who made the change');
            $table->ipAddress('ip_address')->nullable()->comment('IP address of the user');
            $table->text('user_agent')->nullable()->comment('Browser/client information');
            $table->timestamp('created_at')->useCurrent()->comment('When the change occurred');

            $table->index(['table_name', 'record_id']);
            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('account_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->onDelete('cascade');
            $table->foreignId('accounting_period_id')->constrained()->onDelete('cascade');
            $table->date('snapshot_date')->comment('Date of the snapshot');
            $table->decimal('opening_balance', 15, 2)->default(0)->comment('Balance at start of period');
            $table->decimal('period_debits', 15, 2)->default(0)->comment('Total debits in period');
            $table->decimal('period_credits', 15, 2)->default(0)->comment('Total credits in period');
            $table->decimal('closing_balance', 15, 2)->default(0)->comment('Balance at end of period');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['chart_of_account_id', 'accounting_period_id'], 'idx_uniq_acct_period');
            $table->index(['snapshot_date'], 'idx_snapshot_date');
            $table->index(['accounting_period_id', 'snapshot_date'], 'idx_period_snapshot_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_balance_snapshots');
        Schema::dropIfExists('accounting_audit_log');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            // Add accounting period link
            $table->foreignId('accounting_period_id')->nullable()->after('currency_id')
                ->constrained()->onDelete('restrict')
                ->comment('Links to the accounting period for this entry.');

            // Add FX rate for multi-currency support
            $table->decimal('fx_rate_to_base', 15, 6)->default(1.000000)->after('currency_id')
                ->comment('Exchange rate to base currency at time of posting.');

            // Add posting audit fields
            $table->timestamp('posted_at')->nullable()->after('status')
                ->comment('Timestamp when entry was posted.');
            $table->foreignId('posted_by')->nullable()->after('posted_at')
                ->constrained('users')->onDelete('restrict')
                ->comment('User who posted the entry.');

            // Add indexes (entry_date already exists from table creation)
            $table->index('status'); // Standalone index for status filtering
            $table->index(['status', 'entry_date']); // Composite index for combined queries
            $table->index('accounting_period_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE journal_entries ALTER COLUMN status TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE journal_entries ALTER COLUMN status SET NOT NULL');
            DB::statement("ALTER TABLE journal_entries ALTER COLUMN status SET DEFAULT 'draft'");
            DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS journal_entries_status_check');
            DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_status_check CHECK (status IN ('draft', 'posted', 'void'))");
        } else {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->enum('status', ['draft', 'posted', 'void'])->default('draft')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['posted_by']);
            $table->dropForeign(['accounting_period_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'entry_date']);
            $table->dropIndex(['accounting_period_id']);
            $table->dropColumn(['accounting_period_id', 'fx_rate_to_base', 'posted_at', 'posted_by']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS journal_entries_status_check');
            DB::statement('ALTER TABLE journal_entries ALTER COLUMN status DROP DEFAULT');
        } else {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->string('status')->change();
            });
        }
    }
};

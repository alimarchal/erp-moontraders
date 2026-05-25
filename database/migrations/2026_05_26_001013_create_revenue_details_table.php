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
        Schema::create('revenue_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revenue_category_id')->constrained('revenue_categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('transaction_date')->index();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->userTracking();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['revenue_category_id', 'transaction_date'], 'revenue_details_category_date_index');
            $table->index(['supplier_id', 'transaction_date'], 'revenue_details_supplier_date_index');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared("
                CREATE OR REPLACE FUNCTION fn_block_posted_revenue_updates()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        RAISE EXCEPTION 'Posted revenue details are immutable. Cannot update posted revenues.';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::unprepared('
                CREATE TRIGGER trg_block_posted_revenue_updates
                BEFORE UPDATE ON revenue_details
                FOR EACH ROW
                EXECUTE FUNCTION fn_block_posted_revenue_updates();
            ');

            DB::unprepared("
                CREATE OR REPLACE FUNCTION fn_block_posted_revenue_deletes()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        RAISE EXCEPTION 'Cannot delete posted revenue details. Posted revenues are immutable.';
                    END IF;
                    RETURN OLD;
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::unprepared('
                CREATE TRIGGER trg_block_posted_revenue_deletes
                BEFORE DELETE ON revenue_details
                FOR EACH ROW
                EXECUTE FUNCTION fn_block_posted_revenue_deletes();
            ');
        } else {
            DB::unprepared("
                CREATE TRIGGER trg_block_posted_revenue_updates
                BEFORE UPDATE ON revenue_details
                FOR EACH ROW
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Posted revenue details are immutable. Cannot update posted revenues.';
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_block_posted_revenue_deletes
                BEFORE DELETE ON revenue_details
                FOR EACH ROW
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Cannot delete posted revenue details. Posted revenues are immutable.';
                    END IF;
                END
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_revenue_updates ON revenue_details');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_revenue_deletes ON revenue_details');
            DB::unprepared('DROP FUNCTION IF EXISTS fn_block_posted_revenue_updates()');
            DB::unprepared('DROP FUNCTION IF EXISTS fn_block_posted_revenue_deletes()');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_revenue_updates');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_revenue_deletes');
        }

        Schema::dropIfExists('revenue_details');
    }
};

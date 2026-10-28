<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'sales_settlement_credit_sales',
        'sales_settlement_cheques',
        'sales_settlement_bank_transfers',
        'sales_settlement_recoveries',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'attachment')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('attachment', 500)->nullable()->comment('Optional PDF/image path (settlement attachments disk).');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'attachment')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('attachment');
            });
        }
    }
};

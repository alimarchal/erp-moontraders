<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-text employee category (e.g. Corporate, Powder, Liquid); empty for existing employees.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('designation');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};

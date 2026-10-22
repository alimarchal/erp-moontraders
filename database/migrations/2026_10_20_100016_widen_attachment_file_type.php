<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Office files have long MIME types (an .xlsx is 65 characters), which did not fit the 50 character
     * column and made attaching a spreadsheet fail with "Data too long for column file_type".
     */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('file_type', 150)->nullable()->change();
        });

        Schema::table('bank_receipt_scans', function (Blueprint $table) {
            $table->string('file_type', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Values longer than the old limit would be cut; the columns are left wide on purpose.
    }
};

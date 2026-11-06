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
        Schema::table('attachments', function (Blueprint $table) {
            $table->nullableMorphs('attachable');
            $table->string('label', 30)->default('other')->after('description')->comment('receipt, cheque, slip, statement or other');
            $table->foreignId('uploaded_by')->nullable()->after('label')->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->change();
        });

        DB::table('attachments')
            ->whereNotNull('journal_entry_id')
            ->update([
                'attachable_type' => 'App\\Models\\JournalEntry',
                'attachable_id' => DB::raw('journal_entry_id'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attachments')->whereNull('journal_entry_id')->delete();

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropMorphs('attachable');
            $table->dropColumn('label');
            $table->foreignId('journal_entry_id')->nullable(false)->change();
        });
    }
};

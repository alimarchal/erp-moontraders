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
        Schema::create('bank_entry_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_entry_id')->constrained('bank_statement_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->unsignedTinyInteger('level')->default(0)->comment('0 for submit/auto, 1 for checker, 2 for senior approver');
            $table->string('action', 20)->comment('submitted, approved, rejected or auto_approved');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->useCurrent();

            $table->index(['bank_statement_entry_id', 'round']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_entry_approvals');
    }
};

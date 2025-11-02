<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pick_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('picker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_date'], 'pl_status_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pick_lists');
    }
};

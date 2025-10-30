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
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->comment('Self-referencing key for parent cost center/project.')->constrained('cost_centers')->onDelete('restrict');
            $table->string('code', 20)->unique()->comment('Unique code for the cost center/project (e.g., CC001, PROJ2025).');
            $table->string('name')->comment('Name of the cost center or project (e.g., Marketing Department, Website Redesign).');
            $table->text('description')->nullable()->comment('Optional description of the cost center/project.');
            $table->enum('type', ['cost_center', 'project'])->default('cost_center')->comment('Type: cost_center for departments, project for specific projects.');
            $table->date('start_date')->nullable()->comment('Start date for projects.');
            $table->date('end_date')->nullable()->comment('End date for projects.');
            $table->boolean('is_active')->default(true)->comment('If false, no new transactions can be assigned.');
            $table->timestamps();

            // Indexes for quick lookups
            $table->index('code');
            $table->index('type');
            $table->index(['is_active', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};

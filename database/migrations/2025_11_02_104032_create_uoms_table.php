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
        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->string('uom_name')->unique();
            $table->string('symbol')->nullable();
            $table->text('description')->nullable();
            $table->boolean('must_be_whole_number')->default(false)->comment('Quantity must be integer');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['enabled', 'uom_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uoms');
    }
};

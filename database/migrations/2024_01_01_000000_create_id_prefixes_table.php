<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // ← ADD THIS LINE

return new class extends Migration {
    public function up(): void
    {
        Schema::create('id_prefixes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Type name like invoice, complaint');
            $table->string('prefix', 10)->unique()->comment('Short prefix like INV, CMP');
            $table->timestamps();
        });

        // Insert sample data
        DB::table('id_prefixes')->insert([
            ['name' => 'invoice', 'prefix' => 'INV', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'complaint', 'prefix' => 'CMP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'quotation', 'prefix' => 'QTN', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('id_prefixes');
    }
};
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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->onDelete('cascade')->comment('Links to the journal entry.');
            $table->string('file_name')->comment('Original name of the uploaded file.');
            $table->string('file_path')->comment('Storage path of the file.');
            $table->string('file_type', 50)->nullable()->comment('MIME type (e.g., application/pdf, image/jpeg).');
            $table->unsignedBigInteger('file_size')->nullable()->comment('File size in bytes.');
            $table->text('description')->nullable()->comment('Optional description of the attachment.');
            $table->timestamps();

            // Index for quick lookups
            $table->index('journal_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};

<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.ms-excel'];
        $fileType = fake()->randomElement($fileTypes);
        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/vnd.ms-excel' => 'xls',
        ];

        return [
            'journal_entry_id' => JournalEntry::factory(),
            'file_name' => fake()->word().'.'.$extensions[$fileType],
            'file_path' => 'attachments/'.fake()->uuid().'.'.$extensions[$fileType],
            'file_type' => $fileType,
            'file_size' => fake()->numberBetween(1024, 5242880), // 1KB to 5MB
            'description' => fake()->optional()->sentence(),
        ];
    }
}

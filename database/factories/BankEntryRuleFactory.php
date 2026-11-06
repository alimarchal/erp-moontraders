<?php

namespace Database\Factories;

use App\Models\BankEntryCategory;
use App\Models\BankEntryRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankEntryRule>
 */
class BankEntryRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'match_field' => 'narration',
            'match_type' => 'contains',
            'match_value' => fake()->unique()->word(),
            'direction' => 'any',
            'bank_entry_category_id' => BankEntryCategory::factory(),
            'priority' => 100,
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\BankEntryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankEntryCategory>
 */
class BankEntryCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => str($name)->slug()->value().'-'.fake()->unique()->numerify('###'),
            'type' => 'both',
            'default_chart_of_account_id' => null,
            'is_active' => true,
            'sort_order' => 100,
        ];
    }
}

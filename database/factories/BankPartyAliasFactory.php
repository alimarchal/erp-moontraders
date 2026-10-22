<?php

namespace Database\Factories;

use App\Models\BankPartyAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankPartyAlias>
 */
class BankPartyAliasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alias_name' => strtoupper(fake()->unique()->name()),
            'canonical_name' => fake()->name(),
            'is_active' => true,
        ];
    }
}

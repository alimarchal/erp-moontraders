<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Uom>
 */
class UomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uom_name' => $this->faker->unique()->words(2, true),
            'symbol' => strtoupper($this->faker->lexify('??')),
            'description' => $this->faker->optional()->sentence(),
            'must_be_whole_number' => $this->faker->boolean(30),
            'enabled' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ChequeClearing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChequeClearing>
 */
class ChequeClearingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cleared_date' => now()->toDateString(),
            'cleared_via' => 'cash',
            'denom_5000' => 0,
            'denom_1000' => 0,
            'denom_500' => 0,
            'denom_100' => 0,
            'denom_50' => 0,
            'denom_20' => 0,
            'denom_10' => 0,
            'denom_coins' => 0,
        ];
    }
}

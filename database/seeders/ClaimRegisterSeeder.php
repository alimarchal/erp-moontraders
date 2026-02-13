<?php

namespace Database\Seeders;

use App\Models\ClaimRegister;
use Illuminate\Database\Seeder;

class ClaimRegisterSeeder extends Seeder
{
    public function run(): void
    {
        ClaimRegister::factory()->count(10)->pending()->create();
        ClaimRegister::factory()->count(8)->adjusted()->create();
        ClaimRegister::factory()->count(7)->partialAdjust()->create();
    }
}

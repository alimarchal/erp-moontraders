<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'type' => TicketType::PriceUpdate,
            'status' => TicketStatus::Pending,
            'supplier_id' => Supplier::factory(),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}

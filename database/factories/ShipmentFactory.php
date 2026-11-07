<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'courier' => fake()->randomElement(['LBC Express', 'J&T Express', 'JRS Express', 'Ninja Van', 'Flash Express']),
            'tracking_number' => fake()->unique()->bothify('??##########'),
            'shipped_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
        ];
    }
}

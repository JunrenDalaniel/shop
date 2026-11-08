<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => fake()->unique()->bothify('ORD-########'),
            'status' => OrderStatus::Pending,
            'subtotal' => 0,
            'shipping_fee' => 0,
            'tax' => 0,
            'total' => 0,
            'shipping_name' => fake()->name(),
            'shipping_address' => fake()->streetAddress(),
            'shipping_city' => fake()->city(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country' => fake()->countryCode(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'stock_deducted_at' => now(),
        ]);
    }

    public function withCoupon(Coupon $coupon): static
    {
        return $this->state(fn () => [
            'coupon_id' => $coupon->getKey(),
            'coupon_code' => $coupon->code,
        ]);
    }
}

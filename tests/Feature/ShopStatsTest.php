<?php

use App\Enums\OrderStatus;
use App\Filament\Widgets\ShopStats;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('shop stats show paid revenue, orders today and pending orders', function () {
    Order::factory()->create(['status' => OrderStatus::Paid, 'total' => 1500.50]);
    Order::factory()->create(['status' => OrderStatus::Paid, 'total' => 500.00]);
    Order::factory()->create(['status' => OrderStatus::Shipped, 'total' => 9999.00]);
    Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 100.00]);
    Order::factory()->create(['status' => OrderStatus::Pending, 'created_at' => now()->subDays(3)]);

    Livewire::test(ShopStats::class)
        ->assertOk()
        ->assertSee('₱2,000.50')
        ->assertSeeInOrder(['Orders today', '4', 'Pending orders', '2']);
});

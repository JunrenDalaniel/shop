<?php

use App\Enums\OrderStatus;
use App\Models\Order;

test('a pending order can move to paid', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $order->moveTo(OrderStatus::Paid);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('a pending order cannot skip straight to delivered', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    expect(fn () => $order->moveTo(OrderStatus::Delivered))
        ->toThrow(InvalidArgumentException::class, 'Cannot move order from pending to delivered.');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

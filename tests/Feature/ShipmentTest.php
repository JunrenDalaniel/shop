<?php

use App\Models\Order;
use App\Models\Shipment;
use Carbon\CarbonInterface;

it('belongs to an order', function () {
    $shipment = Shipment::factory()->create();

    expect($shipment->order)->toBeInstanceOf(Order::class)
        ->and($shipment->shipped_at)->toBeInstanceOf(CarbonInterface::class);
});

it('is listed on its order', function () {
    $order = Order::factory()->create();
    $shipment = Shipment::factory()->for($order)->create();

    expect($order->shipments->pluck('id')->all())->toBe([$shipment->id]);
});

it('is deleted when its order is deleted', function () {
    $shipment = Shipment::factory()->create();

    $shipment->order->delete();

    expect(Shipment::query()->whereKey($shipment->id)->exists())->toBeFalse();
});

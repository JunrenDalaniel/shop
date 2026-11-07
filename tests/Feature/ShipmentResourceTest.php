<?php

use App\Filament\Resources\Shipments\Pages\CreateShipment;
use App\Filament\Resources\Shipments\Pages\ListShipments;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('shipments are listed, searchable and filterable by order', function () {
    $first = Shipment::factory()->create();
    $second = Shipment::factory()->create();

    Livewire::test(ListShipments::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$first, $second])
        ->searchTable($first->tracking_number)
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second])
        ->searchTable('')
        ->filterTable('order', $second->order_id)
        ->assertCanSeeTableRecords([$second])
        ->assertCanNotSeeTableRecords([$first]);
});

test('a shipment can be created for an order', function () {
    $order = Order::factory()->create();

    Livewire::test(CreateShipment::class)
        ->fillForm([
            'order_id' => $order->id,
            'courier' => 'LBC Express',
            'tracking_number' => 'LB1234567890',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($order->shipments()->where('tracking_number', 'LB1234567890')->exists())->toBeTrue();
});

test('shipment validation requires an order, courier and tracking number', function () {
    Livewire::test(CreateShipment::class)
        ->fillForm(['order_id' => null, 'courier' => null, 'tracking_number' => null])
        ->call('create')
        ->assertHasFormErrors(['order_id' => 'required', 'courier' => 'required', 'tracking_number' => 'required']);
});

<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('orders are listed and can be filtered by status', function () {
    $pending = Order::factory()->create(['status' => OrderStatus::Pending]);
    $shipped = Order::factory()->create(['status' => OrderStatus::Shipped]);

    Livewire::test(ListOrders::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$pending, $shipped])
        ->assertTableColumnStateSet('user.name', $pending->user->name, $pending)
        ->filterTable('status', OrderStatus::Shipped->value)
        ->assertCanSeeTableRecords([$shipped])
        ->assertCanNotSeeTableRecords([$pending]);
});

test('an order can be created with items, snapshots and totals', function () {
    $customer = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Mug', 'price' => 100]);

    Livewire::test(CreateOrder::class)
        ->fillForm([
            'user_id' => $customer->id,
            'status' => OrderStatus::Paid->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'price' => 100],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::where('user_id', $customer->id)->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe('Mug')
        ->and((float) $order->items->first()->line_total)->toBe(300.0)
        ->and((float) $order->subtotal)->toBe(300.0)
        ->and((float) $order->total)->toBe(300.0);
});

test('order validation requires a customer, status and at least one item', function () {
    Livewire::test(CreateOrder::class)
        ->fillForm(['user_id' => null, 'status' => null, 'items' => []])
        ->call('create')
        ->assertHasFormErrors(['user_id' => 'required', 'status' => 'required', 'items']);
});

test('editing an order recalculates its total', function () {
    $order = Order::factory()->create(['shipping_fee' => 50]);
    $item = OrderItem::factory()->for($order)->create(['quantity' => 1, 'price' => 100, 'line_total' => 100]);

    Livewire::test(EditOrder::class, ['record' => $order->id])
        ->fillForm([
            'status' => OrderStatus::Delivered->value,
            'items' => [
                'record-'.$item->id => ['product_id' => $item->product_id, 'quantity' => 2, 'price' => 100],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and((float) $order->subtotal)->toBe(200.0)
        ->and((float) $order->total)->toBe(250.0);
});

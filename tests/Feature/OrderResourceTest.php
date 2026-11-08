<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
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
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'price' => 100],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::where('user_id', $customer->id)->firstOrFail();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe('Mug')
        ->and((float) $order->items->first()->line_total)->toBe(300.0)
        ->and((float) $order->subtotal)->toBe(300.0)
        ->and((float) $order->total)->toBe(300.0);
});

test('order validation requires a customer and at least one item', function () {
    Livewire::test(CreateOrder::class)
        ->fillForm(['user_id' => null, 'items' => []])
        ->call('create')
        ->assertHasFormErrors(['user_id' => 'required', 'items']);
});

test('editing an order recalculates its total and cannot change its status', function () {
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

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and((float) $order->subtotal)->toBe(200.0)
        ->and((float) $order->total)->toBe(250.0);
});

test('the status field is read-only on the order form', function () {
    $order = Order::factory()->create();

    Livewire::test(EditOrder::class, ['record' => $order->id])
        ->assertFormFieldIsDisabled('status');
});

test('an order view page shows its details and the comments thread', function () {
    $order = Order::factory()->create();

    Livewire::test(ViewOrder::class, ['record' => $order->id])
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Comments');
});

test('a transition action moves the order after confirmation', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    Livewire::test(ListOrders::class)
        ->callAction(TestAction::make('markPaid')->table($order))
        ->assertNotified();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('transition actions are only visible when the move is allowed', function () {
    $pending = Order::factory()->create(['status' => OrderStatus::Pending]);
    $delivered = Order::factory()->create(['status' => OrderStatus::Delivered]);

    Livewire::test(ListOrders::class)
        ->assertActionVisible(TestAction::make('markPaid')->table($pending))
        ->assertActionVisible(TestAction::make('cancel')->table($pending))
        ->assertActionHidden(TestAction::make('ship')->table($pending))
        ->assertActionHidden(TestAction::make('markDelivered')->table($pending))
        ->assertActionHidden(TestAction::make('markPaid')->table($delivered))
        ->assertActionHidden(TestAction::make('cancel')->table($delivered));
});

test('the order view page offers the allowed transitions', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Shipped]);

    Livewire::test(ViewOrder::class, ['record' => $order->id])
        ->assertActionVisible('markDelivered')
        ->assertActionHidden('markPaid')
        ->callAction('markDelivered')
        ->assertNotified();

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

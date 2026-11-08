<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

test('the view page renders customer, items, totals and status history', function () {
    $customer = User::factory()->create(['name' => 'Ana Reyes', 'email' => 'ana@example.test']);
    $order = Order::factory()->for($customer)->create([
        'status' => OrderStatus::Pending,
        'shipping_city' => 'Quezon City',
    ]);
    OrderItem::factory()->for($order)->create([
        'product_name' => 'Ceramic Mug',
        'quantity' => 2,
        'price' => '150.00',
        'line_total' => '300.00',
    ]);
    $order->update(['subtotal' => '300.00', 'shipping_fee' => '50.00', 'total' => '350.00']);
    $history = OrderStatusHistory::factory()->for($order)->create();

    Livewire::test(ViewOrder::class, ['record' => $order->id])
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Ana Reyes')
        ->assertSee('ana@example.test')
        ->assertSee('Quezon City')
        ->assertSee('Ceramic Mug')
        ->assertSee('300.00')
        ->assertSee('350.00')
        ->assertSee('Status history')
        ->assertSee($history->to_status->getLabel());
});

test('the coupon section is shown with a coupon and hidden without one', function () {
    $coupon = Coupon::factory()->create(['code' => 'SAVE100', 'discount' => '100.00']);
    $withCoupon = Order::factory()->withCoupon($coupon)->create();
    $without = Order::factory()->create();

    Livewire::test(ViewOrder::class, ['record' => $withCoupon->id])
        ->assertSee('SAVE100')
        ->assertSee('100.00');

    Livewire::test(ViewOrder::class, ['record' => $without->id])
        ->assertDontSee('SAVE100');
});

test('the apply coupon action applies a coupon to a pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'subtotal' => '500.00', 'total' => '500.00']);
    OrderItem::factory()->for($order)->create(['quantity' => 5, 'price' => '100.00', 'line_total' => '500.00']);
    $coupon = Coupon::factory()->create(['discount' => '120.00']);

    Livewire::test(ViewOrder::class, ['record' => $order->id])
        ->assertActionVisible('applyCoupon')
        ->callAction('applyCoupon', ['coupon_id' => $coupon->id])
        ->assertHasNoActionErrors();

    expect($order->fresh()->coupon_code)->toBe($coupon->code)
        ->and($order->fresh()->total)->toBe('380.00');
});

test('the apply coupon action requires a coupon', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    Livewire::test(ViewOrder::class, ['record' => $order->id])
        ->callAction('applyCoupon', ['coupon_id' => null])
        ->assertHasActionErrors(['coupon_id' => 'required']);

    expect($order->fresh()->coupon_id)->toBeNull();
});

test('the remove coupon action clears the coupon', function () {
    $coupon = Coupon::factory()->create(['discount' => '100.00']);
    $order = Order::factory()->withCoupon($coupon)->create(['status' => OrderStatus::Pending]);
    OrderItem::factory()->for($order)->create(['quantity' => 5, 'price' => '100.00', 'line_total' => '500.00']);

    Livewire::test(ViewOrder::class, ['record' => $order->id])
        ->assertActionVisible('removeCoupon')
        ->callAction('removeCoupon');

    expect($order->fresh()->coupon_id)->toBeNull()
        ->and($order->fresh()->coupon_code)->toBeNull()
        ->and($order->fresh()->total)->toBe('500.00');
});

test('coupon actions respect visibility by status and coupon', function () {
    $coupon = Coupon::factory()->create();
    $pendingNoCoupon = Order::factory()->create(['status' => OrderStatus::Pending]);
    $pendingWithCoupon = Order::factory()->withCoupon($coupon)->create(['status' => OrderStatus::Pending]);
    $paid = Order::factory()->paid()->withCoupon($coupon)->create();

    Livewire::test(ViewOrder::class, ['record' => $pendingNoCoupon->id])
        ->assertActionVisible('applyCoupon')
        ->assertActionHidden('removeCoupon');

    Livewire::test(ViewOrder::class, ['record' => $pendingWithCoupon->id])
        ->assertActionVisible('applyCoupon')
        ->assertActionVisible('removeCoupon');

    Livewire::test(ViewOrder::class, ['record' => $paid->id])
        ->assertActionHidden('applyCoupon')
        ->assertActionHidden('removeCoupon');
});

test('a transition with insufficient stock shows a notification and leaves the order unchanged', function () {
    $product = Product::factory()->create(['name' => 'Rare Vase', 'stock' => 1]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_name' => 'Rare Vase',
        'quantity' => 3,
        'price' => '10.00',
        'line_total' => '30.00',
    ]);

    Livewire::test(ViewOrder::class, ['record' => $order->id])
        ->callAction('markPaid')
        ->assertNotified();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->fresh()->stock_deducted_at)->toBeNull()
        ->and($product->fresh()->stock)->toBe(1)
        ->and($order->statusHistories()->count())->toBe(0);
});

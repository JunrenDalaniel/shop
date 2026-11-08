<?php

use App\Enums\OrderStatus;
use App\Events\OrderCouponApplied;
use App\Events\OrderCouponRemoved;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\Event;

/**
 * Build a pending order with one item: subtotal 500.00, shipping 50.00, total 550.00.
 *
 * @param  array<string, mixed>  $attributes
 */
function couponTestOrder(array $attributes = []): Order
{
    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'shipping_fee' => '50.00',
        'tax' => '0.00',
        ...$attributes,
    ]);
    OrderItem::factory()->for($order)->create([
        'product_id' => Product::factory()->create(['stock' => 100])->id,
        'quantity' => 5,
        'price' => '100.00',
        'line_total' => '500.00',
    ]);
    $order->recalculateTotals();

    return $order->fresh();
}

test('applying a coupon sets the code and discount and recalculates the total', function () {
    $order = couponTestOrder();
    $coupon = Coupon::factory()->create(['discount' => '120.00']);

    $order->applyCoupon($coupon);

    $order->refresh();
    expect($order->coupon_id)->toBe($coupon->id)
        ->and($order->coupon_code)->toBe($coupon->code)
        ->and($order->discount_amount)->toBe('120.00')
        ->and($order->subtotal)->toBe('500.00')
        ->and($order->total)->toBe('430.00');
});

test('the discount is capped at the subtotal and the total never goes below zero', function () {
    $order = couponTestOrder(['shipping_fee' => '0.00']);
    $coupon = Coupon::factory()->create(['discount' => '900.00']);

    $order->applyCoupon($coupon);

    $order->refresh();
    expect($order->discount_amount)->toBe('500.00')
        ->and($order->total)->toBe('0.00');
});

test('an expired or inactive coupon throws and changes nothing', function (string $state) {
    Event::fake([OrderCouponApplied::class]);
    $order = couponTestOrder();
    $coupon = Coupon::factory()->{$state}()->create();

    expect(fn () => $order->applyCoupon($coupon))->toThrow(InvalidCouponException::class);

    $order->refresh();
    expect($order->coupon_id)->toBeNull()
        ->and($order->coupon_code)->toBeNull()
        ->and($order->discount_amount)->toBe('0.00')
        ->and($order->total)->toBe('550.00');
    Event::assertNotDispatched(OrderCouponApplied::class);
})->with(['expired', 'inactive']);

test('applying a coupon to a non-pending order throws', function () {
    $order = couponTestOrder();
    $order->moveTo(OrderStatus::Paid);
    $coupon = Coupon::factory()->create();

    expect(fn () => $order->fresh()->applyCoupon($coupon))->toThrow(InvalidCouponException::class);

    expect($order->fresh()->coupon_id)->toBeNull();
});

test('applying a second coupon replaces the first', function () {
    $order = couponTestOrder();
    $first = Coupon::factory()->create(['discount' => '100.00']);
    $second = Coupon::factory()->create(['discount' => '30.00']);

    $order->applyCoupon($first);
    $order->fresh()->applyCoupon($second);

    $order->refresh();
    expect($order->coupon_id)->toBe($second->id)
        ->and($order->coupon_code)->toBe($second->code)
        ->and($order->discount_amount)->toBe('30.00')
        ->and($order->total)->toBe('520.00');
});

test('removing a coupon restores the total', function () {
    $order = couponTestOrder();
    $order->applyCoupon(Coupon::factory()->create(['discount' => '100.00']));

    $order->fresh()->removeCoupon();

    $order->refresh();
    expect($order->coupon_id)->toBeNull()
        ->and($order->coupon_code)->toBeNull()
        ->and($order->discount_amount)->toBe('0.00')
        ->and($order->total)->toBe('550.00');
});

test('removing a coupon from an order without one is a silent no-op', function () {
    Event::fake([OrderCouponRemoved::class]);
    $order = couponTestOrder();

    $order->removeCoupon();

    expect($order->fresh()->total)->toBe('550.00');
    Event::assertNotDispatched(OrderCouponRemoved::class);
});

test('removing a coupon from a non-pending order throws', function () {
    $order = couponTestOrder();
    $order->applyCoupon(Coupon::factory()->create());
    $order->fresh()->moveTo(OrderStatus::Paid);

    expect(fn () => $order->fresh()->removeCoupon())->toThrow(InvalidCouponException::class);
});

test('coupon events dispatch on success', function () {
    Event::fake([OrderCouponApplied::class, OrderCouponRemoved::class]);
    $order = couponTestOrder();
    $coupon = Coupon::factory()->create();

    $order->applyCoupon($coupon);
    Event::assertDispatched(OrderCouponApplied::class, fn (OrderCouponApplied $event): bool => $event->order->is($order) && $event->coupon->is($coupon));
    Event::assertNotDispatched(OrderCouponRemoved::class);

    $order->fresh()->removeCoupon();
    Event::assertDispatched(OrderCouponRemoved::class, fn (OrderCouponRemoved $event): bool => $event->order->is($order) && $event->couponCode === $coupon->code);
});

test('deleting a coupon keeps the code on the order', function () {
    $order = couponTestOrder();
    $coupon = Coupon::factory()->create(['discount' => '100.00']);
    $order->applyCoupon($coupon);

    $coupon->delete();

    $order->refresh();
    expect($order->coupon_id)->toBeNull()
        ->and($order->coupon_code)->toBe($coupon->code);
});

test('recalculating totals is a no-op once the order is not pending', function () {
    $order = couponTestOrder();
    $order->moveTo(OrderStatus::Paid);
    OrderItem::factory()->for($order)->create(['quantity' => 1, 'price' => '100.00', 'line_total' => '100.00']);

    $order->fresh()->recalculateTotals();

    $order->refresh();
    expect($order->subtotal)->toBe('500.00')
        ->and($order->total)->toBe('550.00');
});

<?php

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\Event;

/**
 * Add an item for the product to the order.
 */
function addStockTestItem(Order $order, Product $product, int $quantity): OrderItem
{
    return OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => $quantity,
        'price' => '10.00',
        'line_total' => number_format($quantity * 10, 2, '.', ''),
    ]);
}

test('paying an order takes each item quantity and marks stock as deducted', function () {
    $mug = Product::factory()->create(['stock' => 10]);
    $plate = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    addStockTestItem($order, $mug, 3);
    addStockTestItem($order, $plate, 2);

    $order->moveTo(OrderStatus::Paid);

    expect($mug->fresh()->stock)->toBe(7)
        ->and($plate->fresh()->stock)->toBe(3)
        ->and($order->fresh()->stock_deducted_at)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('paying fails when stock is short and nothing changes', function () {
    Event::fake([OrderStatusChanged::class]);
    $plenty = Product::factory()->create(['stock' => 10]);
    $scarce = Product::factory()->create(['stock' => 1]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    addStockTestItem($order, $plenty, 2);
    addStockTestItem($order, $scarce, 2);

    expect(fn () => $order->moveTo(OrderStatus::Paid))
        ->toThrow(InsufficientStockException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->fresh()->stock_deducted_at)->toBeNull()
        ->and($plenty->fresh()->stock)->toBe(10)
        ->and($scarce->fresh()->stock)->toBe(1)
        ->and($order->statusHistories()->count())->toBe(0);
    Event::assertNotDispatched(OrderStatusChanged::class);
});

test('cancelling a paid order restores stock and clears the deduction marker', function () {
    $mug = Product::factory()->create(['stock' => 10]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    addStockTestItem($order, $mug, 3);
    $order->moveTo(OrderStatus::Paid);

    $order->fresh()->moveTo(OrderStatus::Cancelled);

    expect($mug->fresh()->stock)->toBe(10)
        ->and($order->fresh()->stock_deducted_at)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('cancelling a pending order changes no stock', function () {
    $mug = Product::factory()->create(['stock' => 10]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    addStockTestItem($order, $mug, 3);

    $order->moveTo(OrderStatus::Cancelled);

    expect($mug->fresh()->stock)->toBe(10)
        ->and($order->fresh()->stock_deducted_at)->toBeNull();
});

test('cancelling a legacy paid order without a deduction marker changes no stock', function () {
    $mug = Product::factory()->create(['stock' => 10]);
    $order = Order::factory()->create(['status' => OrderStatus::Paid, 'stock_deducted_at' => null]);
    addStockTestItem($order, $mug, 3);

    $order->moveTo(OrderStatus::Cancelled);

    expect($mug->fresh()->stock)->toBe(10);
});

test('a soft-deleted product is still adjusted', function () {
    $mug = Product::factory()->create(['stock' => 10]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    addStockTestItem($order, $mug, 3);
    $mug->delete();

    $order->moveTo(OrderStatus::Paid);
    expect(Product::withTrashed()->find($mug->id)->stock)->toBe(7);

    $order->fresh()->moveTo(OrderStatus::Cancelled);
    expect(Product::withTrashed()->find($mug->id)->stock)->toBe(10);
});

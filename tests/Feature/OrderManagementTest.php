<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;

test('an order belongs to a user and has items for products', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->for($order)->for($product)->create();

    expect($order->user->is($user))->toBeTrue()
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product->is($product))->toBeTrue()
        ->and($user->orders)->toHaveCount(1)
        ->and($product->category->products)->toHaveCount(1);
});

test('orders and products are linked through order items with pivot data', function () {
    $product = Product::factory()->create();
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);

    expect($order->products->first()->is($product))->toBeTrue()
        ->and($order->products->first()->pivot->quantity)->toBe(3)
        ->and($product->orders->first()->is($order))->toBeTrue()
        ->and($product->category->orderItems)->toHaveCount(1);
});

test('order status is cast to the OrderStatus enum', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('an order item keeps its price snapshot when the product changes', function () {
    $product = Product::factory()->create(['price' => 100]);
    $item = OrderItem::factory()->for($product)->create(['price' => 100, 'quantity' => 2, 'line_total' => 200]);

    $product->update(['price' => 150]);

    expect($item->fresh()->price)->toBe('100.00')
        ->and($item->fresh()->line_total)->toBe('200.00');
});

test('a soft deleted product is still available on its order items', function () {
    $item = OrderItem::factory()->create();

    $item->product->delete();

    expect($item->fresh()->product)->not->toBeNull()
        ->and($item->fresh()->product->trashed())->toBeTrue();
});

test('a product cannot appear twice in the same order', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    OrderItem::factory()->for($order)->for($product)->create();

    OrderItem::factory()->for($order)->for($product)->create();
})->throws(QueryException::class);

test('a user with orders cannot be deleted', function () {
    $order = Order::factory()->create();

    $order->user->delete();
})->throws(QueryException::class);

test('product skus must be unique', function () {
    Product::factory()->create(['sku' => 'SKU-1']);

    Product::factory()->create(['sku' => 'SKU-1']);
})->throws(QueryException::class);

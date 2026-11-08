<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

test('the status filter accepts a single status', function () {
    $pending = Order::factory()->create(['status' => OrderStatus::Pending]);
    $shipped = Order::factory()->create(['status' => OrderStatus::Shipped]);

    Livewire::test(ListOrders::class)
        ->filterTable('status', [OrderStatus::Shipped->value])
        ->assertCanSeeTableRecords([$shipped])
        ->assertCanNotSeeTableRecords([$pending]);
});

test('the status filter accepts multiple statuses', function () {
    $pending = Order::factory()->create(['status' => OrderStatus::Pending]);
    $shipped = Order::factory()->create(['status' => OrderStatus::Shipped]);
    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    Livewire::test(ListOrders::class)
        ->filterTable('status', [OrderStatus::Pending->value, OrderStatus::Shipped->value])
        ->assertCanSeeTableRecords([$pending, $shipped])
        ->assertCanNotSeeTableRecords([$cancelled]);
});

/**
 * Create three orders created on 2026-03-10, 2026-03-15 and 2026-03-20.
 *
 * @return array{0: Order, 1: Order, 2: Order}
 */
function ordersOnThreeDays(): array
{
    return [
        Order::factory()->create(['created_at' => '2026-03-10 00:00:00']),
        Order::factory()->create(['created_at' => '2026-03-15 12:00:00']),
        Order::factory()->create(['created_at' => '2026-03-20 23:59:59']),
    ];
}

test('the date filter with only a from date includes that day onward', function () {
    [$early, $middle, $late] = ordersOnThreeDays();

    Livewire::test(ListOrders::class)
        ->filterTable('created_at', ['from' => '2026-03-15', 'until' => null])
        ->assertCanSeeTableRecords([$middle, $late])
        ->assertCanNotSeeTableRecords([$early]);
});

test('the date filter with only an until date includes that day and earlier', function () {
    [$early, $middle, $late] = ordersOnThreeDays();

    Livewire::test(ListOrders::class)
        ->filterTable('created_at', ['from' => null, 'until' => '2026-03-15'])
        ->assertCanSeeTableRecords([$early, $middle])
        ->assertCanNotSeeTableRecords([$late]);
});

test('the date filter with both dates is inclusive on both boundary days', function () {
    [$early, $middle, $late] = ordersOnThreeDays();

    Livewire::test(ListOrders::class)
        ->filterTable('created_at', ['from' => '2026-03-10', 'until' => '2026-03-20'])
        ->assertCanSeeTableRecords([$early, $middle, $late]);

    Livewire::test(ListOrders::class)
        ->filterTable('created_at', ['from' => '2026-03-11', 'until' => '2026-03-19'])
        ->assertCanSeeTableRecords([$middle])
        ->assertCanNotSeeTableRecords([$early, $late]);
});

test('the status and date filters combine', function () {
    $matching = Order::factory()->create(['status' => OrderStatus::Paid, 'created_at' => '2026-03-15 10:00:00']);
    $wrongStatus = Order::factory()->create(['status' => OrderStatus::Pending, 'created_at' => '2026-03-15 10:00:00']);
    $wrongDate = Order::factory()->create(['status' => OrderStatus::Paid, 'created_at' => '2026-02-01 10:00:00']);

    Livewire::test(ListOrders::class)
        ->filterTable('status', [OrderStatus::Paid->value])
        ->filterTable('created_at', ['from' => '2026-03-01', 'until' => '2026-03-31'])
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$wrongStatus, $wrongDate]);
});

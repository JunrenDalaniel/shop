<?php

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\StatusHistoriesRelationManager;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('a valid move records from, to, who and when', function () {
    $admin = User::factory()->create();
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs($admin);
    $order->moveTo(OrderStatus::Paid);

    $history = $order->statusHistories()->sole();

    expect($history->from_status)->toBe(OrderStatus::Pending)
        ->and($history->to_status)->toBe(OrderStatus::Paid)
        ->and($history->changed_by)->toBe($admin->id)
        ->and($history->created_at)->not->toBeNull();
});

test('an explicit user overrides the authenticated user', function () {
    $actor = User::factory()->create();
    $this->actingAs(User::factory()->create());
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $order->moveTo(OrderStatus::Paid, $actor);

    expect($order->statusHistories()->sole()->changedBy->is($actor))->toBeTrue();
});

test('the actor is null when nobody is authenticated', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $order->moveTo(OrderStatus::Cancelled);

    expect($order->statusHistories()->sole()->changed_by)->toBeNull();
});

test('a refused move writes no history', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    expect(fn () => $order->moveTo(OrderStatus::Delivered))->toThrow(InvalidArgumentException::class);

    expect(OrderStatusHistory::count())->toBe(0);
});

test('a move still dispatches the status changed event', function () {
    Event::fake([OrderStatusChanged::class]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $order->moveTo(OrderStatus::Paid);

    Event::assertDispatched(OrderStatusChanged::class);
});

test('status histories are returned newest first', function () {
    $order = Order::factory()->create();
    $older = OrderStatusHistory::factory()->for($order)->create(['created_at' => now()->subDay()]);
    $newer = OrderStatusHistory::factory()->for($order)->create(['created_at' => now()]);

    expect($order->statusHistories->modelKeys())->toBe([$newer->id, $older->id]);
});

test('the relation manager lists the history read-only', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $order = Order::factory()->create();
    $entry = OrderStatusHistory::factory()->for($order)->create();
    $system = OrderStatusHistory::factory()->for($order)->bySystem()->create();

    Livewire::test(StatusHistoriesRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ViewOrder::class,
    ])
        ->assertCanSeeTableRecords([$entry, $system])
        ->assertTableColumnStateSet('changedBy.name', $entry->changedBy->name, $entry)
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});

test('running a transition action in the admin adds a history row', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    Livewire::test(ListOrders::class)
        ->callAction(TestAction::make('markPaid')->table($order));

    expect($order->statusHistories()->sole()->changed_by)->toBe($admin->id);
});

test('the backfill migration adds one starting entry to orders without history', function () {
    $bare = Order::factory()->create(['status' => OrderStatus::Shipped, 'placed_at' => now()->subWeek()]);
    $tracked = Order::factory()->create(['status' => OrderStatus::Paid]);
    $existing = OrderStatusHistory::factory()->for($tracked)->create();

    $migration = require database_path('migrations/2026_11_08_070027_backfill_order_status_histories.php');
    $migration->up();
    $migration->up();

    $entry = $bare->statusHistories()->sole();

    expect($entry->from_status)->toBeNull()
        ->and($entry->to_status)->toBe(OrderStatus::Shipped)
        ->and($entry->changed_by)->toBeNull()
        ->and($entry->created_at->isSameSecond($bare->placed_at))->toBeTrue()
        ->and($tracked->statusHistories()->pluck('id')->all())->toBe([$existing->id]);
});

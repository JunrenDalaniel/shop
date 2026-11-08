<?php

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Listeners\SendOrderStatusNotification;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Filament\Actions\Testing\TestAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('moving an order dispatches OrderStatusChanged with the previous status', function () {
    Event::fake([OrderStatusChanged::class]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $order->moveTo(OrderStatus::Paid);

    Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->order->is($order)
        && $event->previousStatus === OrderStatus::Pending
        && $event->order->status === OrderStatus::Paid);
});

test('a refused transition dispatches no event', function () {
    Event::fake([OrderStatusChanged::class]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    expect(fn () => $order->moveTo(OrderStatus::Delivered))->toThrow(InvalidArgumentException::class);

    Event::assertNotDispatched(OrderStatusChanged::class);
});

test('the listener is queued', function () {
    expect(new SendOrderStatusNotification)->toBeInstanceOf(ShouldQueue::class);
});

test('the customer is emailed the order number, new status and a link', function () {
    Notification::fake();
    $order = Order::factory()->create(['status' => OrderStatus::Paid, 'order_number' => 'ORD-12345678']);

    $order->moveTo(OrderStatus::Shipped);

    Notification::assertSentTo($order->user, OrderStatusUpdated::class, function (OrderStatusUpdated $notification) use ($order) {
        $mail = $notification->toMail($order->user);
        $text = $mail->subject.' '.implode(' ', $mail->introLines).' '.$mail->actionUrl;

        return in_array('mail', $notification->via($order->user), true)
            && str_contains($text, 'ORD-12345678')
            && str_contains($text, 'Shipped')
            && $mail->actionUrl === route('home');
    });
});

test('cancelled orders get a friendlier message', function () {
    Notification::fake();
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $order->moveTo(OrderStatus::Cancelled);

    Notification::assertSentTo($order->user, OrderStatusUpdated::class, function (OrderStatusUpdated $notification) use ($order) {
        $text = implode(' ', $notification->toMail($order->user)->introLines);

        return str_contains($text, 'sorry') && str_contains($text, 'cancelled');
    });
});

test('cancelling from the admin order page emails the customer', function () {
    Notification::fake();
    $this->actingAs(User::factory()->superAdmin()->create());
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    Livewire::test(ViewOrder::class, ['record' => $order->id])
        ->callAction('cancel');

    Notification::assertSentTo($order->user, OrderStatusUpdated::class);
});

test('shipping from the admin orders table emails the customer', function () {
    Notification::fake();
    $this->actingAs(User::factory()->superAdmin()->create());
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);

    Livewire::test(ListOrders::class)
        ->callAction(TestAction::make('ship')->table($order));

    Notification::assertSentTo($order->user, OrderStatusUpdated::class);
});

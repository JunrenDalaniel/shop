<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderStatusNotification implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(OrderStatusChanged $event): void
    {
        $event->order->user->notify(new OrderStatusUpdated($event->order));
    }
}

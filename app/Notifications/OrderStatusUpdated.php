<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    public function __construct(public Order $order) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->order->status;

        $message = (new MailMessage)
            ->subject("Order {$this->order->order_number}: {$status->getLabel()}")
            ->greeting("Hi {$notifiable->name},");

        if ($status === OrderStatus::Cancelled) {
            return $message
                ->line("We're sorry, your order {$this->order->order_number} has been cancelled.")
                ->line("If this wasn't what you expected or you have any questions, just reply to this email and we'll be happy to help.")
                ->action('Continue shopping', route('home'));
        }

        return $message
            ->line("Good news! Your order {$this->order->order_number} is now {$status->getLabel()}.")
            ->action('Visit the shop', route('home'));
    }
}

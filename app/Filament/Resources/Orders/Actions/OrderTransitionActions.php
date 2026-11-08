<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class OrderTransitionActions
{
    /**
     * One confirmable action per status move, each shown only when the order may make that move.
     *
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::transition('markPaid', 'Mark as paid', OrderStatus::Paid, 'info', Heroicon::Banknotes),
            self::transition('ship', 'Ship', OrderStatus::Shipped, 'warning', Heroicon::Truck),
            self::transition('markDelivered', 'Mark as delivered', OrderStatus::Delivered, 'success', Heroicon::CheckCircle),
            self::transition('cancel', 'Cancel', OrderStatus::Cancelled, 'danger', Heroicon::XCircle),
        ];
    }

    private static function transition(string $name, string $label, OrderStatus $target, string $color, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalDescription(fn (Order $record): string => "Move order {$record->order_number} from {$record->status->getLabel()} to {$target->getLabel()}?")
            ->visible(fn (Order $record): bool => $record->status->canMoveTo($target))
            ->action(function (Order $record) use ($target): void {
                $record->moveTo($target);
            })
            ->successNotificationTitle("Order marked as {$target->getLabel()}");
    }
}

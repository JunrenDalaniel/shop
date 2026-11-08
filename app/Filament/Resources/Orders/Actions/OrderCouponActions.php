<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class OrderCouponActions
{
    /**
     * Apply and remove a coupon, both available only while the order is pending.
     *
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::applyCoupon(),
            self::removeCoupon(),
        ];
    }

    private static function applyCoupon(): Action
    {
        return Action::make('applyCoupon')
            ->label('Apply coupon')
            ->icon(Heroicon::Ticket)
            ->color('gray')
            ->modalHeading('Apply coupon')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
            ->schema([
                Select::make('coupon_id')
                    ->label('Coupon')
                    ->options(fn (): array => Coupon::active()->orderBy('code')->pluck('code', 'id')->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, Order $record): void {
                self::attempt(function () use ($data, $record): void {
                    $record->applyCoupon(Coupon::findOrFail($data['coupon_id']));
                }, 'Coupon applied');
            });
    }

    private static function removeCoupon(): Action
    {
        return Action::make('removeCoupon')
            ->label('Remove coupon')
            ->icon(Heroicon::XMark)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remove coupon')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending && filled($record->coupon_code))
            ->action(function (Order $record): void {
                self::attempt(fn () => $record->removeCoupon(), 'Coupon removed');
            });
    }

    private static function attempt(callable $change, string $successTitle): void
    {
        try {
            $change();
        } catch (InvalidCouponException $exception) {
            Notification::make()
                ->danger()
                ->title('Coupon could not be changed')
                ->body($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->send();
    }
}

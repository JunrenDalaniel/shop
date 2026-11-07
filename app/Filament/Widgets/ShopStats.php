<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class ShopStats extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $paidRevenue = Order::query()
            ->where('status', OrderStatus::Paid)
            ->sum('total');

        return [
            Stat::make('Paid revenue', Number::currency((float) $paidRevenue, 'PHP')),
            Stat::make('Orders today', Order::query()->whereDate('created_at', today())->count()),
            Stat::make('Pending orders', Order::query()->where('status', OrderStatus::Pending)->count()),
        ];
    }
}

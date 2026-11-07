<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Product Name')
                    ->weight('bold')
                    ->size('lg'),

                TextEntry::make('price')
                    ->label('Price')
                    ->money('PHP'),

                TextEntry::make('stock')
                    ->label('Stock Quantity')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    }),

                TextEntry::make('created_at')
                    ->label('Created At')
                    ->dateTime('M d, Y h:i A'),

                TextEntry::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M d, Y h:i A'),
            ]);
    }
}
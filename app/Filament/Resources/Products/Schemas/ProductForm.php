<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Product Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Enter product name'),

                TextInput::make('price')
                    ->label('Price')
                    ->numeric()
                    ->prefix('₱')
                    ->required()
                    ->minValue(0)
                    ->step(0.01)
                    ->placeholder('0.00'),

                TextInput::make('stock')
                    ->label('Stock Quantity')
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(0)
                    ->default(0)
                    ->placeholder('Enter stock quantity'),
            ]);
    }
}
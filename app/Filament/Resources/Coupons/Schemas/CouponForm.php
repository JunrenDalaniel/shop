<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('discount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₱'),
                DatePicker::make('expires_at'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}

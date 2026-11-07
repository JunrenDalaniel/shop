<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Tilto\Commentable\Filament\Infolists\Components\CommentsEntry;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number'),
                        TextEntry::make('user.name')
                            ->label('Customer'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('total')
                            ->money('PHP'),
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->date('M d, Y'),
                    ]),

                Section::make('Comments')
                    ->columnSpanFull()
                    ->schema([
                        CommentsEntry::make('comments')
                            ->nestable(),
                    ]),
            ]);
    }
}

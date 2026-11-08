<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
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
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->date('M d, Y'),
                        TextEntry::make('paid_at')
                            ->dateTime('M d, Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('shipped_at')
                            ->dateTime('M d, Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('cancelled_at')
                            ->dateTime('M d, Y H:i')
                            ->placeholder('-'),
                    ]),

                Section::make('Customer')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Name'),
                        TextEntry::make('user.email')
                            ->label('Email'),
                        TextEntry::make('shipping_name')
                            ->label('Ship to'),
                        TextEntry::make('shipping_address')
                            ->label('Address'),
                        TextEntry::make('shipping_city')
                            ->label('City'),
                        TextEntry::make('shipping_postal_code')
                            ->label('Postal code'),
                        TextEntry::make('shipping_country')
                            ->label('Country'),
                    ]),

                Section::make('Items')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label('Product'),
                                TextEntry::make('quantity'),
                                TextEntry::make('price')
                                    ->money('PHP'),
                                TextEntry::make('line_total')
                                    ->money('PHP'),
                            ]),
                    ]),

                Section::make('Coupon')
                    ->columnSpanFull()
                    ->columns(2)
                    ->visible(fn (Order $record): bool => filled($record->coupon_code))
                    ->schema([
                        TextEntry::make('coupon_code')
                            ->label('Code'),
                        TextEntry::make('discount_amount')
                            ->label('Discount')
                            ->money('PHP'),
                    ]),

                Section::make('Totals')
                    ->columnSpanFull()
                    ->columns(5)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->money('PHP'),
                        TextEntry::make('discount_amount')
                            ->label('Discount')
                            ->money('PHP'),
                        TextEntry::make('shipping_fee')
                            ->money('PHP'),
                        TextEntry::make('tax')
                            ->money('PHP'),
                        TextEntry::make('total')
                            ->money('PHP')
                            ->weight('bold'),
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

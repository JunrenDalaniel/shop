<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Customer')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('status')
                    ->options(OrderStatus::class)
                    ->default(OrderStatus::Pending)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Change the status with the order actions.')
                    ->native(false),

                Repeater::make('items')
                    ->relationship('items')
                    ->columnSpanFull()
                    ->columns(3)
                    ->minItems(1)
                    ->addActionLabel('Add item')
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::withSnapshot($data))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::withSnapshot($data))
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('price', Product::find($state)?->price);
                            }),

                        TextInput::make('quantity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        TextInput::make('price')
                            ->label('Unit price')
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0)
                            ->step(0.01)
                            ->required(),
                    ]),
            ]);
    }

    /**
     * Fill the snapshot columns on order_items that are not part of the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withSnapshot(array $data): array
    {
        $data['product_name'] = Product::withTrashed()->find($data['product_id'])?->name ?? '';
        $data['line_total'] = round((float) $data['quantity'] * (float) $data['price'], 2);

        return $data;
    }
}

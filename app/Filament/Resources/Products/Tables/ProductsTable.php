<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->contentGrid([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])

            ->columns([
                Stack::make([
                    ImageColumn::make('image')
                        ->label('Product Image')
                        ->height(180)
                        ->width('100%')
                        ->defaultImageUrl(url('/images/no-image.png')),

                    TextColumn::make('name')
                        ->label('Product Name')
                        ->searchable()
                        ->sortable()
                        ->weight('bold')
                        ->size('lg'),

                    TextColumn::make('price')
                        ->money('PHP')
                        ->sortable()
                        ->color('success')
                        ->weight('bold'),

                    TextColumn::make('stock')
                        ->label('Available Stock')
                        ->numeric()
                        ->badge()
                        ->color(fn ($state) => match (true) {
                            $state <= 0 => 'danger',
                            $state <= 10 => 'warning',
                            default => 'success',
                        }),

                    TextColumn::make('created_at')
                        ->label('Created At')
                        ->dateTime('M d, Y')
                        ->color('gray'),

                ])->space(3),
            ])

            ->filters([
                SelectFilter::make('stock')
                    ->label('Stock Status')
                    ->options([
                        'in_stock' => 'In Stock',
                        'out_of_stock' => 'Out of Stock',
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['value'] === 'in_stock',
                                fn ($query) => $query->where('stock', '>', 0)
                            )
                            ->when(
                                $data['value'] === 'out_of_stock',
                                fn ($query) => $query->where('stock', '<=', 0)
                            );
                    }),
            ])

            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])

            ->defaultSort('created_at', 'desc')

            ->paginated([12, 24, 36, 48])

            ->defaultPaginationPageOption(10);
    }
}

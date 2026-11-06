<?php

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public const LOW_STOCK_THRESHOLD = 20;

    public static function fromStock(int $stock): self
    {
        return match (true) {
            $stock <= 0 => self::OutOfStock,
            $stock < self::LOW_STOCK_THRESHOLD => self::LowStock,
            default => self::InStock,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::InStock => __('In Stock'),
            self::LowStock => __('Low Stock'),
            self::OutOfStock => __('Out of Stock'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'emerald',
            self::LowStock => 'amber',
            self::OutOfStock => 'red',
        };
    }
}

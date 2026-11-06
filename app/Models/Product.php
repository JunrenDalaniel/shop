<?php

namespace App\Models;

use App\Enums\StockStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'slug',
        'description',
        'price',
        'stock',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function stockStatus(): StockStatus
    {
        return StockStatus::fromStock($this->stock);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithStockStatus(Builder $query, StockStatus $status): Builder
    {
        return match ($status) {
            StockStatus::OutOfStock => $query->where('stock', '<=', 0),
            StockStatus::LowStock => $query->whereBetween('stock', [1, StockStatus::LOW_STOCK_THRESHOLD - 1]),
            StockStatus::InStock => $query->where('stock', '>=', StockStatus::LOW_STOCK_THRESHOLD),
        };
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot(['product_name', 'quantity', 'price', 'line_total'])
            ->withTimestamps();
    }
}

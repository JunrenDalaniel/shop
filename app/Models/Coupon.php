<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'discount',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount' => 'decimal:2',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', today()));
    }

    protected function isExpired(): Attribute
    {
        return Attribute::get(fn (): bool => $this->expires_at !== null && $this->expires_at->isPast() && ! $this->expires_at->isToday());
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isRedeemable(): bool
    {
        return $this->is_active && ! $this->is_expired;
    }

    /**
     * Discount to take off $subtotal: the face value, capped at the subtotal and never negative.
     */
    public function discountFor(string $subtotal): string
    {
        $discount = bccomp((string) $this->discount, $subtotal, 2) === 1 ? $subtotal : (string) $this->discount;

        return bccomp($discount, '0', 2) === -1 ? '0.00' : bcadd($discount, '0', 2);
    }
}

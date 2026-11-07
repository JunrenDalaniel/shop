<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}

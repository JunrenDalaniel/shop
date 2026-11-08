<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Events\OrderCouponApplied;
use App\Events\OrderCouponRemoved;
use App\Events\OrderStatusChanged;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tilto\Commentable\Contracts\Commentable;
use Tilto\Commentable\Traits\HasComments;

class Order extends Model implements Commentable
{
    /** @use HasFactory<OrderFactory> */
    use HasComments, HasFactory;

    protected $fillable = [
        'user_id',
        'coupon_id',
        'coupon_code',
        'order_number',
        'status',
        'subtotal',
        'discount_amount',
        'shipping_fee',
        'tax',
        'total',
        'shipping_name',
        'shipping_address',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'notes',
        'placed_at',
        'paid_at',
        'shipped_at',
        'cancelled_at',
        'stock_deducted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'stock_deducted_at' => 'datetime',
            'shipping_fee' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest()->latest('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot(['product_name', 'quantity', 'price', 'line_total'])
            ->withTimestamps();
    }

    /**
     * Move the order to the given status, refusing transitions the current status does not allow.
     * The change is recorded in the status history, attributed to $by or the authenticated user.
     *
     * @throws InvalidArgumentException
     */
    public function moveTo(OrderStatus $next, ?User $by = null): void
    {
        if (! $this->status->canMoveTo($next)) {
            throw new InvalidArgumentException(
                "Cannot move order from {$this->status->value} to {$next->value}."
            );
        }

        $previous = $this->status;
        $changedBy = $by ?? auth()->user();

        DB::transaction(function () use ($next, $previous, $changedBy): void {
            if ($next === OrderStatus::Paid) {
                $this->deductStock();
            } elseif ($next === OrderStatus::Cancelled) {
                $this->restoreStock();
            }

            $this->update(['status' => $next]);

            $this->statusHistories()->create([
                'from_status' => $previous,
                'to_status' => $next,
                'changed_by' => $changedBy?->getKey(),
            ]);
        });

        OrderStatusChanged::dispatch($this, $previous);
    }

    /**
     * Apply a coupon to a pending order, replacing any coupon already applied.
     *
     * @throws InvalidCouponException
     */
    public function applyCoupon(Coupon $coupon): void
    {
        $this->ensurePending();

        if (! $coupon->isRedeemable()) {
            throw new InvalidCouponException("Coupon {$coupon->code} is not redeemable.");
        }

        DB::transaction(function () use ($coupon): void {
            $this->update(['coupon_id' => $coupon->getKey(), 'coupon_code' => $coupon->code]);
            $this->unsetRelation('coupon');

            $this->recalculateTotals();
        });

        OrderCouponApplied::dispatch($this, $coupon);
    }

    /**
     * Remove the applied coupon from a pending order. Does nothing when none is applied.
     *
     * @throws InvalidCouponException
     */
    public function removeCoupon(): void
    {
        $this->ensurePending();

        if ($this->coupon_id === null && $this->coupon_code === null) {
            return;
        }

        $couponCode = (string) $this->coupon_code;

        DB::transaction(function (): void {
            $this->update(['coupon_id' => null, 'coupon_code' => null]);
            $this->unsetRelation('coupon');

            $this->recalculateTotals();
        });

        OrderCouponRemoved::dispatch($this, $couponCode);
    }

    /**
     * Recalculate subtotal, discount and total from the order's items and coupon.
     * Totals are frozen once the order leaves Pending.
     */
    public function recalculateTotals(): void
    {
        if (! $this->isPendingForTotals()) {
            return;
        }

        $subtotal = bcadd((string) $this->items()->sum('line_total'), '0', 2);
        $discount = $this->coupon_id !== null && $this->coupon !== null
            ? $this->coupon->discountFor($subtotal)
            : '0.00';

        $total = bcadd(bcsub($subtotal, $discount, 2), bcadd((string) $this->shipping_fee, (string) $this->tax, 2), 2);

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'total' => bccomp($total, '0', 2) === -1 ? '0.00' : $total,
        ]);
    }

    /**
     * A freshly created model has no status loaded yet; the column defaults to Pending.
     */
    private function isPendingForTotals(): bool
    {
        return $this->status === null || $this->status === OrderStatus::Pending;
    }

    /**
     * @throws InvalidCouponException
     */
    private function ensurePending(): void
    {
        if (! $this->isPendingForTotals()) {
            throw new InvalidCouponException('Coupons can only be changed on pending orders.');
        }
    }

    /**
     * Take each item's quantity from its product, in product order, and mark the stock as deducted.
     *
     * @throws InsufficientStockException
     */
    private function deductStock(): void
    {
        foreach ($this->quantitiesByProduct() as $productId => $quantity) {
            Product::withTrashed()->findOrFail($productId)->decrementStock($quantity);
        }

        $this->stock_deducted_at = now();
    }

    /**
     * Give the quantities back, only when stock was actually deducted for this order.
     */
    private function restoreStock(): void
    {
        if ($this->stock_deducted_at === null) {
            return;
        }

        foreach ($this->quantitiesByProduct() as $productId => $quantity) {
            Product::withTrashed()->findOrFail($productId)->incrementStock($quantity);
        }

        $this->stock_deducted_at = null;
    }

    /**
     * @return array<int, int> product id => summed quantity, ordered by product id
     */
    private function quantitiesByProduct(): array
    {
        return $this->items()
            ->whereNotNull('product_id')
            ->orderBy('product_id')
            ->get(['product_id', 'quantity'])
            ->groupBy('product_id')
            ->map(fn ($items): int => (int) $items->sum('quantity'))
            ->all();
    }
}

<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
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
        'order_number',
        'status',
        'subtotal',
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
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
     * Recalculate subtotal and total from the order's items.
     */
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('line_total');

        $this->update([
            'subtotal' => $subtotal,
            'total' => $subtotal + (float) $this->shipping_fee + (float) $this->tax,
        ]);
    }
}

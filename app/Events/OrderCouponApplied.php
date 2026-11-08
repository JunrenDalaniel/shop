<?php

namespace App\Events;

use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCouponApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public Coupon $coupon,
    ) {}
}

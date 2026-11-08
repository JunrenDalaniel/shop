<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give every order that has no history one starting entry holding its current status.
     */
    public function up(): void
    {
        DB::table('orders')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('order_status_histories')
                    ->whereColumn('order_status_histories.order_id', 'orders.id');
            })
            ->orderBy('id')
            ->chunkById(500, function ($orders): void {
                DB::table('order_status_histories')->insert(
                    $orders->map(fn (object $order): array => [
                        'order_id' => $order->id,
                        'from_status' => null,
                        'to_status' => $order->status,
                        'changed_by' => null,
                        'created_at' => $order->placed_at ?? $order->created_at,
                    ])->all()
                );
            });
    }

    /**
     * The backfilled entries are indistinguishable from real ones, so there is nothing safe to undo.
     */
    public function down(): void
    {
        //
    }
};

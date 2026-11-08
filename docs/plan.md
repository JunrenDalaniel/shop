# Order Module Completion: Contract

Shared contract for the backend, admin and test agents. Nothing outside it is in scope. Part 1 (status history) is already built in the working tree and is not repeated here; this contract builds on it.

Scope:
1. Stock goes down when an order is paid and back up when it is cancelled.
2. A coupon can be applied to an order and the total is recalculated.
3. The order View page shows customer, items, coupon, totals and status history.
4. The orders table gets status and date filters.

Rules that apply to every agent: money is `decimal(10,2)` and never `float` (use integer cents or bcmath strings in calculations), status changes only through `Order::moveTo()`, admin prices use `money('PHP')`, Filament 5 APIs only, no new packages, no edits to migrations that already ran, no Fortify/auth files.

## Preconditions

- The base must be committed before any worktree is created, because worktrees branch from `HEAD` (`8fcdcef`), which lacks Part 1. The base excludes the unrelated `composer.json`/`composer.lock` diff unless you say otherwise (open question 6).
- Order of work: **Backend first and merged**, then **Admin and Test in parallel**, each branching from the merged backend. Admin and Test both depend on the backend's models, methods and exceptions.
- If an agent finds a bug in a folder it doesn't own, it reports it and doesn't fix it.

## Schema

One new migration, `add_coupon_and_stock_columns_to_orders_table`. No data migration.

| Column               | Type                                                     | Notes                                                                                   |
|----------------------|----------------------------------------------------------|-----------------------------------------------------------------------------------------|
| `coupon_id`          | `foreignId` -> `coupons`, nullable, `nullOnDelete()`     | Currently applied coupon                                                                |
| `coupon_code`        | `string`, nullable                                       | Snapshot of the code, survives coupon deletion                                          |
| `discount_amount`    | `decimal(10,2)`, default `0`                             | Discount actually taken off the subtotal                                                |
| `stock_deducted_at`  | `timestamp`, nullable                                    | Set when stock was taken for this order. The guard that makes restoring stock safe.     |

Existing orders get `null`/`0` for all four. Existing paid/shipped/delivered orders therefore count as "stock never deducted" and cancelling a legacy paid order does not restore stock.

## Backend contract

### Stock

- `Product::decrementStock(int $quantity): void` is atomic (`UPDATE ... WHERE stock >= :qty`). It throws `InsufficientStockException` when it cannot take the full quantity.
- `Product::incrementStock(int $quantity): void`.
- `Order::moveTo()` keeps its signature and guard, and now also does the following **inside its existing `DB::transaction`**:
  - To `Paid`: `deductStock()` takes each item's quantity from its product and sets `stock_deducted_at = now()`. Items are processed in `product_id` order to avoid deadlocks, and quantities for the same product are summed.
  - To `Cancelled`: `restoreStock()` adds the quantities back **only if `stock_deducted_at` is not null**, then sets it to `null`. Cancelling a Pending order changes no stock.
  - If stock is short, `InsufficientStockException` propagates and the whole transaction rolls back. The status is unchanged, no history row is written and `OrderStatusChanged` is not dispatched.
- `deductStock()` and `restoreStock()` are `protected`/`private` on `Order` and called only from `moveTo()`. Stock is not handled in the queued listener, for the same reason history isn't: the listener runs outside the transaction and could leave status and stock out of sync.
- Soft-deleted products are still adjusted (`OrderItem::product()` already uses `withTrashed()`). Items with a null `product_id` are skipped.

### Coupons

- `Coupon`: add `isRedeemable(): bool` (active and not expired, same rule as the existing `active()` scope), `orders(): HasMany`, and `discountFor(string $subtotal): string` (face value capped at the subtotal, never negative). The coupon is a fixed amount, matching the existing `discount` column.
- `Order`: add `coupon(): BelongsTo`, add the four columns to `$fillable` and `casts()` (`discount_amount` as `decimal:2`, `stock_deducted_at` as `datetime`).
- `Order::applyCoupon(Coupon $coupon): void`
  - Allowed only while the order is `Pending`; throws `InvalidCouponException` otherwise, and when `! $coupon->isRedeemable()`.
  - One coupon per order: applying a second replaces the first.
  - In one `DB::transaction`: set `coupon_id` and `coupon_code`, then `recalculateTotals()`.
  - Dispatches `OrderCouponApplied` after the transaction.
- `Order::removeCoupon(): void`
  - Pending only (throws `InvalidCouponException` otherwise). No-op, and no event, when no coupon is applied.
  - Clears `coupon_id` and `coupon_code`, recalculates, dispatches `OrderCouponRemoved` after the transaction.
- `Order::recalculateTotals(): void` (signature unchanged)
  - `subtotal` = sum of `line_total`.
  - `discount_amount` = `coupon->discountFor(subtotal)` when `coupon_id` is set, else `0`.
  - `total` = `subtotal - discount_amount + shipping_fee + tax`, floor `0`.
  - Does nothing unless the order is `Pending`, so a paid order's totals can no longer drift. `CreateOrder` and `EditOrder` already call it.
- A coupon failure (unknown, inactive, expired, wrong order state) writes nothing and dispatches nothing.

### Exceptions

`App\Exceptions\InsufficientStockException` and `App\Exceptions\InvalidCouponException`, both extending `RuntimeException` (open question 4 for the folder). `InsufficientStockException` exposes the product name and the requested and available quantities for the admin message.

### Events

All in `app/Events`, `Dispatchable` + `SerializesModels`, dispatched after the transaction commits, like the existing one.

| Event                  | Payload                                    | Dispatched by             | Listeners |
|------------------------|--------------------------------------------|---------------------------|-----------|
| `OrderStatusChanged`   | `Order $order`, `OrderStatus $previousStatus` | `moveTo()` (existing, unchanged) | `SendOrderStatusNotification` (existing, unchanged) |
| `OrderCouponApplied`   | `Order $order`, `Coupon $coupon`           | `applyCoupon()`           | none      |
| `OrderCouponRemoved`   | `Order $order`, `string $couponCode`       | `removeCoupon()`          | none      |

There is no stock event; stock moves inside the `moveTo()` transaction.

### Factories and seeder

- `OrderFactory`: states `paid()` (status `Paid`, `paid_at` set, `stock_deducted_at` set) and `withCoupon(Coupon $coupon)`.
- `CouponFactory`: states `expired()` and `inactive()`.
- `DatabaseSeeder`: seeded paid/shipped/delivered orders keep `stock_deducted_at = null` (they are legacy-style), and at least one seeded order carries a coupon with a consistent `discount_amount` and `total`.

## Admin contract

All inside `app/Filament/Resources/Orders/`.

- **Table** (`Tables/OrdersTable.php`)
  - Status filter becomes `->multiple()`.
  - New date filter on `created_at` (the column already labeled "Date"): `Filter::make('created_at')` with `from` and `until` `DatePicker`s, inclusive (`whereDate`), with indicators.
- **View page** (`Pages/ViewOrder.php` + `Schemas/OrderInfolist.php`), sections in this order:
  1. *Order*: order number, status badge, created/paid/shipped/cancelled dates.
  2. *Customer*: `user.name`, `user.email`, shipping name/address/city/postal code/country.
  3. *Items*: `RepeatableEntry` over `items`: product name, quantity, price, line total.
  4. *Coupon*: `coupon_code` and `discount_amount`. Hidden when no coupon is applied.
  5. *Totals*: subtotal, discount, shipping fee, tax, total, all `money('PHP')`.
  6. *Status history*: the existing `StatusHistoriesRelationManager`, which the View page already renders below the infolist. No duplicate entry.
  7. *Comments*: existing, unchanged.
  - Eager load `user`, `items`, `coupon` in `OrderResource::getEloquentQuery()` to avoid N+1.
- **Coupon actions** (new `Actions/OrderCouponActions::make()`, added to `ViewOrder` header actions)
  - `applyCoupon`: modal with a required `Select` of `Coupon::active()` by `code`; visible only when `Pending`; calls `$record->applyCoupon()`.
  - `removeCoupon`: confirmable; visible only when `Pending` and `coupon_code` is set; calls `$record->removeCoupon()`.
  - `InvalidCouponException` becomes a danger notification, not a 500.
- **`Actions/OrderTransitionActions.php`**: catch `InsufficientStockException` around `moveTo()` and show a danger notification naming the product and available stock. The status stays as it was.
- **`Schemas/OrderForm.php`**: the `items` repeater is disabled when the order is not `Pending`, matching the backend rule that totals are frozen after Pending. The status field is already disabled and not dehydrated.
- Do not touch `app/Models/`, `database/`, `tests/` or Fortify/auth files.

## Test contract

Pest, in-memory SQLite, factories only, no database refresh. The agent writes new files and edits an existing test only when this contract deliberately changes its behavior (and reports each such edit).

- `tests/Feature/OrderStockTest.php`
  - paying an order takes each item's quantity, sums duplicate products, and sets `stock_deducted_at`
  - insufficient stock throws, and the status, stock, history and event are all untouched
  - cancelling a paid order restores stock and clears `stock_deducted_at`
  - cancelling a pending order changes no stock
  - cancelling a legacy paid order (`stock_deducted_at` null) changes no stock
  - a soft-deleted product is still adjusted
- `tests/Feature/OrderCouponTest.php`
  - applying a coupon sets code and discount and recalculates the total
  - the discount is capped at the subtotal and the total never goes below 0
  - an expired, inactive or non-redeemable coupon throws and changes nothing
  - applying to a non-Pending order throws
  - applying a second coupon replaces the first
  - removing a coupon restores the total, and is a silent no-op with no coupon
  - `OrderCouponApplied`/`OrderCouponRemoved` dispatch on success and not on failure
  - deleting a coupon keeps `coupon_code` on the order
  - `recalculateTotals()` is a no-op once the order is not Pending
- `tests/Feature/OrderViewPageTest.php`
  - renders customer, items, totals and the status history
  - the coupon section is shown with a coupon and hidden without one
  - the apply and remove coupon actions work and respect visibility by status
  - a transition action with insufficient stock shows the notification and leaves the order unchanged
- `tests/Feature/OrderTableFilterTest.php`
  - the status filter (single and multiple)
  - the date filter: from only, until only, both, and boundary days inclusive
  - the two filters combined

## Folder ownership

| Agent       | Owns                                                                                                                                                    | Must not touch                                  |
|-------------|---------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------|
| **Backend** | `database/migrations/` (the one new file), `app/Models/` (`Order`, `Product`, `Coupon`), `app/Events/`, `app/Exceptions/`, `database/factories/`, `database/seeders/` | `app/Filament/`, `tests/`, Fortify/auth files   |
| **Admin**   | `app/Filament/Resources/Orders/` (`Actions/`, `Pages/`, `Schemas/`, `Tables/`, `RelationManagers/`, `OrderResource.php`)                                 | `app/Models/`, `app/Events/`, `database/`, `tests/` |
| **Test**    | `tests/Feature/`                                                                                                                                        | all application code                            |

Integration: after the base commit, merge Backend, then Admin, then Test, one at a time. Run `php artisan test --compact` and `vendor/bin/pint --dirty --format agent` after each merge, and report every diff and result.

## Open questions

1. **Stock shortfall at payment.** Proposal: block the payment (exception, nothing changes). The alternative is allowing negative stock, which I don't recommend. Okay?
2. **Coupon type.** The `coupons` table has only a fixed `discount` amount, so percentage coupons and per-coupon usage limits are out of scope. Okay?
3. **Legacy paid orders.** They count as "stock not deducted", so cancelling one restores nothing. The alternative is to assume they were deducted, which would inflate stock. Okay?
4. **`app/Exceptions/` is a new folder.** Needed for the two exceptions. Approve, or put them elsewhere?
5. **Freezing after Pending.** Items and totals become read-only once an order leaves `Pending` (form repeater disabled, `recalculateTotals()` no-op). Okay?
6. **Carried over from the earlier session, still unresolved.** (a) `composer.json`/`composer.lock` carry a `platform.php` pin and about 1,000 lines of lock changes that are not part of any plan. (b) `2026_11_08_070027_backfill_order_status_histories` is a data migration that has already run, which the earlier plan said not to add. Decide both before the base commit.
7. **Date filter column.** I assumed `created_at`, since that is what the "Date" column shows. Use `placed_at` instead?

<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Category name => [min price, max price, product names].
     *
     * @var array<string, array{0: int, 1: int, 2: array<int, string>}>
     */
    private const CATALOG = [
        'Electronics' => [25, 1200, [
            'Wireless Earbuds', 'Bluetooth Speaker', '27-inch Monitor', 'Mechanical Keyboard', 'Wireless Mouse',
            'USB-C Hub', 'Portable Charger', 'Smartwatch', 'Webcam 1080p', 'Noise-Cancelling Headphones',
        ]],
        'Clothing' => [12, 150, [
            'Cotton T-Shirt', 'Slim Fit Jeans', 'Hooded Sweatshirt', 'Running Shorts', 'Rain Jacket',
            'Wool Socks (3-pack)', 'Leather Belt', 'Baseball Cap', 'Fleece Vest', 'Canvas Sneakers',
        ]],
        'Home & Kitchen' => [8, 250, [
            'Chef Knife', 'Non-Stick Frying Pan', 'Electric Kettle', 'Coffee Grinder', 'Bamboo Cutting Board',
            'Glass Food Containers', 'Bath Towel Set', 'Memory Foam Pillow', 'LED Desk Lamp', 'Air Fryer',
        ]],
        'Books' => [6, 60, [
            'Clean Code', 'The Pragmatic Programmer', 'Atomic Habits', 'Deep Work', 'Designing Data-Intensive Applications',
            'The Hobbit', 'Sapiens', 'Thinking, Fast and Slow', 'The Design of Everyday Things', 'Dune',
        ]],
        'Sports & Outdoors' => [10, 300, [
            'Yoga Mat', 'Adjustable Dumbbells', 'Insulated Water Bottle', 'Camping Tent (2-person)', 'Trekking Poles',
            'Resistance Bands', 'Jump Rope', 'Hiking Backpack 40L', 'Cycling Helmet', 'Foam Roller',
        ]],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        collect(self::CATALOG)->each(function (array $definition, string $name): void {
            [$minPrice, $maxPrice, $productNames] = $definition;

            $category = Category::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);

            foreach ($productNames as $productName) {
                Product::factory()->for($category)->create([
                    'name' => $productName,
                    'slug' => Str::slug($productName),
                    'price' => fake()->numberBetween($minPrice, $maxPrice) + fake()->randomElement([0, 0.49, 0.99]),
                    'stock' => fake()->numberBetween(5, 200),
                ]);
            }
        });

        $products = Product::all();

        User::factory(3)->create()->each(function (User $customer) use ($products): void {
            foreach (range(1, fake()->numberBetween(2, 4)) as $unused) {
                $this->createOrder($customer, $products->random(fake()->numberBetween(1, 5)));
            }
        });

        Shipment::factory(15)->recycle(Order::all())->create();

        Coupon::factory(20)->create();

        Supplier::factory(10)->create();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function createOrder(User $customer, $products): void
    {
        $placedAt = fake()->dateTimeBetween('-90 days', 'now');

        $order = Order::factory()->for($customer)->create([
            'status' => fake()->randomElement(OrderStatus::cases()),
            'shipping_name' => $customer->name,
            'placed_at' => $placedAt,
            'created_at' => $placedAt,
        ]);

        $order->statusHistories()->create([
            'from_status' => null,
            'to_status' => $order->status,
            'changed_by' => null,
            'created_at' => $placedAt,
        ]);

        $totalInCents = 0;

        foreach ($products as $product) {
            $quantity = fake()->numberBetween(1, 3);
            $lineTotalInCents = (int) round($product->price * 100) * $quantity;
            $totalInCents += $lineTotalInCents;

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'price' => $product->price,
                'line_total' => $lineTotalInCents / 100,
            ]);
        }

        $order->update([
            'subtotal' => $totalInCents / 100,
            'total' => $totalInCents / 100,
        ]);
    }
}

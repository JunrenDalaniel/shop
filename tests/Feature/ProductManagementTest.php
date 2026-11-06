<?php

use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guests are redirected to login', function () {
    auth()->logout();

    $this->get(route('products.index'))->assertRedirect(route('login'));
});

test('products page lists products with their stock status', function () {
    Product::factory()->create(['name' => 'Wireless Earbuds', 'stock' => 42]);
    Product::factory()->create(['name' => 'Rain Jacket', 'stock' => 6]);
    Product::factory()->create(['name' => 'Water Bottle', 'stock' => 0]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('Wireless Earbuds')
        ->assertSee('In Stock')
        ->assertSee('Low Stock')
        ->assertSee('Out of Stock');
});

test('stock status is derived from the stock level', function () {
    expect(StockStatus::fromStock(0))->toBe(StockStatus::OutOfStock)
        ->and(StockStatus::fromStock(19))->toBe(StockStatus::LowStock)
        ->and(StockStatus::fromStock(20))->toBe(StockStatus::InStock);
});

test('products can be searched, filtered by category and by availability', function () {
    $books = Category::factory()->create();
    Product::factory()->for($books)->create(['name' => 'Atlas', 'stock' => 30]);
    Product::factory()->create(['name' => 'Earbuds', 'stock' => 3]);

    Livewire::test('pages::products.index')
        ->set('search', 'atl')
        ->assertSee('Atlas')
        ->assertDontSee('Earbuds')
        ->set('search', '')
        ->set('categoryId', (string) $books->id)
        ->assertSee('Atlas')
        ->assertDontSee('Earbuds')
        ->set('categoryId', '')
        ->set('availability', StockStatus::LowStock->value)
        ->assertSee('Earbuds')
        ->assertDontSee('Atlas');
});

test('products can be sorted by price and sorting ignores unknown columns', function () {
    Product::factory()->create(['name' => 'Cheap', 'price' => 10]);
    Product::factory()->create(['name' => 'Pricey', 'price' => 900]);

    Livewire::test('pages::products.index')
        ->call('sort', 'price')
        ->call('sort', 'price')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Pricey', 'Cheap'])
        ->call('sort', 'password')
        ->assertSet('sortBy', 'price');
});

test('a product can be created', function () {
    $category = Category::factory()->create();

    Livewire::test('pages::products.index')
        ->set('name', 'Trail Backpack')
        ->set('description', 'Day pack')
        ->set('category_id', (string) $category->id)
        ->set('price', '1799.50')
        ->set('stock', '19')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Trail Backpack')->first();

    expect($product)->not->toBeNull()
        ->and($product->price)->toBe('1799.50')
        ->and($product->stock)->toBe(19)
        ->and($product->sku)->not->toBeNull()
        ->and($product->slug)->not->toBeNull();
});

test('product validation rejects bad input', function () {
    Livewire::test('pages::products.index')
        ->set('name', '')
        ->set('category_id', '999')
        ->set('price', '-5')
        ->set('stock', '1.5')
        ->call('save')
        ->assertHasErrors(['name', 'category_id', 'price', 'stock']);

    expect(Product::count())->toBe(0);
});

test('a product can be edited', function () {
    $product = Product::factory()->create(['name' => 'Old Name', 'stock' => 5]);

    Livewire::test('pages::products.index')
        ->call('edit', $product->id)
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->set('stock', '50')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh())->name->toBe('New Name')->stock->toBe(50);
});

test('a product can be deleted and keeps its order history', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::products.index')
        ->call('confirmDelete', $product->id)
        ->call('delete');

    expect(Product::count())->toBe(0)
        ->and(Product::withTrashed()->count())->toBe(1);
});

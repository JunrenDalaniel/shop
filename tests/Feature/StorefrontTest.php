<?php

use App\Models\Category;
use App\Models\Product;
use Livewire\Livewire;

test('guests can see categories and products on the homepage', function () {
    $category = Category::factory()->create(['name' => 'Books', 'slug' => 'books']);
    Product::factory()->for($category)->create(['name' => 'Clean Code', 'price' => 650, 'stock' => 5]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Shop by category')
        ->assertSee('Books')
        ->assertSee('Clean Code')
        ->assertSee('₱650.00')
        ->assertSee('Only 5 left');
});

test('inactive and soft deleted products are not shown', function () {
    Product::factory()->create(['name' => 'Hidden Item', 'is_active' => false]);
    Product::factory()->create(['name' => 'Gone Item'])->delete();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Hidden Item')
        ->assertDontSee('Gone Item');
});

test('searching filters the products shown', function () {
    Product::factory()->create(['name' => 'Yoga Mat']);
    Product::factory()->create(['name' => 'Camping Tent']);

    Livewire::test('pages::storefront')
        ->set('search', 'yoga')
        ->assertSee('Yoga Mat')
        ->assertDontSee('Camping Tent');
});

test('adding to the cart increases the cart count but never beyond stock', function () {
    $product = Product::factory()->create(['stock' => 2]);

    Livewire::test('pages::storefront')
        ->call('addToCart', $product->id)
        ->call('addToCart', $product->id)
        ->call('addToCart', $product->id)
        ->assertSet('cart', [$product->id => 2])
        ->assertSeeHtml('data-test="cart-count">2<');
});

test('sold out products cannot be added to the cart', function () {
    $product = Product::factory()->create(['stock' => 0]);

    Livewire::test('pages::storefront')
        ->call('addToCart', $product->id)
        ->assertSet('cart', [])
        ->assertSee('Sold out');
});

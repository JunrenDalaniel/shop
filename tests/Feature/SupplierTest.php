<?php

use App\Models\Product;
use App\Models\Supplier;

test('a supplier has many products', function () {
    $supplier = Supplier::factory()->create();
    Product::factory()->count(3)->create(['supplier_id' => $supplier->id]);

    expect($supplier->products)->toHaveCount(3)
        ->and($supplier->products->first()->supplier->is($supplier))->toBeTrue();
});

test('deleting a supplier keeps its products', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);

    $supplier->delete();

    expect($product->fresh()->supplier_id)->toBeNull();
});

<?php

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

test('suppliers are listed with product counts and searchable by name', function () {
    $match = Supplier::factory()->create(['name' => 'Acme Trading']);
    $other = Supplier::factory()->create(['name' => 'Zeta Goods']);
    Product::factory()->count(2)->create(['supplier_id' => $match->id]);

    Livewire::test(ListSuppliers::class)
        ->assertOk()
        ->assertTableColumnStateSet('products_count', 2, $match)
        ->searchTable('Acme')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

test('a supplier can be created', function () {
    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Acme', 'email' => 'sales@acme.test', 'phone' => '0917 123 4567'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Supplier::where('email', 'sales@acme.test')->exists())->toBeTrue();
});

test('supplier validation requires a name and a unique email', function () {
    Supplier::factory()->create(['email' => 'taken@acme.test']);

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => '', 'email' => 'taken@acme.test'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique']);
});

test('a supplier can be edited without changing its email', function () {
    $supplier = Supplier::factory()->create(['email' => 'keep@acme.test']);

    Livewire::test(EditSupplier::class, ['record' => $supplier->getKey()])
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($supplier->fresh())->name->toBe('Renamed')->email->toBe('keep@acme.test');
});

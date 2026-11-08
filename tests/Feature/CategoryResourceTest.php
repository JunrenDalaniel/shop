<?php

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Categories\RelationManagers\ProductsRelationManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

test('categories are listed with their product counts', function () {
    $category = Category::factory()->create(['name' => 'Books']);
    Product::factory()->count(2)->for($category)->create();

    Livewire::test(ListCategories::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$category])
        ->assertTableColumnStateSet('products_count', 2, $category);
});

test('a category can be created and its slug is generated from the name', function () {
    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'Outdoor Gear'])
        ->assertSchemaStateSet(['slug' => 'outdoor-gear'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::where('slug', 'outdoor-gear')->exists())->toBeTrue();
});

test('category validation requires a name and a unique slug', function () {
    Category::factory()->create(['slug' => 'taken']);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => '', 'slug' => 'taken'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'Other', 'slug' => 'taken'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

test('a category can be edited without changing its slug', function () {
    $category = Category::factory()->create(['name' => 'Old', 'slug' => 'old']);

    Livewire::test(EditCategory::class, ['record' => $category->getKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->fresh())->name->toBe('New Name')->slug->toBe('old');
});

test('a category can be deleted', function () {
    $category = Category::factory()->create();

    Livewire::test(EditCategory::class, ['record' => $category->getKey()])
        ->callAction(DeleteAction::class);

    expect(Category::count())->toBe(0);
});

test('deleting a category keeps its products as uncategorized', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['name' => 'Orphan Lamp']);

    Livewire::test(EditCategory::class, ['record' => $category->getKey()])
        ->callAction(DeleteAction::class);

    expect($product->fresh()->category_id)->toBeNull();

    $this->get(route('products.index'))->assertOk()->assertSee('Orphan Lamp')->assertSee('Uncategorized');
    $this->get(route('home'))->assertOk();
});

test('the products relation manager lists only the category products', function () {
    $category = Category::factory()->create();
    $mine = Product::factory()->for($category)->create();
    $other = Product::factory()->create();

    Livewire::test(ProductsRelationManager::class, [
        'ownerRecord' => $category,
        'pageClass' => EditCategory::class,
    ])
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
});

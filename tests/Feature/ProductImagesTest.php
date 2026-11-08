<?php

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->superAdmin()->create());
});

function productFormData(array $images): array
{
    return [
        'name' => 'Camera',
        'price' => 100,
        'stock' => 5,
        'images' => $images,
    ];
}

test('product images are stored in the products media collection', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(productFormData([UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::firstWhere('name', 'Camera')->getMedia('products'))->toHaveCount(2);
});

test('uploaded images are stored on the public disk so the storefront can serve them', function () {
    config(['filesystems.default' => 'local', 'filament.default_filesystem_disk' => 'local']);

    Livewire::test(CreateProduct::class)
        ->fillForm(productFormData([UploadedFile::fake()->image('a.jpg')]))
        ->call('create')
        ->assertHasNoFormErrors();

    $media = Product::firstWhere('name', 'Camera')->getFirstMedia('products');

    expect($media->disk)->toBe('public')
        ->and($media->conversions_disk)->toBe('public');
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
});

test('no more than five images can be uploaded', function () {
    $images = collect(range(1, 6))->map(fn (int $i) => UploadedFile::fake()->image("img{$i}.jpg"))->all();

    Livewire::test(CreateProduct::class)
        ->fillForm(productFormData($images))
        ->call('create')
        ->assertHasFormErrors(['images']);
});

test('only jpg, jpeg and png files are accepted', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(productFormData([UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]))
        ->call('create')
        ->assertHasFormErrors(['images']);

    Livewire::test(CreateProduct::class)
        ->fillForm(productFormData([UploadedFile::fake()->image('anim.gif')]))
        ->call('create')
        ->assertHasFormErrors(['images']);
});

test('the upload field caps file size at 500 MB and the Livewire upload limit allows it', function () {
    Livewire::test(CreateProduct::class)
        ->assertFormFieldExists('images', fn (SpatieMediaLibraryFileUpload $field) => $field->getMaxSize() === 512000
            && $field->getMaxFiles() === 5);

    expect(config('livewire.temporary_file_upload.rules'))->toContain('max:512000');
});

test('the products table shows the image column', function () {
    $product = Product::factory()->create();

    Livewire::test(ListProducts::class)
        ->assertOk()
        ->assertTableColumnExists('images');
});

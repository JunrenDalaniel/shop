<?php

use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Products')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'category')]
    public string $categoryId = '';

    #[Url(as: 'availability')]
    public string $availability = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public int $perPage = 10;

    public ?int $productId = null;

    public string $name = '';

    public string $description = '';

    public string $category_id = '';

    public string $price = '';

    public string $stock = '';

    /**
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['name', 'price', 'stock'];

    /**
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [5, 10, 25];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedAvailability(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return Product::query()
            ->with('category')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->when(
                StockStatus::tryFrom($this->availability),
                fn ($query, StockStatus $status) => $query->withStockStatus($status),
            )
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }

    #[Computed]
    public function selectedProduct(): ?Product
    {
        return $this->productId ? Product::with('category')->find($this->productId) : null;
    }

    public function create(): void
    {
        $this->resetForm();

        Flux::modal('product-form')->show();
    }

    public function showDetails(int $id): void
    {
        $this->productId = Product::findOrFail($id)->id;

        Flux::modal('product-view')->show();
    }

    public function edit(int $id): void
    {
        $product = Product::findOrFail($id);

        $this->resetValidation();
        $this->productId = $product->id;
        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->category_id = (string) $product->category_id;
        $this->price = (string) $product->price;
        $this->stock = (string) $product->stock;

        Flux::modal('product-view')->close();
        Flux::modal('product-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'category_id' => (int) $validated['category_id'],
            'price' => $validated['price'],
            'stock' => (int) $validated['stock'],
        ];

        if ($this->productId) {
            Product::findOrFail($this->productId)->update($attributes);
        } else {
            Product::create($attributes + [
                'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
                'sku' => 'SKU-'.Str::upper(Str::random(8)),
            ]);
        }

        Flux::modal('product-form')->close();
        Flux::toast(variant: 'success', text: $this->productId ? __('Product updated.') : __('Product added.'));

        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->productId = Product::findOrFail($id)->id;

        Flux::modal('product-delete')->show();
    }

    public function delete(): void
    {
        Product::findOrFail($this->productId)->delete();

        Flux::modal('product-delete')->close();
        Flux::toast(variant: 'success', text: __('Product deleted.'));

        $this->productId = null;
    }

    private function resetForm(): void
    {
        $this->reset('productId', 'name', 'description', 'category_id', 'price', 'stock');
        $this->resetValidation();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Products') }}</flux:heading>
            <flux:subheading>{{ __('Manage and monitor your product inventory.') }}</flux:subheading>
        </div>

        <flux:button wire:click="create" icon="plus" class="bg-emerald-500! text-emerald-950! hover:bg-emerald-400!" data-test="add-product-button">
            {{ __('Add Product') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-64 max-w-md flex-1">
            <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" :placeholder="__('Search products by name')" :aria-label="__('Search products by name')" />
        </div>

        <div class="w-48">
            <flux:select wire:model.live="categoryId" :label="__('Category')">
                <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-44">
            <flux:select wire:model.live="availability" :label="__('Availability')">
                <flux:select.option value="">{{ __('All stock levels') }}</flux:select.option>
                @foreach (StockStatus::cases() as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:text class="ms-auto" aria-live="polite">
            {{ trans_choice(':count product|:count products', $this->products->total()) }}
        </flux:text>
    </div>

    @if ($this->products->isEmpty())
        <div class="rounded-xl border border-zinc-200 p-10 text-center dark:border-zinc-700" role="status">
            <flux:heading>{{ __('No products match your filters') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Try a different search or clear a filter.') }}</flux:text>
        </div>
    @else
        <div class="hidden md:block">
            <flux:table :paginate="$this->products">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Product Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column align="end" sortable :sorted="$sortBy === 'price'" :direction="$sortDirection" wire:click="sort('price')">{{ __('Price') }}</flux:table.column>
                    <flux:table.column align="end" sortable :sorted="$sortBy === 'stock'" :direction="$sortDirection" wire:click="sort('stock')">{{ __('Stock') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->products as $product)
                        <flux:table.row :key="$product->id">
                            <flux:table.cell variant="strong">{{ $product->name }}</flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate">{{ $product->description }}</flux:table.cell>
                            <flux:table.cell>{{ $product->category?->name ?? __('Uncategorized') }}</flux:table.cell>
                            <flux:table.cell align="end">₱{{ number_format($product->price, 2) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $product->stock }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$product->stockStatus()->color()" inset="top bottom">{{ $product->stockStatus()->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="showDetails({{ $product->id }})" :aria-label="__('View :name', ['name' => $product->name])" />
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $product->id }})" :aria-label="__('Edit :name', ['name' => $product->name])" />
                                    <flux:button size="sm" variant="ghost" icon="trash" class="hover:text-red-400!" wire:click="confirmDelete({{ $product->id }})" :aria-label="__('Delete :name', ['name' => $product->name])" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="flex flex-col gap-3 md:hidden">
            @foreach ($this->products as $product)
                <article wire:key="card-{{ $product->id }}" class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading>{{ $product->name }}</flux:heading>
                        <flux:badge size="sm" :color="$product->stockStatus()->color()">{{ $product->stockStatus()->label() }}</flux:badge>
                    </div>
                    <flux:text>{{ $product->description }}</flux:text>
                    <div class="flex flex-wrap justify-between gap-2 text-sm">
                        <flux:text>{{ $product->category?->name ?? __('Uncategorized') }}</flux:text>
                        <span><strong>₱{{ number_format($product->price, 2) }}</strong> · {{ __(':count in stock', ['count' => $product->stock]) }}</span>
                    </div>
                    <div class="flex gap-2">
                        <flux:button size="sm" class="flex-1" wire:click="showDetails({{ $product->id }})">{{ __('View') }}</flux:button>
                        <flux:button size="sm" class="flex-1" wire:click="edit({{ $product->id }})">{{ __('Edit') }}</flux:button>
                        <flux:button size="sm" class="flex-1" variant="danger" wire:click="confirmDelete({{ $product->id }})">{{ __('Delete') }}</flux:button>
                    </div>
                </article>
            @endforeach

            {{ $this->products->links() }}
        </div>
    @endif

    <div class="flex items-center gap-3">
        <flux:text>{{ __('Rows per page') }}</flux:text>
        <div class="w-24">
            <flux:select wire:model.live="perPage" :aria-label="__('Rows per page')">
                <flux:select.option value="5">5</flux:select.option>
                <flux:select.option value="10">10</flux:select.option>
                <flux:select.option value="25">25</flux:select.option>
            </flux:select>
        </div>
    </div>

    <flux:modal name="product-view" class="w-full max-w-lg">
        @if ($this->selectedProduct)
            <div class="space-y-6">
                <div class="flex items-start justify-between gap-3 pe-8">
                    <flux:heading size="lg">{{ $this->selectedProduct->name }}</flux:heading>
                    <flux:badge size="sm" :color="$this->selectedProduct->stockStatus()->color()">{{ $this->selectedProduct->stockStatus()->label() }}</flux:badge>
                </div>

                <flux:text>{{ $this->selectedProduct->description }}</flux:text>

                <dl class="grid grid-cols-3 gap-4 rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Category') }}</dt>
                        <dd class="mt-1">{{ $this->selectedProduct->category?->name ?? __('Uncategorized') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Price') }}</dt>
                        <dd class="mt-1">₱{{ number_format($this->selectedProduct->price, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Stock') }}</dt>
                        <dd class="mt-1">{{ __(':count units', ['count' => $this->selectedProduct->stock]) }}</dd>
                    </div>
                </dl>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="edit({{ $this->selectedProduct->id }})" class="bg-emerald-500! text-emerald-950! hover:bg-emerald-400!">{{ __('Edit product') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="product-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $productId ? __('Edit product') : __('Add product') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Product name')" required />
            <flux:input wire:model="description" :label="__('Description')" />

            <flux:select wire:model="category_id" :label="__('Category')" required>
                <flux:select.option value="">{{ __('Select a category') }}</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="price" :label="__('Price (₱)')" type="number" min="0" step="0.01" required />
                <flux:input wire:model="stock" :label="__('Stock')" type="number" min="0" step="1" required />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" class="bg-emerald-500! text-emerald-950! hover:bg-emerald-400!" data-test="save-product-button">{{ __('Save product') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="product-delete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this product?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __(':name will be removed from your inventory. Past orders keep their record of it.', ['name' => $this->selectedProduct?->name]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete" data-test="confirm-delete-product-button">{{ __('Delete product') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

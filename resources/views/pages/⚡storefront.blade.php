<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::shop'), Title('Home')] class extends Component {
    public string $search = '';

    /**
     * Product id => quantity.
     *
     * @var array<int, int>
     */
    #[Session]
    public array $cart = [];

    public function addToCart(int $productId): void
    {
        $product = Product::query()->active()->findOrFail($productId);

        $quantity = $this->cart[$product->id] ?? 0;

        if ($quantity >= $product->stock) {
            return;
        }

        $this->cart[$product->id] = $quantity + 1;
    }

    #[Computed]
    public function cartCount(): int
    {
        return array_sum($this->cart);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->withCount(['products' => fn ($query) => $query->active()])
            ->with(['products' => fn ($query) => $query
                ->active()
                ->when(trim($this->search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
                ->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function featuredProducts(): Collection
    {
        return Product::query()
            ->active()
            ->with('category')
            ->where('stock', '>', 0)
            ->latest('id')
            ->limit(5)
            ->get();
    }

    public function iconFor(string $slug): string
    {
        return match ($slug) {
            'electronics' => 'computer-desktop',
            'clothing' => 'shopping-bag',
            'home-kitchen' => 'home',
            'books' => 'book-open',
            'sports-outdoors' => 'trophy',
            default => 'tag',
        };
    }
}; ?>

<div>
    <header class="border-b border-stone-200">
        <div class="mx-auto max-w-[1200px] px-6">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-4 py-4">
                <a href="{{ route('home') }}" class="order-1 mr-auto flex min-h-12 items-center gap-2.5 sm:mr-0" aria-label="{{ __(':app home', ['app' => config('app.name')]) }}">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-orange-700 text-white">
                        <flux:icon name="shopping-bag" class="size-6" />
                    </span>
                    <span class="text-2xl font-bold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <form role="search" wire:submit.prevent class="order-4 flex min-w-0 basis-full sm:order-2 sm:flex-1 sm:basis-[360px]">
                    <label class="sr-only" for="search">{{ __('Search products') }}</label>
                    <input
                        id="search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Search for products') }}"
                        class="h-12 min-w-0 flex-1 rounded-s-[10px] border-[1.5px] border-e-0 border-stone-500 bg-white px-4 text-base text-neutral-900"
                    >
                    <button type="submit" aria-label="{{ __('Search') }}" class="flex min-w-14 items-center justify-center rounded-e-[10px] bg-orange-700 px-4 text-white hover:bg-orange-800">
                        <flux:icon name="magnifying-glass" class="size-5" />
                    </button>
                </form>

                <button type="button" aria-label="{{ trans_choice('Cart, :count item|Cart, :count items', $this->cartCount) }}" class="relative order-3 flex size-12 items-center justify-center rounded-[10px] border-[1.5px] border-stone-500 bg-white">
                    <flux:icon name="shopping-cart" class="size-6" />
                    <span class="absolute -top-2 -right-2 flex h-[22px] min-w-[22px] items-center justify-center rounded-full bg-orange-700 px-1.5 text-xs font-semibold text-white" data-test="cart-count">{{ $this->cartCount }}</span>
                </button>
            </div>

            <nav aria-label="{{ __('Categories') }}" class="flex gap-1 overflow-x-auto pb-2">
                @foreach ($this->categories as $category)
                    <a href="#{{ $category->slug }}" class="flex min-h-11 items-center rounded-lg px-3.5 text-[15px] font-medium whitespace-nowrap hover:bg-stone-100">{{ $category->name }}</a>
                @endforeach

                <span class="ms-auto"></span>

                @auth
                    <a href="{{ route('products.index') }}" class="flex min-h-11 items-center rounded-lg px-3.5 text-[15px] font-medium whitespace-nowrap hover:bg-stone-100">{{ __('Manage products') }}</a>
                @else
                    <a href="{{ route('login') }}" class="flex min-h-11 items-center rounded-lg px-3.5 text-[15px] font-medium whitespace-nowrap hover:bg-stone-100">{{ __('Log in') }}</a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        <section class="bg-orange-700 text-white">
            <div class="mx-auto max-w-[1200px] px-6">
                <div class="flex flex-wrap items-center gap-10 py-10 sm:py-[72px]">
                    <div class="flex basis-[380px] flex-1 flex-col items-start gap-5">
                        <h1 class="text-[clamp(38px,5.4vw,64px)] leading-[1.04] font-bold tracking-tighter text-balance">{{ __('Everything you need, one wise stop.') }}</h1>
                        <p class="max-w-[460px] text-lg leading-normal text-pretty">{{ __('Electronics, clothing, home essentials, books and outdoor gear, all in one store.') }}</p>
                        <a href="#featured" class="mt-1 inline-flex min-h-[54px] items-center justify-center rounded-[10px] bg-white px-8 text-base font-semibold text-neutral-900 hover:brightness-90">{{ __('Shop Now') }}</a>
                    </div>

                    <div class="flex min-h-[300px] basis-[320px] flex-1 flex-col items-center justify-center gap-5 rounded-[20px] border-2 border-dashed border-white/65 bg-white/12 p-6">
                        <div class="flex flex-wrap justify-center gap-5">
                            @foreach ($this->categories as $category)
                                <flux:icon :name="$this->iconFor($category->slug)" class="size-11 text-white" />
                            @endforeach
                        </div>
                        <span class="text-sm font-medium">{{ __('Hero image placeholder') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-10 pb-6 sm:pt-16" aria-labelledby="category-heading">
            <div class="mx-auto max-w-[1200px] px-6">
                <h2 id="category-heading" class="mb-6 text-3xl font-bold tracking-tight">{{ __('Shop by category') }}</h2>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fit,minmax(190px,1fr))] sm:gap-4">
                    @foreach ($this->categories as $category)
                        <a href="#{{ $category->slug }}" wire:key="tile-{{ $category->id }}" class="flex min-h-[150px] flex-col items-start gap-1.5 rounded-[14px] border border-stone-200 bg-stone-50 p-5 last:col-span-2 hover:border-orange-700 sm:last:col-span-1">
                            <span class="mb-2 flex size-12 items-center justify-center rounded-xl bg-orange-700 text-white">
                                <flux:icon :name="$this->iconFor($category->slug)" class="size-6" />
                            </span>
                            <span class="text-xl font-semibold tracking-tight">{{ $category->name }}</span>
                            <span class="text-sm text-neutral-600">{{ trans_choice(':count product|:count products', $category->products_count) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        @if (trim($search) === '')
            <section id="featured" class="mt-10 bg-stone-50 py-10 sm:py-16" aria-labelledby="featured-heading">
                <div class="mx-auto max-w-[1200px] px-6">
                    <h2 id="featured-heading" class="mb-6 text-3xl font-bold tracking-tight">{{ __('Featured Products') }}</h2>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fit,minmax(200px,1fr))] sm:gap-5">
                        @foreach ($this->featuredProducts as $product)
                            <x-product-card wire:key="featured-{{ $product->id }}" :product="$product" :icon="$this->iconFor($product->category?->slug ?? '')" :category-name="$product->category?->name" />
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @forelse ($this->categories->filter(fn ($category) => $category->products->isNotEmpty()) as $category)
            <section id="{{ $category->slug }}" class="pt-10 sm:pt-16" aria-label="{{ $category->name }}" wire:key="section-{{ $category->id }}">
                <div class="mx-auto max-w-[1200px] px-6">
                    <h2 class="mb-6 border-t border-stone-200 pt-6 text-3xl font-bold tracking-tight">{{ $category->name }}</h2>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fit,minmax(200px,1fr))] sm:gap-5">
                        @foreach ($category->products as $product)
                            <x-product-card wire:key="product-{{ $product->id }}" :product="$product" :icon="$this->iconFor($category->slug)" />
                        @endforeach
                    </div>
                </div>
            </section>
        @empty
            <section class="mx-auto max-w-[1200px] px-6 py-16 text-center" role="status">
                <p class="text-lg font-semibold">{{ __('No products match your search.') }}</p>
            </section>
        @endforelse
    </main>

    <footer class="mt-20 bg-neutral-900 text-neutral-200">
        <div class="mx-auto max-w-[1200px] px-6 pt-14 pb-10">
            <div class="grid gap-10 sm:grid-cols-[repeat(auto-fit,minmax(230px,1fr))]">
                <div class="flex flex-col gap-3">
                    <span class="text-2xl font-bold text-white">{{ config('app.name') }}</span>
                    <p class="max-w-[300px] text-[15px] leading-relaxed text-neutral-300">{{ __('A general store for electronics, clothing, home and kitchen, books, and sports and outdoor gear.') }}</p>
                </div>

                <div class="flex flex-col gap-2.5 text-[15px] text-neutral-300">
                    <h3 class="mb-1 text-sm font-semibold tracking-wider text-white uppercase">{{ __('Contact us') }}</h3>
                    <p>{{ __('Email: [YOUR EMAIL]') }}</p>
                    <p>{{ __('Phone: [YOUR PHONE NUMBER]') }}</p>
                    <p>{{ __('Address: [YOUR STORE ADDRESS]') }}</p>
                    <p>{{ __('Hours: [YOUR OPENING HOURS]') }}</p>
                </div>

                <div class="flex flex-col gap-3">
                    <h3 class="mb-1 text-sm font-semibold tracking-wider text-white uppercase">{{ __('We accept') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['GCash', 'Maya', 'Credit / Debit Card', 'Bank Transfer', 'Cash on Delivery'] as $method)
                            <span class="inline-flex min-h-9 items-center rounded-lg bg-neutral-800 px-3 text-sm">{{ $method }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <h3 class="mb-1 text-sm font-semibold tracking-wider text-white uppercase">{{ __('Follow us') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['Facebook', 'Instagram', 'TikTok', 'YouTube'] as $network)
                            <a href="#" class="inline-flex min-h-11 items-center rounded-full border border-neutral-600 px-4 text-sm font-medium hover:bg-neutral-800">{{ $network }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            <p class="mt-10 border-t border-neutral-700 pt-6 text-sm text-neutral-300">© {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
        </div>
    </footer>
</div>

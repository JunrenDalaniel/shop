@props(['product', 'icon' => 'tag', 'categoryName' => null])

@php
    $status = $product->stockStatus();
    $isOut = $product->stock <= 0;
    $statusClasses = match ($status) {
        \App\Enums\StockStatus::OutOfStock => 'text-neutral-600',
        \App\Enums\StockStatus::LowStock => 'text-red-700',
        default => 'text-green-700',
    };
    $dotClasses = match ($status) {
        \App\Enums\StockStatus::OutOfStock => 'bg-neutral-500',
        \App\Enums\StockStatus::LowStock => 'bg-red-700',
        default => 'bg-green-700',
    };
    $statusText = $status === \App\Enums\StockStatus::LowStock
        ? __('Only :count left', ['count' => $product->stock])
        : $status->label();
@endphp

<article class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white">
    <div class="flex aspect-square flex-col items-center justify-center gap-2 bg-stone-100" aria-hidden="true">
        <flux:icon :name="$icon" class="size-14 text-stone-400" />
        <span class="text-xs text-stone-500">{{ __('Product image') }}</span>
    </div>

    <div class="flex flex-1 flex-col gap-1.5 p-3.5 pb-4">
        @if ($categoryName)
            <span class="text-xs font-medium tracking-wider text-neutral-600 uppercase">{{ $categoryName }}</span>
        @endif

        <h3 class="text-base leading-snug font-semibold">{{ $product->name }}</h3>
        <div class="text-xl font-bold">₱{{ number_format($product->price, 2) }}</div>

        <div class="mb-2 flex items-center gap-2 text-sm font-medium {{ $statusClasses }}">
            <span class="size-2 flex-none rounded-full {{ $dotClasses }}"></span>
            {{ $statusText }}
        </div>

        <button
            type="button"
            wire:click="addToCart({{ $product->id }})"
            @disabled($isOut)
            aria-label="{{ __('Add :name to cart', ['name' => $product->name]) }}"
            class="mt-auto inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-orange-700 px-6 text-sm font-semibold text-white hover:bg-orange-800 disabled:cursor-not-allowed disabled:bg-stone-200 disabled:text-neutral-600"
        >
            {{ $isOut ? __('Sold out') : __('Add to Cart') }}
        </button>
    </div>
</article>

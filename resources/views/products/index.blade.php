<x-layouts::shop :title="__('Products')">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <flux:heading size="xl">{{ __('Products') }}</flux:heading>

        @if ($products->isEmpty())
            <div class="rounded-xl border border-neutral-200 p-6 text-center text-neutral-500 dark:border-neutral-700">
                {{ __('No products yet.') }}
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Price') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Stock') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($products as $product)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $product->name }}</td>
                                <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">{{ \Illuminate\Support\Str::limit($product->description, 80) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($product->price, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ $product->stock }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts::shop>

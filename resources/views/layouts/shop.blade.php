<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <header class="border-b border-neutral-200 dark:border-neutral-700">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
                <a href="{{ route('products.index') }}" class="flex items-center gap-2 font-semibold">
                    <x-app-logo-icon class="size-7 fill-current text-black dark:text-white" />
                    <span>{{ config('app.name', 'Laravel') }}</span>
                </a>

                <nav class="flex items-center gap-4 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" class="hover:underline">{{ auth()->user()->name }}</a>
                    @else
                        <a href="{{ route('login') }}" class="hover:underline">{{ __('Log in') }}</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="hover:underline">{{ __('Register') }}</a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>

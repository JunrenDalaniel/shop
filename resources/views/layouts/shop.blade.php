<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-neutral-900 antialiased">
        {{ $slot }}

        @fluxScripts
    </body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-dvh items-center justify-center bg-slate-50 px-4 py-10">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>

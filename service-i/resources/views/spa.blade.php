<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'service-i') }} — Notes</title>

        @php
            $spaAssetsReady = file_exists(public_path('build/manifest.json'))
                || file_exists(public_path('hot'));
        @endphp
        @if ($spaAssetsReady)
            @vite(['resources/js/app.js'])
        @else
            <style>
                body { font-family: system-ui, sans-serif; margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; background: #fafaf9; color: #1c1917; }
                .spa-fallback { max-width: 22rem; padding: 1.5rem; text-align: center; }
                .spa-fallback h1 { font-size: 1.125rem; margin: 0 0 0.5rem; }
                .spa-fallback p { font-size: 0.875rem; color: #78716c; margin: 0; line-height: 1.5; }
                code { font-size: 0.8125rem; background: #e7e5e4; padding: 0.125rem 0.375rem; border-radius: 0.25rem; }
            </style>
        @endif
    </head>
    <body class="bg-paper text-ink antialiased">
        @if ($spaAssetsReady)
            <div id="app"></div>
        @else
            <div class="spa-fallback">
                <h1>Фронтенд не собран</h1>
                <p>Выполните <code>npm run build</code> в service-i или пересоберите образ Docker.</p>
            </div>
        @endif
    </body>
</html>

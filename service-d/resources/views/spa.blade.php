<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'service-d') }}</title>

        @php
            $spaAssetsReady = file_exists(public_path('spa-build/manifest.json'))
                || file_exists(public_path('hot'));
        @endphp
        @if ($spaAssetsReady)
            @vite(['resources/js/spa-app/app.js'], 'spa-build')
        @else
            <style>
                body { font-family: system-ui, sans-serif; margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; }
                .spa-fallback { max-width: 22rem; padding: 1.5rem; text-align: center; }
                .spa-fallback h1 { font-size: 1.125rem; margin: 0 0 0.5rem; }
                .spa-fallback p { font-size: 0.875rem; color: #64748b; margin: 0; line-height: 1.5; }
                code { font-size: 0.8125rem; background: #e2e8f0; padding: 0.125rem 0.375rem; border-radius: 0.25rem; }
            </style>
        @endif
    </head>
    <body class="bg-slate-50 text-slate-900 antialiased">
        @if ($spaAssetsReady)
            <div id="spa-app"></div>
        @else
            <div class="spa-fallback">
                <h1>Фронтенд не собран</h1>
                <p>Выполните <code>docker compose exec service-d npm run build</code> или запустите Vite dev-сервер.</p>
            </div>
        @endif
    </body>
</html>

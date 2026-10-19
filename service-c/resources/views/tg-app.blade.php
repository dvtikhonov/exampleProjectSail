<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="#2688eb">

        <title>Заказ еды</title>

        <script>window.__MESSENGER_CHANNEL__ = 'telegram';</script>

        @if (! empty($localDevInitData))
            <script>window.__TELEGRAM_DEV_INIT_DATA__ = @json($localDevInitData);</script>
        @endif

        <script src="https://telegram.org/js/telegram-web-app.js"></script>
        <script>
            (function () {
                var tg = window.Telegram && window.Telegram.WebApp;

                if (!tg) {
                    return;
                }

                if (typeof tg.ready === 'function') {
                    tg.ready();
                }

                if (typeof tg.disableVerticalSwipes === 'function') {
                    tg.disableVerticalSwipes();
                }

                // Safe area до монтирования Vue: notch + «Закрыть» / меню Telegram.
                function insetPx(value) {
                    return typeof value === 'number' && isFinite(value) && value > 0 ? value : 0;
                }

                function applySafeAreaCssVars() {
                    var root = document.documentElement;
                    var safe = tg.safeAreaInset;
                    var content = tg.contentSafeAreaInset;

                    if (safe) {
                        root.style.setProperty('--tg-safe-area-inset-top', insetPx(safe.top) + 'px');
                        root.style.setProperty('--tg-safe-area-inset-bottom', insetPx(safe.bottom) + 'px');
                        root.style.setProperty('--tg-safe-area-inset-left', insetPx(safe.left) + 'px');
                        root.style.setProperty('--tg-safe-area-inset-right', insetPx(safe.right) + 'px');
                    }

                    if (content) {
                        root.style.setProperty('--tg-content-safe-area-inset-top', insetPx(content.top) + 'px');
                        root.style.setProperty('--tg-content-safe-area-inset-bottom', insetPx(content.bottom) + 'px');
                        root.style.setProperty('--tg-content-safe-area-inset-left', insetPx(content.left) + 'px');
                        root.style.setProperty('--tg-content-safe-area-inset-right', insetPx(content.right) + 'px');
                    }
                }

                applySafeAreaCssVars();

                if (typeof tg.onEvent === 'function') {
                    tg.onEvent('safeAreaChanged', applySafeAreaCssVars);
                    tg.onEvent('contentSafeAreaChanged', applySafeAreaCssVars);
                    tg.onEvent('fullscreenChanged', applySafeAreaCssVars);
                }

                var platform = String(tg.platform || '').toLowerCase();
                var isMobile = platform === 'ios'
                    || platform === 'android'
                    || platform === 'android_x';

                // Mobile: expand bottom sheet to full height.
                // Desktop/web: keep a windowed SeparatePanel (title bar, drag).
                // Fullscreen on Telegram Desktop can pin the panel to the right
                // edge without a movable chrome (Windows SeparatePanel bug).
                if (isMobile) {
                    if (typeof tg.expand === 'function') {
                        tg.expand();
                    }

                    applySafeAreaCssVars();

                    return;
                }

                function leaveFullscreen() {
                    if (tg.isFullscreen && typeof tg.exitFullscreen === 'function') {
                        tg.exitFullscreen();
                    }
                }

                leaveFullscreen();

                if (typeof tg.onEvent === 'function') {
                    tg.onEvent('fullscreenChanged', leaveFullscreen);
                }
            })();
        </script>
        @php
            $tgAppAssetsReady = file_exists(public_path('max-build/manifest.json'))
                || file_exists(public_path('hot'));
        @endphp
        @if ($tgAppAssetsReady)
            @vite(['resources/js/max-app/app.js'], 'max-build')
        @else
            <style>
                body { font-family: system-ui, sans-serif; margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; background: #f5f5f5; color: #333; }
                .tg-app-fallback { max-width: 20rem; padding: 1.5rem; text-align: center; }
                .tg-app-fallback h1 { font-size: 1.125rem; margin: 0 0 0.5rem; }
                .tg-app-fallback p { font-size: 0.875rem; color: #666; margin: 0; line-height: 1.5; }
                code { font-size: 0.8125rem; background: #eee; padding: 0.125rem 0.375rem; border-radius: 0.25rem; }
            </style>
        @endif
    </head>
    <body class="max-app-body bg-max-surface text-max-text antialiased">
        @if ($tgAppAssetsReady)
            <div id="tg-app"></div>
        @else
            <div class="tg-app-fallback">
                <h1>Фронтенд не собран</h1>
                <p>Выполните <code>docker compose exec service-c npm run build</code> и перезапустите контейнер. Для мобильных устройств не используйте <code>npm run dev</code>.</p>
            </div>
        @endif
    </body>
</html>

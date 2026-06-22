<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="#2688eb">

        <title>Заказ еды</title>

        @if (! empty($localDevInitData))
            <script>window.__MAX_DEV_INIT_DATA__ = @json($localDevInitData);</script>
        @endif

        <script src="https://st.max.ru/js/max-web-app.js"></script>
        <script>
            (function () {
                var platform = window.WebApp?.platform ?? null;
                var activeReadyJob = null;
                var lastPolledHidden = document.hidden;

                function canSignalReady() {
                    return document.visibilityState === 'visible'
                        && typeof window.WebApp?.ready === 'function';
                }

                function invokeReady(job) {
                    window.WebApp.ready();
                    window.__MAX_SHELL_READY_SENT__ = true;
                    activeReadyJob = null;
                }

                function signalReady() {
                    var job = activeReadyJob;

                    if (!job) {
                        return;
                    }

                    if (!job.allowReplay && window.__MAX_SHELL_READY_SENT__) {
                        activeReadyJob = null;

                        return;
                    }

                    if (!canSignalReady()) {
                        job.attempts += 1;

                        if (job.attempts <= 120) {
                            requestAnimationFrame(signalReady);
                        } else {
                            activeReadyJob = null;
                        }

                        return;
                    }

                    invokeReady(job);
                }

                function resumeReadyIfPending() {
                    if (!activeReadyJob || window.__MAX_SHELL_READY_SENT__) {
                        return;
                    }

                    requestAnimationFrame(function () {
                        requestAnimationFrame(signalReady);
                    });
                }

                function scheduleReady(source, allowReplay) {
                    if (!allowReplay && window.__MAX_SHELL_READY_SENT__) {
                        return;
                    }

                    activeReadyJob = { source: source, allowReplay: allowReplay, attempts: 0 };

                    if (document.visibilityState === 'visible') {
                        requestAnimationFrame(function () {
                            requestAnimationFrame(signalReady);
                        });

                        return;
                    }
                }

                function closeDesktopApp() {
                    window.__MAX_SHELL_READY_SENT__ = false;

                    if (typeof window.WebApp?.close === 'function') {
                        window.WebApp.close();
                    }
                }

                window.__MAX_CLOSE_DESKTOP_APP__ = closeDesktopApp;

                function setupDesktopBackClose() {
                    if (platform !== 'desktop' || !window.WebApp?.BackButton) {
                        return;
                    }

                    window.WebApp.BackButton.onClick(closeDesktopApp);
                    window.WebApp.BackButton.show();
                }

                setupDesktopBackClose();
                scheduleReady('first-open', false);

                document.addEventListener('visibilitychange', function () {
                    if (!document.hidden) {
                        resumeReadyIfPending();
                    }
                });

                window.addEventListener('pageshow', function (event) {
                    if (event.persisted) {
                        resumeReadyIfPending();
                    }
                });

                setInterval(function () {
                    var hidden = document.hidden;

                    if (hidden === lastPolledHidden) {
                        return;
                    }

                    lastPolledHidden = hidden;

                    if (!hidden) {
                        resumeReadyIfPending();
                    }
                }, 400);

            })();
        </script>
        @php
            $maxAppAssetsReady = file_exists(public_path('max-build/manifest.json'))
                || file_exists(public_path('hot'));
        @endphp
        @if ($maxAppAssetsReady)
            @vite(['resources/js/max-app/app.js'], 'max-build')
        @else
            <style>
                body { font-family: system-ui, sans-serif; margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; background: #f5f5f5; color: #333; }
                .max-app-fallback { max-width: 20rem; padding: 1.5rem; text-align: center; }
                .max-app-fallback h1 { font-size: 1.125rem; margin: 0 0 0.5rem; }
                .max-app-fallback p { font-size: 0.875rem; color: #666; margin: 0; line-height: 1.5; }
                code { font-size: 0.8125rem; background: #eee; padding: 0.125rem 0.375rem; border-radius: 0.25rem; }
            </style>
        @endif
    </head>
    <body class="max-app-body bg-max-surface text-max-text antialiased">
        @if ($maxAppAssetsReady)
            <div id="max-app"></div>
        @else
            <div class="max-app-fallback">
                <h1>Фронтенд не собран</h1>
                <p>Выполните <code>docker compose exec service-c npm run build</code> и перезапустите контейнер. Для мобильных устройств не используйте <code>npm run dev</code>.</p>
            </div>
        @endif
    </body>
</html>

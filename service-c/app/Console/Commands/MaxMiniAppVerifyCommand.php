<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\MaxAppRequestContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Artisan-команда проверки доступности MAX mini-app по публичному URL.
 */
class MaxMiniAppVerifyCommand extends Command
{
    protected $signature = 'max:miniapp:verify';

    protected $description = 'Проверить доступность MAX mini-app по MAX_MINI_APP_URL';

    /**
     * Выполняет проверку HTML и ассетов mini-app.
     */
    public function handle(): int
    {
        $miniAppUrl = $this->resolveMiniAppUrl();
        $appUrl = rtrim((string) config('app.url'), '/');
        $localUrl = $this->resolveLocalMaxAppUrl();
        $isLocalPc = str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1');

        if ($miniAppUrl === '' && $isLocalPc) {
            $this->line('Режим ПК: MAX_MINI_APP_URL не задан — проверяю только локальный mini-app.');
            $this->line("APP_URL: {$appUrl}");
            $this->checkLocalHtml($localUrl);

            return $this->verifyLocalOnly($localUrl);
        }

        if ($miniAppUrl === '') {
            $this->error('Не задан MAX_MINI_APP_URL и не удалось вывести URL из MAX_WEBHOOK_URL / APP_URL.');

            return self::FAILURE;
        }

        if (trim((string) config('max.ui_stand.mini_app_url', '')) === '') {
            $this->line('MAX_MINI_APP_URL пуст — проверяю URL из MAX_WEBHOOK_URL (кабинет MAX + open_app через MAX_BOT_USERNAME).');
        }

        if (str_ends_with(strtolower($appUrl), '/max-app')) {
            $this->error('APP_URL задан неверно: не должен заканчиваться на /max-app');
            $this->line('  Сейчас: '.$appUrl);
            $this->line('  Нужно: '.preg_replace('#/max-app$#', '', $appUrl));
            $this->line('  MAX_MINI_APP_URL остаётся: '.$miniAppUrl);

            return self::FAILURE;
        }

        $this->line("Проверка: {$miniAppUrl}");
        $this->line("APP_URL: {$appUrl}");

        $this->checkLocalHtml($localUrl);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($miniAppUrl);
        } catch (\Throwable $exception) {
            $this->error('Не удалось подключиться: '.$exception->getMessage());
            $this->warnTunnelHelp();

            return self::FAILURE;
        }

        $status = $response->status();
        $body = $response->body();

        $this->line("HTTP {$status} (как браузер / MAX WebView)");

        if ($status !== 200) {
            $this->error('Mini-app URL недоступен снаружи.');

            return self::FAILURE;
        }

        if ($this->isFxTunnelWarningPage($body)) {
            $this->error('fxTunnel вернул страницу «Dev Tunnel Warning», а не mini-app.');
            $this->newLine();
            $this->line('Обычно это значит: туннель offline или fxTunnel блокирует GET в iframe MAX.');
            $this->line('  ./scripts/fxtun-exampleprojectsail-watch.sh');
            $this->line('  ./scripts/diag-max-fxtun.sh');
            $this->newLine();
            $this->line('Проверка с обходом interstitial:');
            $this->line("  curl -sS -H 'X-FxTunnel-Skip-Warning: 1' {$miniAppUrl} | head -20");

            return self::FAILURE;
        }

        if (! str_contains($body, 'id="max-app"') || ! str_contains($body, 'max-web-app.js')) {
            if (str_contains($body, 'Фронтенд не собран')) {
                $this->error('Через туннель отдаётся заглушка «Фронтенд не собран» — нет production-сборки.');
                $this->line('  ./scripts/build-max-app.sh');
            } else {
                $this->warn('Ответ 200, но HTML не похож на max-app.');
            }

            return self::FAILURE;
        }

        $this->info('HTML max-app получен через туннель.');

        if (preg_match('#https?://[^"\']+/(?:max-build|build)/assets/[^"\']+\.js#', $body, $matches) !== 1) {
            if (preg_match('#/max-app/(?:max-build|build)/assets/[^"\']+\.js#', $body)) {
                $this->error('Ассеты по пути /max-app/.../build/ — исправьте APP_URL (уберите /max-app).');

                return self::FAILURE;
            }

            $this->warn('В HTML нет /max-build/assets/*.js — выполните: docker compose exec service-c npm run build');

            return self::FAILURE;
        }

        $assetUrl = $matches[0];
        $assetStatus = Http::timeout(15)->get($assetUrl)->status();
        $this->line("JS asset: {$assetUrl} → HTTP {$assetStatus}");

        if ($assetStatus !== 200) {
            $this->error('JS-ассет недоступен через туннель (HTTP '.$assetStatus.').');

            return self::FAILURE;
        }

        $assetBody = Http::timeout(15)->get($assetUrl)->body();
        if ($this->isFxTunnelWarningPage($assetBody)) {
            $this->error('JS-ассет через туннель — снова страница fxTunnel Warning.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Mini-app доступен. URL для кабинета MAX:');
        $this->line("  {$miniAppUrl}");

        return self::SUCCESS;
    }

    /**
     * Определяет URL mini-app для проверки.
     */
    private function resolveMiniAppUrl(): string
    {
        $explicit = trim((string) config('max.ui_stand.mini_app_url', ''));

        if ($explicit !== '') {
            return $explicit;
        }

        $publicUrl = MaxAppRequestContext::publicAppUrl();

        if ($publicUrl !== null) {
            return $publicUrl.'/max-app';
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && ! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '127.0.0.1')) {
            return $appUrl.'/max-app';
        }

        return '';
    }

    /**
     * Определяет локальный URL MAX mini-app.
     */
    private function resolveLocalMaxAppUrl(): string
    {
        $candidates = [
            'http://127.0.0.1:'.(int) env('SERVICE_C_INTERNAL_PORT', 8000).'/max-app',
            'http://127.0.0.1:'.(int) env('SERVICE_C_PORT', 8083).'/max-app',
        ];

        foreach ($candidates as $url) {
            try {
                Http::timeout(3)->get($url);

                return $url;
            } catch (\Throwable) {
                continue;
            }
        }

        return $candidates[0];
    }

    /**
     * Проверяет доступность локального HTML mini-app.
     */
    private function checkLocalHtml(string $localUrl): void
    {
        try {
            $localBody = Http::timeout(5)->get($localUrl)->body();
        } catch (\Throwable) {
            $this->warn("Локально {$localUrl} недоступен — docker compose up -d service-c");

            return;
        }

        if (str_contains($localBody, '/max-app/max-build/assets/') || str_contains($localBody, '/max-app/build/assets/')) {
            $this->error('Локальный HTML ссылается на /max-app/.../assets/ — неверный APP_URL.');
        } elseif (str_contains($localBody, 'id="max-app"')) {
            $this->info('Локально Laravel отдаёт корректный max-app.');
        }
    }

    /**
     * Выполняет только локальную проверку mini-app.
     */
    private function verifyLocalOnly(string $localUrl): int
    {
        try {
            $localBody = Http::timeout(5)->get($localUrl)->body();
        } catch (\Throwable $exception) {
            $this->error('Локальный mini-app недоступен: '.$exception->getMessage());
            $this->line('  docker compose up -d service-c');

            return self::FAILURE;
        }

        if (str_contains($localBody, 'Фронтенд не собран')) {
            $this->warn('Выполните: docker compose exec service-c npm run dev  (ПК) или npm run build');

            return self::FAILURE;
        }

        if (! str_contains($localBody, 'id="max-app"') && ! str_contains($localBody, 'localhost:5174')) {
            $this->error('Локальный /max-app не содержит SPA.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Локальный mini-app доступен для разработки на ПК:');
        $this->line("  {$localUrl}");
        $this->line('  Webhook MAX: оставьте MAX_WEBHOOK_URL на HTTPS-туннель и запустите max:webhook:subscribe');

        return self::SUCCESS;
    }

    /**
     * Проверяет, что ответ — страница предупреждения fx-туннеля.
     */
    private function isFxTunnelWarningPage(string $body): bool
    {
        return str_contains($body, 'Dev Tunnel Warning');
    }

    /**
     * Выводит подсказку по настройке туннеля.
     */
    private function warnTunnelHelp(): void
    {
        $this->warn('Обновите URL в кабинете MAX и проверьте, что туннель запущен:');
        $this->line('  ./scripts/fxtun-exampleprojectsail.sh run');
    }
}

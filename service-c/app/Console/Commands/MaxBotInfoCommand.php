<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MaxBotInfoCommand extends Command
{
    protected $signature = 'max:bot:info';

    protected $description = 'Показать профиль бота MAX (GET /me) для проверки токена и username';

    public function handle(): int
    {
        $token = trim((string) config('max.bot_access_token', ''));

        if ($token === '') {
            $this->error('MAX_BOT_ACCESS_TOKEN не задан в .env.');

            return self::FAILURE;
        }

        $response = Http::baseUrl('https://platform-api.max.ru')
            ->withHeaders(['Authorization' => $token])
            ->acceptJson()
            ->get('/me');

        if ($response->status() === 401) {
            $this->error('Токен MAX недействителен (HTTP 401). Обновите MAX_BOT_ACCESS_TOKEN в .env.');

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Не удалось получить профиль бота MAX (HTTP '.$response->status().').');

            return self::FAILURE;
        }

        $body = $response->json();
        if (! is_array($body)) {
            $this->error('Неожиданный ответ MAX API.');

            return self::FAILURE;
        }

        $this->info('Профиль бота MAX:');
        $this->line('  user_id: '.(string) ($body['user_id'] ?? ''));
        $this->line('  name: '.(string) ($body['name'] ?? ''));
        $this->line('  username: '.(string) ($body['username'] ?? ''));
        $this->newLine();
        $this->line('Для кнопки open_app укажите в .env:');
        $this->line('  MAX_BOT_USER_ID='.(string) ($body['user_id'] ?? ''));
        $this->line('  MAX_BOT_USERNAME='.(string) ($body['username'] ?? ''));
        $this->line('  MAX_MINI_APP_URL=https://exampleprojectsail.fxtun.dev/max-app  # или выводится из MAX_WEBHOOK_URL');
        $this->newLine();
        $this->line('После изменения .env отправьте сообщение заново:');
        $this->line('  php artisan max:ui-stand:send');

        return self::SUCCESS;
    }
}

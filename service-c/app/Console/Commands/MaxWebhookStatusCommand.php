<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Max\UiStand\MaxWebhookSubscriber;
use Illuminate\Console\Command;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

class MaxWebhookStatusCommand extends Command
{
    protected $signature = 'max:webhook:status';

    protected $description = 'Проверить подписку MAX webhook и доступность MAX_WEBHOOK_URL';

    public function handle(MaxWebhookSubscriber $subscriber): int
    {
        $configuredUrl = trim((string) config('max.webhook.url', ''));

        $this->line('Конфиг MAX_WEBHOOK_URL: '.($configuredUrl !== '' ? $configuredUrl : '(не задан)'));

        try {
            $subscriptions = $subscriber->listSubscriptions();

            if ($subscriptions === []) {
                $this->warn('У бота нет активных webhook-подписок в MAX.');
            } else {
                $this->info('Подписки в MAX:');

                foreach ($subscriptions as $subscription) {
                    $url = (string) ($subscription['url'] ?? '');
                    $updateTypes = $subscription['update_types'] ?? [];
                    $types = is_array($updateTypes) ? implode(', ', $updateTypes) : '';

                    $this->line("  - {$url}");
                    $this->line("    update_types: {$types}");

                    if ($configuredUrl !== '' && $url !== $configuredUrl) {
                        $this->warn('    URL в MAX не совпадает с MAX_WEBHOOK_URL в .env — выполните max:webhook:subscribe');
                    }
                }
            }
        } catch (MaxMessengerException $exception) {
            $this->error('Не удалось получить подписки MAX: '.$exception->userMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Не удалось получить подписки MAX: '.$exception->getMessage());

            return self::FAILURE;
        }

        $probe = $subscriber->probeWebhookUrl();

        if ($probe['reachable']) {
            $this->info('Проба MAX_WEBHOOK_URL: OK (HTTP '.$probe['http_status'].')');
        } else {
            $this->error('Проба MAX_WEBHOOK_URL: недоступен');

            if ($probe['http_status'] !== null) {
                $this->line('HTTP статус: '.$probe['http_status']);
            }

            if ($probe['error'] !== null) {
                $this->line('Ошибка: '.$probe['error']);
            }

            if (is_string($probe['error']) && str_contains($probe['error'], '1033')) {
                $this->newLine();
                $this->warn('Cloudflare Error 1033: процесс cloudflared может быть запущен, но trycloudflare.com не маршрутизирует трафик.');
                $this->line('Рекомендации:');
                $this->line('  1) ./scripts/fxtun-exampleprojectsail.sh run — стабильнее в РФ');
                $this->line('  2) cloudflared через VPN');
                $this->line('  3) CLOUDFLARED_USE_DOCKER=1 ./scripts/cloudflared-tunnel.sh');
            } else {
                $this->line('Проверьте, что туннель (cloudflared/fxtun) запущен и MAX_WEBHOOK_URL совпадает с URL из вывода туннеля.');
            }
        }

        return $probe['reachable'] ? self::SUCCESS : self::FAILURE;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Max\UiStand\MaxWebhookSubscriber;
use Illuminate\Console\Command;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Artisan-команда удаления устаревших dev-туннелей из подписок MAX.
 */
class MaxWebhookCleanCommand extends Command
{
    protected $signature = 'max:webhook:clean';

    protected $description = 'Удалить в MAX устаревшие dev-туннели (trycloudflare), внешние подписки не трогать';

    /**
     * Удаляет устаревшие webhook-подписки dev-туннелей.
     */
    public function handle(MaxWebhookSubscriber $subscriber): int
    {
        $configuredUrl = trim((string) config('max.webhook.url', ''));

        if ($configuredUrl === '') {
            $this->error('MAX_WEBHOOK_URL не задан в .env.');

            return self::FAILURE;
        }

        try {
            $result = $subscriber->unsubscribeStaleDevTunnels($configuredUrl);
        } catch (MaxMessengerException $exception) {
            $this->error($exception->userMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Не удалось удалить устаревшие подписки: '.$exception->getMessage());

            return self::FAILURE;
        }

        $removed = $result['removed'];
        $preserved = $result['preserved'];

        if ($removed === []) {
            $this->info('Устаревших dev-туннелей нет.');
        } else {
            $this->info('Удалены устаревшие dev-туннели:');

            foreach ($removed as $url) {
                $this->line('  - '.$url);
            }
        }

        $this->line('Текущий стенд: '.$configuredUrl);

        if ($preserved !== []) {
            $this->info('Сохранены внешние подписки (не трогали):');

            foreach ($preserved as $url) {
                $this->line('  - '.$url);
            }
        }

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Max\MaxWebhookStaleDevTunnelCleanerInterface;
use App\Contracts\Max\MaxWebhookSubscriptionClientInterface;
use App\Contracts\Max\MaxWebhookUrlProbeInterface;
use Illuminate\Console\Command;
use RuntimeException;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Artisan-команда регистрации MAX webhook для событий бота.
 */
class MaxWebhookSubscribeCommand extends Command
{
    protected $signature = 'max:webhook:subscribe';

    protected $description = 'Зарегистрировать MAX webhook для событий message_callback и bot_started';

    /**
     * Очищает устаревшие туннели и регистрирует webhook-подписку.
     */
    public function handle(
        MaxWebhookSubscriptionClientInterface $subscriptionClient,
        MaxWebhookUrlProbeInterface $urlProbe,
        MaxWebhookStaleDevTunnelCleanerInterface $staleDevTunnelCleaner,
    ): int {
        try {
            $configuredUrl = trim((string) config('max.webhook.url', ''));

            if ($configuredUrl !== '') {
                $cleanup = $staleDevTunnelCleaner->unsubscribeStaleDevTunnels($configuredUrl);

                if ($cleanup['removed'] !== []) {
                    $this->warn('Удалены устаревшие dev-туннели: '.count($cleanup['removed']));
                }
            }

            $subscriptionClient->subscribe();
            $this->info('Подписка MAX webhook зарегистрирована.');

            $probe = $urlProbe->probeWebhookUrl();

            if ($probe['reachable']) {
                $this->info('Проба MAX_WEBHOOK_URL: OK (HTTP '.$probe['http_status'].')');
            } else {
                $this->warn('Проба MAX_WEBHOOK_URL: недоступен — туннель не доставляет запросы на service-c.');
                $this->warn('Запустите ./scripts/fxtun-exampleprojectsail.sh run (или cloudflared) и обновите MAX_WEBHOOK_URL в .env.');
            }

            return self::SUCCESS;
        } catch (RuntimeException|MaxMessengerException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Не удалось зарегистрировать подписку MAX webhook.');

            return self::FAILURE;
        }
    }
}

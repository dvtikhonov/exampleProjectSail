<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Telegram\TelegramMessengerClientInterface;
use App\Exceptions\Telegram\TelegramMessengerException;
use Illuminate\Console\Command;
use Throwable;

/**
 * Artisan-команда регистрации Telegram webhook для входящих message.
 */
class TelegramWebhookSubscribeCommand extends Command
{
    protected $signature = 'telegram:webhook:subscribe';

    protected $description = 'Зарегистрировать Telegram webhook для входящих message (личка с ботом)';

    /**
     * Вызывает setWebhook с URL и secret_token из config.
     */
    public function handle(TelegramMessengerClientInterface $client): int
    {
        $url = trim((string) config('telegram.webhook.url', ''));
        $secret = trim((string) config('telegram.webhook.secret', ''));
        $token = trim((string) config('telegram.bot_token', ''));

        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан.');

            return self::FAILURE;
        }

        if ($url === '') {
            $this->error('TELEGRAM_WEBHOOK_URL не задан.');

            return self::FAILURE;
        }

        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET не задан.');

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret)) {
            $this->error('TELEGRAM_WEBHOOK_SECRET: допустимы 1–256 символов [A-Za-z0-9_-].');

            return self::FAILURE;
        }

        try {
            $client->setWebhook($url, $secret, ['message']);
            $this->info('Подписка Telegram webhook зарегистрирована: '.$url);

            return self::SUCCESS;
        } catch (TelegramMessengerException $exception) {
            report($exception);
            $this->error($exception->userMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Не удалось зарегистрировать Telegram webhook: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

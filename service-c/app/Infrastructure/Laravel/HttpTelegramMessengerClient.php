<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\Contracts\Telegram\TelegramMessengerClientInterface;
use App\DTO\Telegram\TelegramInlineKeyboardButtonDto;
use App\Exceptions\Telegram\TelegramMessengerRequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * HTTP-клиент Telegram Bot API: POST /bot{token}/sendMessage.
 */
final class HttpTelegramMessengerClient implements TelegramMessengerClientInterface
{
    private const BASE_URL = 'https://api.telegram.org';

    private const REQUEST_TIMEOUT_SECONDS = 15;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly ApplicationConfigInterface $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function sendMessage(int $chatId, string $text, array $buttonRows = []): void
    {
        $token = trim((string) $this->config->get('telegram.bot_token', ''));

        if ($token === '') {
            throw new TelegramMessengerRequestException('Telegram bot token is not configured');
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($buttonRows !== []) {
            $payload['reply_markup'] = [
                'inline_keyboard' => $this->mapInlineKeyboard($buttonRows),
            ];
        }

        // #region agent log
        $v4 = [];
        $v6 = [];
        try {
            $aRecords = dns_get_record('api.telegram.org', DNS_A);
            $aaaaRecords = dns_get_record('api.telegram.org', DNS_AAAA);
            if (is_array($aRecords)) {
                foreach ($aRecords as $record) {
                    if (isset($record['ip']) && is_string($record['ip'])) {
                        $v4[] = $record['ip'];
                    }
                }
            }
            if (is_array($aaaaRecords)) {
                foreach ($aaaaRecords as $record) {
                    if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                        $v6[] = $record['ipv6'];
                    }
                }
            }
        } catch (Throwable) {
        }
        $this->agentDebugLog('H3', 'HttpTelegramMessengerClient.php', 'telegram dns before sendMessage', [
            'chat_id' => $chatId,
            'v4' => $v4,
            'v6' => $v6,
            'timeout_seconds' => self::REQUEST_TIMEOUT_SECONDS,
        ]);
        // #endregion

        $response = $this->http->request(
            method: 'POST',
            url: '/bot'.$token.'/sendMessage',
            jsonBody: $payload,
            baseUrl: self::BASE_URL,
            timeoutSeconds: self::REQUEST_TIMEOUT_SECONDS,
        );

        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        $ok = is_array($body) && ($body['ok'] ?? false) === true;

        if ($response->successful && $ok) {
            return;
        }

        $description = is_array($body) && isset($body['description']) && is_string($body['description'])
            ? $body['description']
            : trim($response->body);

        if ($description === '') {
            $description = sprintf('Telegram sendMessage failed with HTTP %d', $response->status);
        }

        throw new TelegramMessengerRequestException($description);
    }

    /**
     * {@inheritDoc}
     */
    public function setWebhook(string $url, string $secretToken, array $allowedUpdates = ['message']): void
    {
        $token = trim((string) $this->config->get('telegram.bot_token', ''));

        if ($token === '') {
            throw new TelegramMessengerRequestException('Telegram bot token is not configured');
        }

        $payload = [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => array_values($allowedUpdates),
            'drop_pending_updates' => false,
        ];

        $response = $this->http->request(
            method: 'POST',
            url: '/bot'.$token.'/setWebhook',
            jsonBody: $payload,
            baseUrl: self::BASE_URL,
            timeoutSeconds: self::REQUEST_TIMEOUT_SECONDS,
        );

        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        $ok = is_array($body) && ($body['ok'] ?? false) === true;

        if ($response->successful && $ok) {
            return;
        }

        $description = is_array($body) && isset($body['description']) && is_string($body['description'])
            ? $body['description']
            : trim($response->body);

        if ($description === '') {
            $description = sprintf('Telegram setWebhook failed with HTTP %d', $response->status);
        }

        throw new TelegramMessengerRequestException($description);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function agentDebugLog(string $hypothesisId, string $location, string $message, array $data): void
    {
        // #region agent log
        try {
            $payload = [
                'sessionId' => '87a53d',
                'runId' => 'pre-fix',
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            $line = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (is_string($line)) {
                @file_put_contents('/home/ddd/exampleProjectSail/.cursor/debug-87a53d.log', $line."\n", FILE_APPEND);
            }
            Log::info('telegram_connect_probe', $payload);
        } catch (Throwable) {
        }
        // #endregion
    }

    /**
     * @param  array<int, array<int, TelegramInlineKeyboardButtonDto>>  $buttonRows
     * @return list<list<array<string, mixed>>>
     */
    private function mapInlineKeyboard(array $buttonRows): array
    {
        $rows = [];

        foreach ($buttonRows as $row) {
            $mappedRow = [];

            foreach ($row as $button) {
                $mapped = ['text' => $button->text];

                if ($button->webAppUrl !== null && $button->webAppUrl !== '') {
                    $mapped['web_app'] = ['url' => $button->webAppUrl];
                } elseif ($button->url !== null && $button->url !== '') {
                    $mapped['url'] = $button->url;
                }

                $mappedRow[] = $mapped;
            }

            if ($mappedRow !== []) {
                $rows[] = $mappedRow;
            }
        }

        return $rows;
    }
}

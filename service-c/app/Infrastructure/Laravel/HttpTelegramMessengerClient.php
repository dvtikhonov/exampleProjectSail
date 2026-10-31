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

        $debugConnect = $this->debugConnectEnabled();
        $dns = $debugConnect ? $this->telegramDns() : null;
        if ($dns !== null) {
            // #region agent log
            $this->agentDebug('H3', 'telegram dns before sendMessage', [
                'v4' => $dns['v4'],
                'v6' => $dns['v6'],
                'timeout_seconds' => self::REQUEST_TIMEOUT_SECONDS,
                'laravel_default_connect_timeout' => 10,
            ]);
            // #endregion
        }

        $started = microtime(true);

        try {
            $response = $this->http->request(
                method: 'POST',
                url: '/bot'.$token.'/sendMessage',
                jsonBody: $payload,
                baseUrl: self::BASE_URL,
                timeoutSeconds: self::REQUEST_TIMEOUT_SECONDS,
            );
        } catch (Throwable $exception) {
            if ($debugConnect) {
                $v4 = is_array($dns) ? ($dns['v4'][0] ?? null) : null;
                $v6 = is_array($dns) ? ($dns['v6'][0] ?? null) : null;
                // #region agent log
                $this->agentDebug('H1', 'telegram sendMessage connect failed', [
                    'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
                    'exception' => $exception::class,
                    'curl_errno' => $this->curlErrno($exception),
                    'error' => $this->redactTelegramSecret($exception->getMessage()),
                    'probe_v4' => is_string($v4) ? $this->probeTcp($v4, 4) : null,
                    'probe_v6' => is_string($v6) ? $this->probeTcp($v6, 3) : null,
                ]);
                // #endregion
            }

            throw $exception;
        }

        if ($debugConnect) {
            // #region agent log
            $this->agentDebug('H1', 'telegram sendMessage http returned', [
                'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
                'status' => $response->status,
                'successful' => $response->successful,
            ]);
            // #endregion
        }

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

    private function debugConnectEnabled(): bool
    {
        return $this->config->get('telegram.debug_connect', false) === true;
    }

    /**
     * @return array{v4: list<string>, v6: list<string>}
     */
    private function telegramDns(): array
    {
        $v4 = gethostbynamel('api.telegram.org');
        $v6 = [];
        $records = @dns_get_record('api.telegram.org', DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $v6[] = $record['ipv6'];
                }
            }
        }

        return [
            'v4' => is_array($v4) ? array_values($v4) : [],
            'v6' => $v6,
        ];
    }

    /**
     * @return array{ip: string, ok: bool, errno: int, error: string, ms: int}
     */
    private function probeTcp(string $ip, int $timeoutSeconds): array
    {
        $errno = 0;
        $errstr = '';
        $target = str_contains($ip, ':') ? 'tcp://['.$ip.']:443' : 'tcp://'.$ip.':443';
        $started = microtime(true);
        $socket = @stream_socket_client($target, $errno, $errstr, $timeoutSeconds, STREAM_CLIENT_CONNECT);
        $ok = is_resource($socket);

        if ($ok) {
            fclose($socket);
        }

        return [
            'ip' => $ip,
            'ok' => $ok,
            'errno' => $errno,
            'error' => $errstr,
            'ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function curlErrno(Throwable $exception): ?int
    {
        $current = $exception;

        while ($current !== null) {
            if ($current instanceof \GuzzleHttp\Exception\RequestException) {
                $context = $current->getHandlerContext();

                if (isset($context['errno'])) {
                    return (int) $context['errno'];
                }
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    private function redactTelegramSecret(string $message): string
    {
        return (string) preg_replace('#/bot[^/\s]+#', '/bot[redacted]', $message);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function agentDebug(string $hypothesisId, string $message, array $data): void
    {
        $payload = [
            'sessionId' => '26618d',
            'runId' => 'pre-fix',
            'hypothesisId' => $hypothesisId,
            'location' => 'HttpTelegramMessengerClient.php',
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ];

        // #region agent log
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($line)) {
            foreach ([
                '/home/ddd/exampleProjectSail/.cursor/debug-26618d.log',
                '/var/www/html/storage/logs/debug-26618d.log',
            ] as $path) {
                @file_put_contents($path, $line."\n", FILE_APPEND | LOCK_EX);
            }
        }

        Log::info('telegram_connect_probe', $payload);
        // #endregion
    }
}

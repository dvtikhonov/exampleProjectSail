<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Laravel-адаптер {@see HttpClientInterface} поверх Http facade.
 */
class LaravelHttpClient implements HttpClientInterface
{
    /**
     * {@inheritDoc}
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?array $jsonBody = null,
        ?string $baseUrl = null,
        int $timeoutSeconds = 30,
        bool $followRedirects = true,
    ): HttpResponseDto {
        $pending = Http::withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->timeout($timeoutSeconds);

        if (! $followRedirects) {
            $pending = $pending->withoutRedirecting();
        }

        if ($baseUrl !== null && $baseUrl !== '') {
            $pending = $pending->baseUrl($baseUrl);
        }

        $telegramApi = is_string($baseUrl) && str_contains($baseUrl, 'api.telegram.org');
        $startedAtMs = (int) round(microtime(true) * 1000);

        if ($telegramApi) {
            // #region agent log
            $pending = $pending->withOptions([
                'on_stats' => function (TransferStats $stats) use ($timeoutSeconds): void {
                    $handler = $stats->getHandlerStats();
                    $this->agentDebugLog('H3', 'LaravelHttpClient.php', 'telegram curl stats', [
                        'primary_ip' => $handler['primary_ip'] ?? null,
                        'local_ip' => $handler['local_ip'] ?? null,
                        'namelookup_time' => $handler['namelookup_time'] ?? null,
                        'connect_time' => $handler['connect_time'] ?? null,
                        'appconnect_time' => $handler['appconnect_time'] ?? null,
                        'total_time' => $handler['total_time'] ?? null,
                        'http_code' => $handler['http_code'] ?? null,
                        'timeout_seconds' => $timeoutSeconds,
                        'connect_timeout_option_set' => false,
                    ]);
                },
            ]);
            $this->agentDebugLog('H2', 'LaravelHttpClient.php', 'telegram request options', [
                'timeout_seconds' => $timeoutSeconds,
                'connect_timeout_option_set' => false,
                'url_suffix' => str_contains($url, 'sendMessage') ? 'sendMessage' : 'other',
            ]);
            // #endregion
        }

        try {
            $response = $pending->send(
                strtoupper($method),
                $url,
                $jsonBody !== null ? ['json' => $jsonBody] : [],
            );
        } catch (Throwable $exception) {
            // #region agent log
            if ($telegramApi) {
                $this->agentDebugLog('H2', 'LaravelHttpClient.php', 'telegram request threw', [
                    'elapsed_ms' => (int) round(microtime(true) * 1000) - $startedAtMs,
                    'error' => preg_replace('#/bot[^/\s]+#', '/bot[redacted]', $exception->getMessage()),
                ]);
            }
            // #endregion
            throw $exception;
        }

        return new HttpResponseDto(
            status: $response->status(),
            body: $response->body(),
            successful: $response->successful(),
        );
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
}

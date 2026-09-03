<?php

declare(strict_types=1);

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Сравнение значения заголовка с секретом из config() через hash_equals.
 */
trait ComparesConfiguredHeaderSecret
{
    /**
     * Проверяет заголовок запроса против настроенного секрета.
     *
     * @return Response|null null — секрет совпал, запрос можно пропускать дальше
     */
    protected function verifyHeaderSecret(
        Request $request,
        string $configKey,
        string $headerName,
        string $logContext,
    ): ?Response {
        $expected = (string) config($configKey);

        if ($expected === '') {
            Log::warning($this->notConfiguredHeaderSecretMessage($configKey, $logContext));

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        $provided = (string) $request->header($headerName, '');

        if (! hash_equals($expected, $provided)) {
            Log::warning($this->invalidHeaderSecretMessage($configKey, $logContext, $headerName));

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        return null;
    }

    /**
     * Сообщение лога при пустом секрете в конфигурации.
     */
    private function notConfiguredHeaderSecretMessage(string $configKey, string $logContext): string
    {
        return match ($configKey) {
            'max.webhook.secret' => 'MAX webhook rejected: MAX_WEBHOOK_SECRET is not configured.',
            'phototext.agent_token' => 'PhotoText agent rejected: PHOTOTEXT_AGENT_TOKEN is not configured.',
            default => "{$logContext} rejected: secret is not configured.",
        };
    }

    /**
     * Сообщение лога при неверном или отсутствующем заголовке.
     */
    private function invalidHeaderSecretMessage(string $configKey, string $logContext, string $headerName): string
    {
        return match ($configKey) {
            'max.webhook.secret' => 'MAX webhook rejected: invalid X-Max-Bot-Api-Secret header.',
            'phototext.agent_token' => 'PhotoText agent rejected: invalid X-PhotoText-Token header.',
            default => "{$logContext} rejected: invalid {$headerName} header.",
        };
    }
}

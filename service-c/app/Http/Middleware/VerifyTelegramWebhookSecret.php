<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ComparesConfiguredHeaderSecret;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка secret_token Telegram webhook в заголовке X-Telegram-Bot-Api-Secret-Token.
 *
 * @see https://core.telegram.org/bots/api#setwebhook
 */
class VerifyTelegramWebhookSecret
{
    use ComparesConfiguredHeaderSecret;

    /**
     * Проверяет секрет входящего webhook Telegram.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rejected = $this->verifyHeaderSecret(
            $request,
            'telegram.webhook.secret',
            'X-Telegram-Bot-Api-Secret-Token',
            'Telegram webhook',
        );

        if ($rejected !== null) {
            return $rejected;
        }

        return $next($request);
    }
}

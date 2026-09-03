<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ComparesConfiguredHeaderSecret;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка секрета MAX webhook в заголовке X-Max-Bot-Api-Secret.
 */
class VerifyMaxWebhookSecret
{
    use ComparesConfiguredHeaderSecret;

    /**
     * Проверяет секрет входящего webhook MAX.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rejected = $this->verifyHeaderSecret(
            $request,
            'max.webhook.secret',
            'X-Max-Bot-Api-Secret',
            'MAX webhook',
        );

        if ($rejected !== null) {
            return $rejected;
        }

        return $next($request);
    }
}

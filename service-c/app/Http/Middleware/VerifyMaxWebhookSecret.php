<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка секрета MAX webhook в заголовке X-Max-Bot-Api-Secret.
 */
class VerifyMaxWebhookSecret
{
    /**
     * Проверяет секрет входящего webhook MAX.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('max.webhook.secret');

        if ($expected === '') {
            Log::warning('MAX webhook rejected: MAX_WEBHOOK_SECRET is not configured.');

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        $provided = (string) $request->header('X-Max-Bot-Api-Secret', '');

        if (! hash_equals($expected, $provided)) {
            Log::warning('MAX webhook rejected: invalid X-Max-Bot-Api-Secret header.');

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

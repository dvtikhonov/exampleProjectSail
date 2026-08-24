<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка токена агента PhotoText в заголовке X-PhotoText-Token.
 */
class VerifyPhotoTextAgentToken
{
    /**
     * Сравнивает X-PhotoText-Token с PHOTOTEXT_AGENT_TOKEN через hash_equals.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('phototext.agent_token');

        if ($expected === '') {
            Log::warning('PhotoText agent rejected: PHOTOTEXT_AGENT_TOKEN is not configured.');

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        $provided = (string) $request->header('X-PhotoText-Token', '');

        if (! hash_equals($expected, $provided)) {
            Log::warning('PhotoText agent rejected: invalid X-PhotoText-Token header.');

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

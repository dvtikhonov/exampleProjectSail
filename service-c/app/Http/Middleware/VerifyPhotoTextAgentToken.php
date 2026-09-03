<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ComparesConfiguredHeaderSecret;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка токена агента PhotoText в заголовке X-PhotoText-Token.
 */
class VerifyPhotoTextAgentToken
{
    use ComparesConfiguredHeaderSecret;

    /**
     * Сравнивает X-PhotoText-Token с PHOTOTEXT_AGENT_TOKEN через hash_equals.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rejected = $this->verifyHeaderSecret(
            $request,
            'phototext.agent_token',
            'X-PhotoText-Token',
            'PhotoText agent',
        );

        if ($rejected !== null) {
            return $rejected;
        }

        return $next($request);
    }
}

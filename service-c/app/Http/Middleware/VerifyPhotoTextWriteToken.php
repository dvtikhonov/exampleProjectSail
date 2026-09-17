<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ComparesConfiguredHeaderSecret;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка write-токена агента PhotoText в заголовке X-PhotoText-Write-Token.
 */
class VerifyPhotoTextWriteToken
{
    use ComparesConfiguredHeaderSecret;

    /**
     * Сравнивает X-PhotoText-Write-Token с PHOTOTEXT_WRITE_TOKEN через hash_equals.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rejected = $this->verifyHeaderSecret(
            $request,
            'phototext.write_token',
            'X-PhotoText-Write-Token',
            'PhotoText write',
        );

        if ($rejected !== null) {
            return $rejected;
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ComparesConfiguredHeaderSecret;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка секрета sidecar Briskly sync в заголовке X-Briskly-Capture-Secret.
 */
class VerifyBrisklySyncCaptureSecret
{
    use ComparesConfiguredHeaderSecret;

    /**
     * Сравнивает X-Briskly-Capture-Secret с BRISKLY_SYNC_CAPTURE_SECRET через hash_equals.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rejected = $this->verifyHeaderSecret(
            $request,
            'briskly_sync.capture_secret',
            'X-Briskly-Capture-Secret',
            'Briskly sync capture',
        );

        if ($rejected !== null) {
            return $rejected;
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Interface\Http\Middleware;

use App\Http\Middleware\Concerns\ComparesConfiguredHeaderSecret;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка секрета sidecar SchedulePhoto в заголовке X-Schedule-Photo-Capture-Secret.
 */
class VerifySchedulePhotoCaptureSecret
{
    use ComparesConfiguredHeaderSecret;

    /**
     * Сравнивает X-Schedule-Photo-Capture-Secret с schedule_photo.capture_secret.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rejected = $this->verifyHeaderSecret(
            $request,
            'schedule_photo.capture_secret',
            'X-Schedule-Photo-Capture-Secret',
            'SchedulePhoto capture',
        );

        if ($rejected !== null) {
            return $rejected;
        }

        return $next($request);
    }
}

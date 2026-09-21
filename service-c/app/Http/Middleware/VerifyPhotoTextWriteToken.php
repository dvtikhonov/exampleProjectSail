<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Shared\ApplicationEnvironmentInterface;
use App\Http\Middleware\Concerns\ComparesConfiguredHeaderSecret;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка write-токена агента PhotoText в заголовке X-PhotoText-Write-Token.
 *
 * В production дополнительно требует PHOTOTEXT_MANAGER_MAX_USER_ID > 0
 * (allow-list менеджера для мутаций place/apply).
 */
class VerifyPhotoTextWriteToken
{
    use ComparesConfiguredHeaderSecret;

    private const string MANAGER_ALLOWLIST_REQUIRED_MESSAGE = 'PhotoText write недоступен: в production обязателен PHOTOTEXT_MANAGER_MAX_USER_ID > 0.';

    public function __construct(
        private readonly ApplicationEnvironmentInterface $environment,
    ) {}

    /**
     * Сравнивает X-PhotoText-Write-Token с PHOTOTEXT_WRITE_TOKEN через hash_equals.
     * В production без allow-list менеджера возвращает 503.
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

        if ($this->environment->is(['production'])
            && (int) config('phototext.manager_max_user_id') <= 0
        ) {
            return response()->json([
                'message' => self::MANAGER_ALLOWLIST_REQUIRED_MESSAGE,
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}

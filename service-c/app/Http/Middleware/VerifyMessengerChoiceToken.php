<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ComparesConfiguredHeaderSecret;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка секрета service-j в заголовке X-Messenger-Choice-Token.
 */
class VerifyMessengerChoiceToken
{
    use ComparesConfiguredHeaderSecret;

    /**
     * Сравнивает X-Messenger-Choice-Token с MESSENGER_CHOICE_LOG_TOKEN через hash_equals.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rejected = $this->verifyHeaderSecret(
            $request,
            'messenger_choice.log_token',
            'X-Messenger-Choice-Token',
            'Messenger choice log',
        );

        if ($rejected !== null) {
            return $rejected;
        }

        return $next($request);
    }
}

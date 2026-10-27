<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Max\MaxMiniAppAccessLoggerInterface;
use App\Http\Requests\Internal\LogMessengerChoiceRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Внутренний приём визита страницы выбора мессенджера (только сеть Docker, заголовок с токеном).
 */
class InternalMessengerChoiceController extends Controller
{
    public function __construct(
        private readonly MaxMiniAppAccessLoggerInterface $accessLogger,
    ) {}

    /**
     * POST /internal/messenger-choice.
     *
     * Auth: X-Messenger-Choice-Token. Ответы: 204 / 401 / 422.
     */
    public function store(LogMessengerChoiceRequest $request): Response
    {
        $this->accessLogger->logMessengerChoicePageRequest($request->accessContext());

        return response()->noContent();
    }
}

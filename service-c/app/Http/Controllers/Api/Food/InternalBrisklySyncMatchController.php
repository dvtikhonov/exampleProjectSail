<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\BrisklySync\BrisklySyncSessionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Internal\CompleteBrisklySyncMatchRequest;
use Illuminate\Http\JsonResponse;

/**
 * Внутренний колбэк sidecar после фонового wait LLM (без miniapp auth).
 */
class InternalBrisklySyncMatchController extends Controller
{
    public function __construct(
        private readonly BrisklySyncSessionServiceInterface $sessions,
    ) {}

    /**
     * POST /api/food/internal/briskly-sync/match-complete.
     *
     * Auth: X-Briskly-Capture-Secret. Ответы: 200 / 409 stale / 401 / 422.
     */
    public function complete(CompleteBrisklySyncMatchRequest $request): JsonResponse
    {
        $this->sessions->completeQueuedMatch(
            $request->sessionId(),
            $request->matchGeneration(),
            $request->matchLines(),
            $request->error(),
        );

        return response()->json(['ok' => true]);
    }
}

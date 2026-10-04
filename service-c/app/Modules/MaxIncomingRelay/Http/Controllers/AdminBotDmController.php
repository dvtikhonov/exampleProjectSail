<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Http\Controllers;

use App\Contracts\Food\ManualOrder\ManualOrderUserQueryServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Http\Controllers\Controller;
use App\Modules\MaxIncomingRelay\Contracts\BotDmChatServiceInterface;
use App\Modules\MaxIncomingRelay\Http\Requests\ListBotDmMessagesRequest;
use App\Modules\MaxIncomingRelay\Http\Requests\ListBotDmUsersRequest;
use App\Modules\MaxIncomingRelay\Http\Requests\SendBotDmMessageRequest;
use Illuminate\Http\JsonResponse;

/**
 * Admin API лички с пользователем MAX через бота (роль max_manager).
 */
class AdminBotDmController extends Controller
{
    public function __construct(
        private readonly BotDmChatServiceInterface $botDmChatService,
        private readonly ManualOrderUserQueryServiceInterface $manualOrderUserQueryService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
    ) {}

    /**
     * Поиск и список пользователей MAX для выбора собеседника.
     */
    public function users(ListBotDmUsersRequest $request): JsonResponse
    {
        $result = $this->manualOrderUserQueryService->list(
            $request->searchQuery(),
            $request->perPage(),
        );

        return response()->json([
            'users' => array_map(
                static fn ($user): array => $user->toArray(),
                $result['users'],
            ),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Лента сообщений лички с пользователем.
     */
    public function index(ListBotDmMessagesRequest $request, int $maxUserId): JsonResponse
    {
        $messages = $this->botDmChatService->listMessages(
            $maxUserId,
            $request->afterId(),
            $request->limit(),
        );

        return response()->json([
            'messages' => array_map(
                static fn ($message): array => $message->toArray(),
                $messages,
            ),
        ]);
    }

    /**
     * Отправляет текст пользователю в MAX и сохраняет сообщение в историю.
     */
    public function store(SendBotDmMessageRequest $request, int $maxUserId): JsonResponse
    {
        $message = $this->botDmChatService->sendMessage(
            $this->authenticatedMaxUserResolver->identity()->maxUserId,
            $maxUserId,
            $request->body(),
        );

        return response()->json([
            'message' => $message->toArray(),
        ], JsonResponse::HTTP_CREATED);
    }
}

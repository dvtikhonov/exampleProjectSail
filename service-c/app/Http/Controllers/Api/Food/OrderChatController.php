<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Chat\OrderChatServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\ListOrderMessagesRequest;
use App\Http\Requests\Food\SendOrderMessageRequest;
use Illuminate\Http\JsonResponse;

/**
 * API чата по заказу еды для клиента и администратора MAX mini-app.
 */
class OrderChatController extends Controller
{
    public function __construct(
        private readonly OrderChatServiceInterface $orderChatService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
    ) {}

    /**
     * Возвращает историю сообщений чата заказа.
     */
    public function index(ListOrderMessagesRequest $request, int $order): JsonResponse
    {
        $messages = $this->orderChatService->listMessages(
            $this->authenticatedMaxUserResolver->identity(),
            $order,
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
     * Отправляет сообщение в чат заказа.
     */
    public function store(SendOrderMessageRequest $request, int $order): JsonResponse
    {
        $message = $this->orderChatService->sendMessage(
            $this->authenticatedMaxUserResolver->identity(),
            $order,
            $request->body(),
        );

        return response()->json([
            'message' => $message->toArray(),
        ], JsonResponse::HTTP_CREATED);
    }
}

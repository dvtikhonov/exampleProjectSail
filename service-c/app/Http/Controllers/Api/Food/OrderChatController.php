<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\OrderChatServiceInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\ListOrderMessagesRequest;
use App\Http\Requests\Food\SendOrderMessageRequest;
use App\Models\MaxUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API чата по заказу еды для клиента и администратора MAX mini-app.
 */
class OrderChatController extends Controller
{
    public function __construct(
        private readonly OrderChatServiceInterface $orderChatService,
    ) {}

    /**
     * Возвращает историю сообщений чата заказа.
     */
    public function index(ListOrderMessagesRequest $request, int $order): JsonResponse
    {
        try {
            $messages = $this->orderChatService->listMessages(
                $this->maxUser($request),
                $order,
                $request->afterId(),
                $request->limit(),
            );
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

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
        try {
            $message = $this->orderChatService->sendMessage(
                $this->maxUser($request),
                $order,
                $request->body(),
            );
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'message' => $message->toArray(),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * Текущий аутентифицированный пользователь MAX из запроса.
     */
    private function maxUser(Request $request): MaxUser
    {
        /** @var MaxUser $maxUser */
        $maxUser = $request->user();

        return $maxUser;
    }
}

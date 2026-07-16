<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\OrderChatNotifierInterface;
use App\Contracts\Food\OrderChatServiceInterface;
use App\Contracts\Food\OrderMessageRepositoryInterface;
use App\DTO\Food\OrderMessageDto;
use App\Enums\Food\OrderMessageAuthorType;
use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;
use App\Models\FoodOrderMessage;
use App\Models\MaxUser;

/**
 * Чтение и отправка сообщений в чате по заказу еды.
 */
class OrderChatService implements OrderChatServiceInterface
{
    private const int MAX_BODY_LENGTH = 2000;

    private const int DEFAULT_LIST_LIMIT = 50;

    public function __construct(
        private readonly FoodOrderCustomerReadRepositoryInterface $foodOrderReadRepository,
        private readonly OrderMessageRepositoryInterface $orderMessageRepository,
        private readonly OrderChatAuthorizationService $orderChatAuthorizationService,
        private readonly OrderChatNotifierInterface $orderChatNotifier,
    ) {}

    /**
     * Возвращает сообщения чата заказа.
     *
     * @return list<OrderMessageDto>
     *
     * @throws FoodDomainException
     */
    public function listMessages(
        MaxUser $actor,
        int $orderId,
        ?int $afterId = null,
        int $limit = self::DEFAULT_LIST_LIMIT,
    ): array {
        $order = $this->findOrderOrFail($orderId);
        $this->orderChatAuthorizationService->assertCanAccessChat($actor, $order);

        $messages = $this->orderMessageRepository->listForOrder(
            foodOrderId: $order->id,
            afterId: $afterId,
            limit: $this->normalizeLimit($limit),
        );

        $this->orderMessageRepository->markMessagesAsRead(
            foodOrderId: $order->id,
            readerMaxUserId: $actor->max_user_id,
        );

        return array_map(
            fn (FoodOrderMessage $message): OrderMessageDto => $this->mapMessage($message, $order),
            $messages,
        );
    }

    /**
     * Отправляет сообщение в чат заказа.
     *
     * @throws FoodDomainException
     */
    public function sendMessage(MaxUser $actor, int $orderId, string $body): OrderMessageDto
    {
        $normalizedBody = $this->normalizeBody($body);
        $order = $this->findOrderOrFail($orderId);
        $this->orderChatAuthorizationService->assertCanAccessChat($actor, $order);

        $message = $this->orderMessageRepository->create(
            foodOrderId: $order->id,
            senderMaxUserId: $actor->max_user_id,
            body: $normalizedBody,
        );

        $message->loadMissing('sender');

        $dto = $this->mapMessage($message, $order);
        $this->orderChatNotifier->notify($order, $dto);

        return $dto;
    }

    /**
     * Находит заказ или выбрасывает доменное исключение.
     *
     * @throws FoodDomainException
     */
    private function findOrderOrFail(int $orderId): FoodOrder
    {
        $order = $this->foodOrderReadRepository->findById($orderId);

        if ($order === null) {
            throw new FoodDomainException('Order not found.', 404);
        }

        return $order;
    }

    /**
     * Нормализует и валидирует текст сообщения чата.
     *
     * @throws FoodDomainException
     */
    private function normalizeBody(string $body): string
    {
        $normalized = trim($body);

        if ($normalized === '') {
            throw new FoodDomainException('Message body is required.', 422);
        }

        if (mb_strlen($normalized) > self::MAX_BODY_LENGTH) {
            throw new FoodDomainException(
                sprintf('Message body must not exceed %d characters.', self::MAX_BODY_LENGTH),
                422,
            );
        }

        return $normalized;
    }

    /**
     * Нормализует лимит выборки сообщений.
     */
    private function normalizeLimit(int $limit): int
    {
        if ($limit < 1) {
            return self::DEFAULT_LIST_LIMIT;
        }

        return min($limit, 100);
    }

    /**
     * Преобразует модель сообщения в DTO.
     */
    private function mapMessage(
        FoodOrderMessage $message,
        FoodOrder $order,
    ): OrderMessageDto {
        $authorType = $message->sender_max_user_id === $order->max_user_id
            ? OrderMessageAuthorType::Customer
            : OrderMessageAuthorType::Admin;

        return new OrderMessageDto(
            id: $message->id,
            foodOrderId: $message->food_order_id,
            senderMaxUserId: $message->sender_max_user_id,
            senderFirstName: $message->sender?->first_name,
            senderLastName: $message->sender?->last_name,
            senderUsername: $message->sender?->username,
            authorType: $authorType,
            body: $message->body,
            createdAt: $message->created_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }
}

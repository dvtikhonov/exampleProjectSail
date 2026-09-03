<?php

declare(strict_types=1);

namespace App\Services\Food\Chat;

use App\Contracts\Food\Chat\OrderChatNotifierInterface;
use App\Contracts\Food\Chat\OrderChatServiceInterface;
use App\Contracts\Food\Chat\OrderMessageRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\DTO\Food\Chat\OrderMessageDto;
use App\DTO\Food\Chat\OrderMessageRecord;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Chat\OrderMessageAuthorType;
use App\Exceptions\Food\FoodDomainException;

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
        MaxUserIdentity $actor,
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
            readerMaxUserId: $actor->maxUserId,
        );

        return array_map(
            fn (OrderMessageRecord $message): OrderMessageDto => $this->mapMessage($message, $order),
            $messages,
        );
    }

    /**
     * Отправляет сообщение в чат заказа.
     *
     * @throws FoodDomainException
     */
    public function sendMessage(MaxUserIdentity $actor, int $orderId, string $body): OrderMessageDto
    {
        $normalizedBody = $this->normalizeBody($body);
        $order = $this->findOrderOrFail($orderId);
        $this->orderChatAuthorizationService->assertCanAccessChat($actor, $order);

        $message = $this->orderMessageRepository->create(
            foodOrderId: $order->id,
            senderMaxUserId: $actor->maxUserId,
            body: $normalizedBody,
        );

        $dto = $this->mapMessage($message, $order);
        $this->orderChatNotifier->notify($order, $dto);

        return $dto;
    }

    /**
     * Находит заказ или выбрасывает доменное исключение.
     *
     * @throws FoodDomainException
     */
    private function findOrderOrFail(int $orderId): FoodOrderRecord
    {
        $order = $this->foodOrderReadRepository->findById($orderId);

        if ($order === null) {
            throw new FoodDomainException('Заказ не найден.', 404);
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
            throw new FoodDomainException('Текст сообщения обязателен.', 422);
        }

        if (mb_strlen($normalized) > self::MAX_BODY_LENGTH) {
            throw new FoodDomainException(
                sprintf('Текст сообщения не должен превышать %d символов.', self::MAX_BODY_LENGTH),
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
     * Преобразует доменную проекцию сообщения в API DTO.
     */
    private function mapMessage(
        OrderMessageRecord $message,
        FoodOrderRecord $order,
    ): OrderMessageDto {
        $authorType = $message->senderMaxUserId === $order->maxUserId
            ? OrderMessageAuthorType::Customer
            : OrderMessageAuthorType::Admin;

        return new OrderMessageDto(
            id: $message->id,
            foodOrderId: $message->foodOrderId,
            senderMaxUserId: $message->senderMaxUserId,
            senderFirstName: $message->senderFirstName,
            senderLastName: $message->senderLastName,
            senderUsername: $message->senderUsername,
            authorType: $authorType,
            body: $message->body,
            createdAt: $message->createdAt,
        );
    }
}

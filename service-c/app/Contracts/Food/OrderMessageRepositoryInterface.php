<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\FoodOrderMessage;

/**
 * Репозиторий сообщений чата по заказам еды.
 */
interface OrderMessageRepositoryInterface
{
    /**
     * Создаёт сообщение в чате заказа.
     */
    public function create(int $foodOrderId, int $senderMaxUserId, string $body): FoodOrderMessage;

    /**
     * Сообщения заказа в хронологическом порядке; при указании after_id — только с id больше.
     *
     * @return list<FoodOrderMessage>
     */
    public function listForOrder(int $foodOrderId, ?int $afterId = null, int $limit = 50): array;

    /**
     * Находит сообщение чата по идентификатору.
     */
    public function findById(int $id): ?FoodOrderMessage;

    /**
     * Статистика чата по заказам для списка клиента.
     *
     * @param  list<int>  $foodOrderIds
     * @return array<int, array{last_message_at: ?string, unread_count: int}>
     */
    public function getChatStatsForOrders(array $foodOrderIds, int $viewerMaxUserId): array;

    /**
     * Отмечает все сообщения заказа прочитанными для указанного участника.
     */
    public function markMessagesAsRead(int $foodOrderId, int $readerMaxUserId): void;
}

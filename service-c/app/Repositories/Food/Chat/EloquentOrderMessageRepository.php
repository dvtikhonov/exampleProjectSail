<?php

declare(strict_types=1);

namespace App\Repositories\Food\Chat;

use App\Contracts\Food\Chat\OrderMessageRepositoryInterface;
use App\DTO\Food\Chat\OrderMessageRecord;
use App\Models\Food\FoodOrderChatRead;
use App\Models\Food\FoodOrderMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-реализация репозитория сообщений чата заказов еды.
 */
class EloquentOrderMessageRepository implements OrderMessageRepositoryInterface
{
    public function __construct(
        private readonly OrderMessageMapper $orderMessageMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function create(int $foodOrderId, int $senderMaxUserId, string $body): OrderMessageRecord
    {
        $message = FoodOrderMessage::query()->create([
            'food_order_id' => $foodOrderId,
            'sender_max_user_id' => $senderMaxUserId,
            'body' => $body,
        ]);

        $message->loadMissing('sender');

        return $this->orderMessageMapper->toRecord($message);
    }

    /**
     * {@inheritDoc}
     */
    public function listForOrder(int $foodOrderId, ?int $afterId = null, int $limit = 50): array
    {
        $query = FoodOrderMessage::query()
            ->with('sender')
            ->where('food_order_id', $foodOrderId)
            ->orderBy('id');

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (FoodOrderMessage $message): OrderMessageRecord => $this->orderMessageMapper->toRecord($message))
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?OrderMessageRecord
    {
        $message = FoodOrderMessage::query()
            ->with('sender')
            ->find($id);

        return $message !== null ? $this->orderMessageMapper->toRecord($message) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getChatStatsForOrders(array $foodOrderIds, int $viewerMaxUserId): array
    {
        if ($foodOrderIds === []) {
            return [];
        }

        $stats = [];

        foreach ($foodOrderIds as $orderId) {
            $stats[$orderId] = [
                'last_message_at' => null,
                'unread_count' => 0,
            ];
        }

        $lastMessages = FoodOrderMessage::query()
            ->selectRaw('food_order_id, MAX(created_at) as last_message_at')
            ->whereIn('food_order_id', $foodOrderIds)
            ->groupBy('food_order_id')
            ->get();

        foreach ($lastMessages as $row) {
            $orderId = (int) $row->food_order_id;
            $stats[$orderId]['last_message_at'] = $row->last_message_at !== null
                ? Carbon::parse($row->last_message_at)->toIso8601String()
                : null;
        }

        $unreadCounts = DB::table('max_food_order_messages as m')
            ->leftJoin('max_food_order_chat_reads as r', function ($join) use ($viewerMaxUserId): void {
                $join->on('r.food_order_id', '=', 'm.food_order_id')
                    ->where('r.reader_max_user_id', '=', $viewerMaxUserId);
            })
            ->selectRaw('m.food_order_id, COUNT(*) as unread_count')
            ->whereIn('m.food_order_id', $foodOrderIds)
            ->where('m.sender_max_user_id', '!=', $viewerMaxUserId)
            ->whereRaw('m.id > COALESCE(r.last_read_message_id, 0)')
            ->groupBy('m.food_order_id')
            ->get();

        foreach ($unreadCounts as $row) {
            $orderId = (int) $row->food_order_id;
            $stats[$orderId]['unread_count'] = (int) $row->unread_count;
        }

        return $stats;
    }

    /**
     * {@inheritDoc}
     */
    public function markMessagesAsRead(int $foodOrderId, int $readerMaxUserId): void
    {
        $latestMessageId = FoodOrderMessage::query()
            ->where('food_order_id', $foodOrderId)
            ->max('id');

        if ($latestMessageId === null) {
            return;
        }

        FoodOrderChatRead::query()->updateOrCreate(
            [
                'food_order_id' => $foodOrderId,
                'reader_max_user_id' => $readerMaxUserId,
            ],
            [
                'last_read_message_id' => (int) $latestMessageId,
            ],
        );
    }
}

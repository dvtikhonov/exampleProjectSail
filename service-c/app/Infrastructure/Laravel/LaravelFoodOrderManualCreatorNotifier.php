<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Review\FoodOrderManualCreatorMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderManualCreatorNotifierInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use Psr\Log\LoggerInterface;

/**
 * Уведомление менеджеру, оформившему ручной заказ, через MAX.
 */
class LaravelFoodOrderManualCreatorNotifier implements FoodOrderManualCreatorNotifierInterface
{
    public function __construct(
        private readonly FoodOrderManualCreatorMaxMessageBuilderInterface $manualCreatorMessageBuilder,
        private readonly MaxUiStandRecipientResolverInterface $uiStandRecipientResolver,
        private readonly FoodOrderCustomerMaxDispatchHelper $dispatchHelper,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     *
     * Сначала DM на created_by_max_user_id; при ошибке MAX (например демо-id → 404)
     * — fallback в MAX_UI_STAND_* из .env (без кэша webhook).
     */
    public function notifyManualOrderCreatorConfirmed(FoodOrderRecord $order): void
    {
        if (! $order->isManual) {
            return;
        }

        $creatorId = $order->createdByMaxUserId;

        if ($creatorId === null) {
            return;
        }

        $text = $this->manualCreatorMessageBuilder->buildManualOrderCreatorConfirmed($order);

        $sent = $this->dispatchHelper->trySendToUser($text, $order, (int) $creatorId);

        if (! $sent) {
            $this->trySendManualCreatorToUiStand($text, $order);
        }
    }

    /**
     * Fallback: детальный состав ручного заказа в UI Stand (только MAX_UI_STAND_*).
     */
    private function trySendManualCreatorToUiStand(string $text, FoodOrderRecord $order): void
    {
        $chatIds = $this->uiStandRecipientResolver->configuredChatIds();
        $userIds = $this->uiStandRecipientResolver->configuredUserIds();

        if ($chatIds === [] && $userIds === []) {
            $this->logger->warning(
                'MAX manual order creator notification fallback skipped: UI Stand recipients are not configured',
                ['order_id' => $order->id],
            );

            return;
        }

        foreach ($chatIds as $chatId) {
            $this->dispatchHelper->trySendToChat($text, $order, $chatId);
        }

        foreach ($userIds as $userId) {
            $this->dispatchHelper->trySendToUser($text, $order, $userId);
        }
    }
}

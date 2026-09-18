<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Review\FoodOrderCustomerMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserDisplayDto;
use Psr\Log\LoggerInterface;

/**
 * Отправка уведомлений о новом заказе еды в чаты и пользователей MAX
 * (только MAX_UI_STAND_* из .env, без кэша bot_started / webhook).
 */
class LaravelFoodOrderMaxNotifier implements FoodOrderMaxNotifierInterface
{
    public function __construct(
        private readonly MaxOrderNotificationConfigProviderInterface $configProvider,
        private readonly MaxUiStandRecipientResolverInterface $uiStandRecipientResolver,
        private readonly FoodOrderCustomerMaxMessageBuilderInterface $messageBuilder,
        private readonly MaxOpenAppButtonFactory $openAppButtonFactory,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(OrderDto $order, MaxUserDisplayDto $customer): void
    {
        $chatIds = $this->uiStandRecipientResolver->configuredChatIds();
        $userIds = $this->uiStandRecipientResolver->configuredUserIds();

        if ($chatIds === [] && $userIds === []) {
            $this->logger->warning(
                'MAX order notification skipped: UI Stand recipients are not configured',
                [
                    'order_id' => $order->id,
                ],
            );

            return;
        }

        $config = $this->configProvider->config();
        $text = $this->messageBuilder->build($order, $customer, $config->maxTextLength);
        $buttonRows = $this->openAppButtonFactory->buildGenericMiniAppButtonRows();

        $this->notificationSender->broadcastToUiStand(
            text: $text,
            buttonRows: $buttonRows,
            logContext: ['order_id' => $order->id],
            failureLogMessage: 'MAX order notification send failed',
        );
    }
}

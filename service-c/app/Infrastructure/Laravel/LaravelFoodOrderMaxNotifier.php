<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserDisplayDto;
use App\Services\Max\Food\FoodOrderMaxMessageBuilder;
use App\Support\Max\MaxOpenAppButtonFactory;
use Illuminate\Support\Facades\Log;

/**
 * Отправка уведомлений о новом заказе еды в чаты и пользователей MAX
 * (получатели UI Stand: MAX_UI_STAND_* и кэш webhook — как у «тест бот 2»).
 */
class LaravelFoodOrderMaxNotifier implements FoodOrderMaxNotifierInterface
{
    public function __construct(
        private readonly MaxOrderNotificationConfigProviderInterface $configProvider,
        private readonly MaxUiStandRecipientResolverInterface $uiStandRecipientResolver,
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
        private readonly MaxOpenAppButtonFactory $openAppButtonFactory,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(OrderDto $order, MaxUserDisplayDto $customer): void
    {
        $chatIds = $this->uiStandRecipientResolver->chatIds();
        $userIds = $this->uiStandRecipientResolver->userIds();

        if ($chatIds === [] && $userIds === []) {
            Log::channel('max_log')->warning(
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

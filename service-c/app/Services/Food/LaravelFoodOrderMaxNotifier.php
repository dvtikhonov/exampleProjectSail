<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\DTO\Food\OrderDto;
use App\Models\MaxUser;
use App\Support\MaxOpenAppTargetResolver;
use App\Support\MaxUiStandRecipientResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Отправка уведомлений о новом заказе еды в чаты и пользователей MAX
 * (получатели UI Stand: MAX_UI_STAND_* и кэш webhook — как у «тест бот 2»).
 */
class LaravelFoodOrderMaxNotifier implements FoodOrderMaxNotifierInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly MaxOrderNotificationConfigProviderInterface $configProvider,
        private readonly MaxUiStandRecipientResolver $uiStandRecipientResolver,
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
        private readonly MaxOpenAppTargetResolver $openAppTargetResolver,
        private readonly Repository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(OrderDto $order, MaxUser $maxUser): void
    {
        $chatIds = $this->uiStandRecipientResolver->chatIds();
        $userIds = $this->uiStandRecipientResolver->userIds();

        if ($chatIds === [] && $userIds === []) {
            Log::channel('messMax')->warning(
                'MAX order notification skipped: UI Stand recipients are not configured',
                [
                    'order_id' => $order->id,
                ],
            );

            return;
        }

        $config = $this->configProvider->config();
        $text = $this->messageBuilder->build($order, $maxUser, $config->maxTextLength);
        $buttonRows = $this->buildOpenAppButtonRows();

        foreach ($chatIds as $chatId) {
            $this->trySendNotification($text, $buttonRows, orderId: $order->id, chatId: $chatId);
        }

        foreach ($userIds as $userId) {
            $this->trySendNotification($text, $buttonRows, orderId: $order->id, userId: $userId);
        }
    }

    /**
     * Строит ряды кнопок открытия mini-app для уведомления о заказе.
     *
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    private function buildOpenAppButtonRows(): array
    {
        $webAppUrl = $this->openAppTargetResolver->resolveWebApp();

        if ($webAppUrl === null) {
            return [];
        }

        return [
            [
                new MaxInlineKeyboardButtonDto(
                    text: (string) $this->config->get('max.ui_stand.mini_app_button_text', 'Заказ еды'),
                    type: 'open_app',
                    webApp: $webAppUrl,
                    contactId: $this->openAppTargetResolver->resolveContactId(),
                ),
            ],
        ];
    }

    /**
     * Пытается отправить MAX-уведомление о заказе.
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    private function trySendNotification(
        string $text,
        array $buttonRows,
        int $orderId,
        ?int $chatId = null,
        ?int $userId = null,
    ): void {
        try {
            if ($buttonRows !== []) {
                $this->client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
                    text: $text,
                    buttonRows: $buttonRows,
                    chatId: $chatId,
                    userId: $userId,
                ));

                return;
            }

            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                chatId: $chatId,
                userId: $userId,
            ));
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX order notification send failed', [
                'order_id' => $orderId,
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->userMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX order notification send failed', [
                'order_id' => $orderId,
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

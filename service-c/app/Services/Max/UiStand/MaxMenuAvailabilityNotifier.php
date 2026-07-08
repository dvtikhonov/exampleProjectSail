<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Support\MaxOpenAppTargetResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Уведомление в MAX о доступности меню для заказов на завтра.
 */
class MaxMenuAvailabilityNotifier
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly Repository $config,
        private readonly MaxOpenAppTargetResolver $openAppTargetResolver,
    ) {}

    /**
     * Отправляет уведомление во все чаты из MAX_UI_STAND_CHAT_IDS.
     */
    public function notify(): void
    {
        $chatIds = $this->recipientChatIds();

        if ($chatIds === []) {
            Log::channel('messMax')->warning('MAX menu availability notification skipped: chat recipients are not configured');

            return;
        }

        $text = $this->buildMessageText();
        $buttonRows = $this->buildOpenAppButtonRows();

        foreach ($chatIds as $chatId) {
            $this->trySendNotification($text, $buttonRows, $chatId);
        }
    }

    private function buildMessageText(): string
    {
        $orderDate = CarbonImmutable::now(self::TIMEZONE)
            ->addDay()
            ->format('j.m.Y');

        return 'Доступно для заказов меню на '.$orderDate;
    }

    /**
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
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    private function trySendNotification(string $text, array $buttonRows, int $chatId): void
    {
        try {
            if ($buttonRows !== []) {
                $this->client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
                    text: $text,
                    buttonRows: $buttonRows,
                    chatId: $chatId,
                ));

                return;
            }

            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                chatId: $chatId,
            ));
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX menu availability notification send failed', [
                'chat_id' => $chatId,
                'error' => $exception->userMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX menu availability notification send failed', [
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function recipientChatIds(): array
    {
        return array_values(array_map(
            intval(...),
            (array) $this->config->get('max.ui_stand.recipient_chat_ids', []),
        ));
    }
}

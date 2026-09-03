<?php

declare(strict_types=1);

namespace App\Support\Max;

use Illuminate\Contracts\Config\Repository;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;

/**
 * Фабрика кнопок inline open_app для MAX-уведомлений.
 */
final class MaxOpenAppButtonFactory
{
    public function __construct(
        private readonly MaxOpenAppTargetResolver $openAppTargetResolver,
        private readonly Repository $config,
    ) {}

    /**
     * Строит ряд с кнопкой открытия mini-app (generic, без payload).
     *
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    public function buildGenericMiniAppButtonRows(?string $text = null): array
    {
        $webAppUrl = $this->openAppTargetResolver->resolveWebApp();

        if ($webAppUrl === null) {
            return [];
        }

        return [
            [
                new MaxInlineKeyboardButtonDto(
                    text: $text ?? (string) $this->config->get('max.ui_stand.mini_app_button_text', 'Заказ еды'),
                    type: 'open_app',
                    webApp: $webAppUrl,
                    contactId: $this->openAppTargetResolver->resolveContactId(),
                ),
            ],
        ];
    }

    /**
     * Строит ряд с кнопкой открытия чата заказа в mini-app.
     *
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    public function buildOrderChatButtonRows(int $orderId, string $startParam): array
    {
        $webAppUrl = $this->openAppTargetResolver->resolveWebApp();

        if ($webAppUrl === null) {
            return [];
        }

        return [
            [
                new MaxInlineKeyboardButtonDto(
                    text: sprintf('Открыть заказ №%d', $orderId),
                    type: 'open_app',
                    payload: $startParam,
                    webApp: $webAppUrl,
                    contactId: $this->openAppTargetResolver->resolveContactId(),
                ),
            ],
        ];
    }
}

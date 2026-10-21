<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Chat\FoodOrderChatMaxMessageBuilderInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\DTO\Telegram\TelegramInlineKeyboardButtonDto;

/**
 * Фабрика кнопок InlineKeyboard для Telegram Mini App в клиентских уведомлениях.
 *
 * При наличии bot_username — url с startapp (initDataUnsafe.start_param).
 * Иначе — web_app на TELEGRAM_MINI_APP_URL с query deep-link (order_id/view).
 */
final class TelegramWebAppButtonFactory
{
    public function __construct(
        private readonly ApplicationConfigInterface $config,
        private readonly FoodOrderChatMaxMessageBuilderInterface $chatMessageBuilder,
    ) {}

    /**
     * Строит ряд с кнопкой открытия Mini App (приветственный экран /start).
     *
     * @return array<int, array<int, TelegramInlineKeyboardButtonDto>>
     */
    public function buildGenericMiniAppButtonRows(?string $text = null): array
    {
        $miniAppUrl = trim((string) $this->config->get('telegram.mini_app_url', ''));

        if ($miniAppUrl === '') {
            return [];
        }

        $buttonText = $text ?? (string) $this->config->get(
            'telegram.ui_stand.mini_app_button_text',
            'Заказ еды',
        );

        return [
            [
                new TelegramInlineKeyboardButtonDto(
                    text: $buttonText,
                    webAppUrl: $miniAppUrl,
                ),
            ],
        ];
    }

    /**
     * Строит ряд с кнопкой открытия чата заказа в Mini App.
     *
     * @return array<int, array<int, TelegramInlineKeyboardButtonDto>>
     */
    public function buildOrderChatButtonRows(int $orderId, string $startParam): array
    {
        $miniAppUrl = trim((string) $this->config->get('telegram.mini_app_url', ''));

        if ($miniAppUrl === '') {
            return [];
        }

        $text = sprintf('Открыть заказ №%d', $orderId);
        $botUsername = ltrim(trim((string) $this->config->get('telegram.bot_username', '')), '@');

        if ($botUsername !== '') {
            return [
                [
                    new TelegramInlineKeyboardButtonDto(
                        text: $text,
                        url: sprintf(
                            'https://t.me/%s?startapp=%s',
                            $botUsername,
                            rawurlencode($startParam),
                        ),
                    ),
                ],
            ];
        }

        $webAppUrl = $this->chatMessageBuilder->buildOrderChatOpenAppUrl($orderId, $miniAppUrl)
            ?? $miniAppUrl;

        return [
            [
                new TelegramInlineKeyboardButtonDto(
                    text: $text,
                    webAppUrl: $webAppUrl,
                ),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

/**
 * Сборка текста MAX-уведомлений о заказе.
 */
final class FoodOrderMaxTextAssembler
{
    public const DEFAULT_MAX_TEXT_LENGTH = 4000;

    private const ORDER_CHAT_PREVIEW_MAX_LENGTH = 200;

    /**
     * Обрезает превью текста чата до лимита.
     */
    public function truncateChatPreview(string $body): string
    {
        $normalized = trim($body);

        if (mb_strlen($normalized) <= self::ORDER_CHAT_PREVIEW_MAX_LENGTH) {
            return $normalized;
        }

        return mb_substr($normalized, 0, self::ORDER_CHAT_PREVIEW_MAX_LENGTH - 1).'…';
    }

    /**
     * Склеивает части MAX-сообщения о заказе.
     */
    public function assembleMessage(string $header, string $itemsSection, string $footer): string
    {
        $sections = [$header];

        if ($itemsSection !== '') {
            $sections[] = $itemsSection;
        }

        $sections[] = $footer;

        return implode("\n\n", $sections);
    }
}

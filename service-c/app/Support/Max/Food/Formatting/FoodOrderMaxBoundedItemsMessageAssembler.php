<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

/**
 * Сборка MAX-сообщения с полным списком позиций (без усечения названий и строк).
 */
final class FoodOrderMaxBoundedItemsMessageAssembler
{
    public function __construct(
        private readonly FoodOrderMaxTextAssembler $textAssembler,
        private readonly FoodOrderMaxBulletItemsFormatter $bulletItemsFormatter,
    ) {}

    /**
     * Собирает сообщение header/items/footer с bullet-позициями целиком.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function assembleWithBulletItems(
        string $header,
        array $items,
        string $footer,
        int $maxTextLength = FoodOrderMaxTextAssembler::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        unset($maxTextLength);

        $itemsSection = $items === []
            ? ''
            : implode("\n", $this->bulletItemsFormatter->formatItemsLines($items));

        return $this->textAssembler->assembleMessage($header, $itemsSection, $footer);
    }

    /**
     * Собирает сообщение из заголовка и готовых строк позиций целиком.
     *
     * @param  list<string>  $itemLines
     */
    public function assembleWithPrefixedItemLines(
        string $header,
        array $itemLines,
        int $maxTextLength = FoodOrderMaxTextAssembler::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        unset($maxTextLength);

        if ($itemLines === []) {
            return $header;
        }

        return $header."\n".implode("\n", $itemLines);
    }
}

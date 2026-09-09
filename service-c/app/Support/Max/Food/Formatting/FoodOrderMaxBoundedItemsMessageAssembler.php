<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

/**
 * Сборка MAX-сообщения с усечением списка позиций под лимит длины.
 */
final class FoodOrderMaxBoundedItemsMessageAssembler
{
    public function __construct(
        private readonly FoodOrderMaxTextAssembler $textAssembler,
        private readonly FoodOrderMaxBulletItemsFormatter $bulletItemsFormatter,
    ) {}

    /**
     * Собирает сообщение header/items/footer с bullet-позициями и усечением.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function assembleWithBulletItems(
        string $header,
        array $items,
        string $footer,
        int $maxTextLength,
    ): string {
        if ($items === []) {
            return $this->textAssembler->ensureWithinLimit(
                $this->textAssembler->assembleMessage($header, '', $footer),
                $maxTextLength,
            );
        }

        $fullItemsSection = implode("\n", $this->bulletItemsFormatter->formatItemsLines($items));
        $fullText = $this->textAssembler->assembleMessage($header, $fullItemsSection, $footer);

        if (mb_strlen($fullText) <= $maxTextLength) {
            return $fullText;
        }

        $totalItems = count($items);

        for ($includedCount = $totalItems - 1; $includedCount >= 0; $includedCount--) {
            $remaining = $totalItems - $includedCount;
            $itemsSection = $this->bulletItemsFormatter->buildItemsSection($items, $includedCount, $remaining);
            $candidate = $this->textAssembler->assembleMessage($header, $itemsSection, $footer);

            if (mb_strlen($candidate) <= $maxTextLength) {
                return $candidate;
            }
        }

        return $this->textAssembler->ensureWithinLimit(
            $this->textAssembler->assembleMessage(
                $header,
                $this->textAssembler->buildTruncationSuffix($totalItems),
                $footer,
            ),
            $maxTextLength,
        );
    }

    /**
     * Собирает сообщение из заголовка и готовых строк позиций с усечением.
     *
     * @param  list<string>  $itemLines
     */
    public function assembleWithPrefixedItemLines(
        string $header,
        array $itemLines,
        int $maxTextLength,
    ): string {
        if ($itemLines === []) {
            return $this->textAssembler->ensureWithinLimit($header, $maxTextLength);
        }

        $fullText = $header."\n".implode("\n", $itemLines);

        if (mb_strlen($fullText) <= $maxTextLength) {
            return $fullText;
        }

        $totalLines = count($itemLines);

        for ($includedCount = $totalLines - 1; $includedCount >= 0; $includedCount--) {
            $remaining = $totalLines - $includedCount;
            $lines = array_slice($itemLines, 0, $includedCount);
            $lines[] = $this->textAssembler->buildTruncationSuffix($remaining);
            $candidate = $header."\n".implode("\n", $lines);

            if (mb_strlen($candidate) <= $maxTextLength) {
                return $candidate;
            }
        }

        return $this->textAssembler->ensureWithinLimit(
            $header."\n".$this->textAssembler->buildTruncationSuffix($totalLines),
            $maxTextLength,
        );
    }
}

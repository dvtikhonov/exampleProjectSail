<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\PhotoText\PhotoTextComboRefGrouperInterface;
use App\DTO\Food\PhotoText\PhotoTextAgentItemDto;
use App\DTO\Food\PhotoText\PhotoTextComboRefGroupDto;
use App\Enums\Food\PhotoText\PhotoTextComboRefGroupKind;

/**
 * Группировка JSON-позиций агента: одиночные без combo_ref, пары с общим UUID.
 */
class PhotoTextComboRefGrouper implements PhotoTextComboRefGrouperInterface
{
    /**
     * {@inheritDoc}
     */
    public function group(array $items): array
    {
        $groups = [];
        $itemsByRef = [];
        /** @var list<array{kind: 'single', item: PhotoTextAgentItemDto}|array{kind: 'ref', ref: string}> $order */
        $order = [];

        foreach ($items as $item) {
            if (! $item instanceof PhotoTextAgentItemDto) {
                continue;
            }

            $comboRef = $this->normalizedComboRef($item->comboRef);

            if ($comboRef === null) {
                $order[] = ['kind' => 'single', 'item' => $item];

                continue;
            }

            if (! array_key_exists($comboRef, $itemsByRef)) {
                $itemsByRef[$comboRef] = [];
                $order[] = ['kind' => 'ref', 'ref' => $comboRef];
            }

            $itemsByRef[$comboRef][] = $item;
        }

        foreach ($order as $entry) {
            if ($entry['kind'] === 'single') {
                $groups[] = new PhotoTextComboRefGroupDto(
                    kind: PhotoTextComboRefGroupKind::Single,
                    items: [$entry['item']],
                );

                continue;
            }

            $bucket = $itemsByRef[$entry['ref']];
            $groups[] = new PhotoTextComboRefGroupDto(
                kind: $this->kindForBucket($bucket),
                items: $bucket,
            );
        }

        return $groups;
    }

    /**
     * Пара: ровно две позиции с одинаковым quantity; иначе Unresolved.
     *
     * @param  list<PhotoTextAgentItemDto>  $bucket
     */
    private function kindForBucket(array $bucket): PhotoTextComboRefGroupKind
    {
        if (count($bucket) !== 2) {
            return PhotoTextComboRefGroupKind::Unresolved;
        }

        if ($bucket[0]->quantity !== $bucket[1]->quantity) {
            return PhotoTextComboRefGroupKind::Unresolved;
        }

        return PhotoTextComboRefGroupKind::Pair;
    }

    /**
     * Нормализует combo_ref: пустая строка трактуется как отсутствие пары.
     */
    private function normalizedComboRef(?string $comboRef): ?string
    {
        if ($comboRef === null) {
            return null;
        }

        $trimmed = trim($comboRef);

        return $trimmed === '' ? null : $trimmed;
    }
}

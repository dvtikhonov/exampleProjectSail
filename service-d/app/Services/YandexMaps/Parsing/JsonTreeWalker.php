<?php

declare(strict_types=1);

namespace App\Services\YandexMaps\Parsing;

/**
 * Depth-first walk over JSON trees decoded from Yandex Maps network payloads.
 */
class JsonTreeWalker
{
    /**
     * @param  callable(array<string, mixed>, string[]): void  $visitor
     * @param  string[]  $path
     */
    public function walk(mixed $node, callable $visitor, array $path = []): void
    {
        if (! is_array($node)) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $index => $item) {
                $this->walk($item, $visitor, [...$path, (string) $index]);
            }

            return;
        }

        /** @var array<string, mixed> $record */
        $record = $node;
        $visitor($record, $path);

        foreach ($record as $key => $value) {
            $this->walk($value, $visitor, [...$path, (string) $key]);
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  string[]  $keys
     */
    public function pickString(array $record, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $record[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  string[]  $keys
     * @return array<string, mixed>|null
     */
    public function pickRecord(array $record, array $keys): ?array
    {
        foreach ($keys as $key) {
            $value = $record[$key] ?? null;

            if (is_array($value) && ! array_is_list($value)) {
                return $value;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Soft DTO входящего Update webhook MAX (см. https://dev.max.ru/docs-api/objects/Update).
 *
 * Невалидный payload → null (контроллер отвечает 200 и игнорирует).
 * Неизвестные поля сохраняются в {@see $payload} для обработчиков.
 */
readonly class MaxWebhookUpdateDto
{
    /**
     * @param  array<string, mixed>  $payload  исходный ассоциативный массив Update
     */
    public function __construct(
        public string $updateType,
        public ?int $timestamp,
        public array $payload,
    ) {}

    /**
     * Мягкий разбор raw JSON/массива. Возвращает null при отсутствии валидного update_type.
     */
    public static function tryFrom(mixed $raw): ?self
    {
        if (! is_array($raw)) {
            return null;
        }

        $updateType = $raw['update_type'] ?? null;
        if (! is_string($updateType) || $updateType === '') {
            return null;
        }

        $timestamp = null;
        if (array_key_exists('timestamp', $raw)) {
            $rawTimestamp = $raw['timestamp'];
            if (is_int($rawTimestamp)) {
                $timestamp = $rawTimestamp;
            } elseif (is_float($rawTimestamp) || (is_string($rawTimestamp) && is_numeric($rawTimestamp))) {
                $timestamp = (int) $rawTimestamp;
            }
            // нечисловой timestamp игнорируем (оставляем null), DTO принимаем
        }

        /** @var array<string, mixed> $payload */
        $payload = $raw;

        return new self(
            updateType: $updateType,
            timestamp: $timestamp,
            payload: $payload,
        );
    }
}

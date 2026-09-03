<?php

declare(strict_types=1);

namespace App\DTO\Food\Order;

use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use DateTimeInterface;

/**
 * Частичное обновление заказа еды: заданы только изменяемые поля.
 */
readonly class FoodOrderUpdateCommand
{
    /**
     * @param  list<array<string, mixed>>|null  $itemsSnapshot
     */
    public function __construct(
        public ?OrderStatus $status = null,
        public ?OrderReviewStatus $addressReviewStatus = null,
        public ?OrderReviewStatus $compositionReviewStatus = null,
        public ?OrderReviewStatus $paymentReviewStatus = null,
        public ?int $addressReviewedBy = null,
        public ?string $addressReviewedAt = null,
        public ?int $compositionReviewedBy = null,
        public ?string $compositionReviewedAt = null,
        public ?string $addressRejectionComment = null,
        public ?string $compositionRejectionComment = null,
        public ?int $paymentReviewedBy = null,
        public ?string $paymentReviewedAt = null,
        public ?string $paymentRejectionComment = null,
        public ?array $itemsSnapshot = null,
        public ?string $itemsTotal = null,
        public ?string $deliveryCost = null,
        public ?string $total = null,
    ) {}

    /**
     * Собирает команду из ассоциативного массива атрибутов (мост для legacy-вызовов).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function fromAttributes(array $attributes): self
    {
        return new self(
            status: self::enumOrNull($attributes, 'status', OrderStatus::class),
            addressReviewStatus: self::enumOrNull($attributes, 'address_review_status', OrderReviewStatus::class),
            compositionReviewStatus: self::enumOrNull($attributes, 'composition_review_status', OrderReviewStatus::class),
            paymentReviewStatus: self::enumOrNull($attributes, 'payment_review_status', OrderReviewStatus::class),
            addressReviewedBy: self::intOrNull($attributes, 'address_reviewed_by'),
            addressReviewedAt: self::datetimeOrNull($attributes, 'address_reviewed_at'),
            compositionReviewedBy: self::intOrNull($attributes, 'composition_reviewed_by'),
            compositionReviewedAt: self::datetimeOrNull($attributes, 'composition_reviewed_at'),
            addressRejectionComment: self::stringOrNull($attributes, 'address_rejection_comment'),
            compositionRejectionComment: self::stringOrNull($attributes, 'composition_rejection_comment'),
            paymentReviewedBy: self::intOrNull($attributes, 'payment_reviewed_by'),
            paymentReviewedAt: self::datetimeOrNull($attributes, 'payment_reviewed_at'),
            paymentRejectionComment: self::stringOrNull($attributes, 'payment_rejection_comment'),
            itemsSnapshot: self::arrayOrNull($attributes, 'items_snapshot'),
            itemsTotal: self::stringOrNull($attributes, 'items_total'),
            deliveryCost: self::stringOrNull($attributes, 'delivery_cost'),
            total: self::stringOrNull($attributes, 'total'),
        );
    }

    /**
     * @param  class-string<OrderStatus|OrderReviewStatus>  $enumClass
     */
    private static function enumOrNull(array $attributes, string $key, string $enumClass): OrderStatus|OrderReviewStatus|null
    {
        if (! array_key_exists($key, $attributes)) {
            return null;
        }

        $value = $attributes[$key];

        if ($value instanceof $enumClass) {
            return $value;
        }

        if (is_string($value)) {
            return $enumClass::from($value);
        }

        return null;
    }

    private static function intOrNull(array $attributes, string $key): ?int
    {
        if (! array_key_exists($key, $attributes) || $attributes[$key] === null) {
            return null;
        }

        return (int) $attributes[$key];
    }

    private static function stringOrNull(array $attributes, string $key): ?string
    {
        if (! array_key_exists($key, $attributes) || $attributes[$key] === null) {
            return null;
        }

        return (string) $attributes[$key];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private static function arrayOrNull(array $attributes, string $key): ?array
    {
        if (! array_key_exists($key, $attributes) || ! is_array($attributes[$key])) {
            return null;
        }

        return $attributes[$key];
    }

    private static function datetimeOrNull(array $attributes, string $key): ?string
    {
        if (! array_key_exists($key, $attributes) || $attributes[$key] === null) {
            return null;
        }

        $value = $attributes[$key];

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return (string) $value;
    }
}

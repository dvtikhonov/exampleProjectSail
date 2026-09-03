<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\DTO\Food\Order\FoodOrderCreateCommand;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\Models\Food\FoodOrder;
use DateTimeInterface;

/**
 * Преобразование между Eloquent-моделью заказа и доменными Record/Command.
 */
class FoodOrderMapper
{
    /**
     * Преобразует модель заказа в доменную проекцию.
     */
    public function toRecord(FoodOrder $model): FoodOrderRecord
    {
        return new FoodOrderRecord(
            id: (int) $model->id,
            cartId: $model->cart_id !== null ? (int) $model->cart_id : null,
            maxUserId: (int) $model->max_user_id,
            isManual: (bool) $model->is_manual,
            createdByMaxUserId: $model->created_by_max_user_id !== null
                ? (int) $model->created_by_max_user_id
                : null,
            restaurantId: (int) $model->restaurant_id,
            status: $model->status,
            addressReviewStatus: $model->address_review_status,
            compositionReviewStatus: $model->composition_review_status,
            paymentReviewStatus: $model->payment_review_status,
            addressReviewedBy: $model->address_reviewed_by !== null
                ? (int) $model->address_reviewed_by
                : null,
            addressReviewedAt: $this->formatDateTime($model->address_reviewed_at),
            compositionReviewedBy: $model->composition_reviewed_by !== null
                ? (int) $model->composition_reviewed_by
                : null,
            compositionReviewedAt: $this->formatDateTime($model->composition_reviewed_at),
            addressRejectionComment: $model->address_rejection_comment,
            compositionRejectionComment: $model->composition_rejection_comment,
            paymentReviewedBy: $model->payment_reviewed_by !== null
                ? (int) $model->payment_reviewed_by
                : null,
            paymentReviewedAt: $this->formatDateTime($model->payment_reviewed_at),
            paymentRejectionComment: $model->payment_rejection_comment,
            total: (string) $model->total,
            deliveryAddress: $model->delivery_address,
            deliveryDate: $model->delivery_date?->format('Y-m-d'),
            deliveryCost: $model->delivery_cost !== null ? (string) $model->delivery_cost : null,
            itemsTotal: (string) $model->items_total,
            itemsSnapshot: is_array($model->items_snapshot) ? $model->items_snapshot : [],
            createdAt: $model->created_at?->toIso8601String() ?? now()->toIso8601String(),
            updatedAt: $model->updated_at?->toIso8601String(),
            restaurantName: $model->relationLoaded('restaurant')
                ? ($model->restaurant?->name !== null ? (string) $model->restaurant->name : null)
                : null,
            customerFirstName: $model->relationLoaded('maxUser') ? $model->maxUser?->first_name : null,
            customerLastName: $model->relationLoaded('maxUser') ? $model->maxUser?->last_name : null,
            customerUsername: $model->relationLoaded('maxUser') ? $model->maxUser?->username : null,
            hasMessages: array_key_exists('messages_exists', $model->getAttributes())
                ? (bool) $model->messages_exists
                : null,
        );
    }

    /**
     * Преобразует команду создания в атрибуты Eloquent.
     *
     * @return array<string, mixed>
     */
    public function toCreateAttributes(FoodOrderCreateCommand $command): array
    {
        return [
            'cart_id' => $command->cartId,
            'max_user_id' => $command->maxUserId,
            'is_manual' => $command->isManual,
            'created_by_max_user_id' => $command->createdByMaxUserId,
            'restaurant_id' => $command->restaurantId,
            'status' => $command->status,
            'address_review_status' => $command->addressReviewStatus,
            'composition_review_status' => $command->compositionReviewStatus,
            'payment_review_status' => $command->paymentReviewStatus,
            'address_reviewed_by' => $command->addressReviewedBy,
            'address_reviewed_at' => $command->addressReviewedAt,
            'composition_reviewed_by' => $command->compositionReviewedBy,
            'composition_reviewed_at' => $command->compositionReviewedAt,
            'payment_reviewed_by' => $command->paymentReviewedBy,
            'payment_reviewed_at' => $command->paymentReviewedAt,
            'total' => $command->total,
            'delivery_address' => $command->deliveryAddress,
            'delivery_date' => $command->deliveryDate,
            'delivery_cost' => $command->deliveryCost,
            'items_total' => $command->itemsTotal,
            'items_snapshot' => $command->itemsSnapshot,
        ];
    }

    /**
     * Преобразует команду обновления в атрибуты Eloquent (только заданные поля).
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(FoodOrderUpdateCommand $command): array
    {
        $attributes = [];

        if ($command->status !== null) {
            $attributes['status'] = $command->status;
        }

        if ($command->addressReviewStatus !== null) {
            $attributes['address_review_status'] = $command->addressReviewStatus;
        }

        if ($command->compositionReviewStatus !== null) {
            $attributes['composition_review_status'] = $command->compositionReviewStatus;
        }

        if ($command->paymentReviewStatus !== null) {
            $attributes['payment_review_status'] = $command->paymentReviewStatus;
        }

        if ($command->addressReviewedBy !== null) {
            $attributes['address_reviewed_by'] = $command->addressReviewedBy;
        }

        if ($command->addressReviewedAt !== null) {
            $attributes['address_reviewed_at'] = $command->addressReviewedAt;
        }

        if ($command->compositionReviewedBy !== null) {
            $attributes['composition_reviewed_by'] = $command->compositionReviewedBy;
        }

        if ($command->compositionReviewedAt !== null) {
            $attributes['composition_reviewed_at'] = $command->compositionReviewedAt;
        }

        if ($command->addressRejectionComment !== null) {
            $attributes['address_rejection_comment'] = $command->addressRejectionComment;
        }

        if ($command->compositionRejectionComment !== null) {
            $attributes['composition_rejection_comment'] = $command->compositionRejectionComment;
        }

        if ($command->paymentReviewedBy !== null) {
            $attributes['payment_reviewed_by'] = $command->paymentReviewedBy;
        }

        if ($command->paymentReviewedAt !== null) {
            $attributes['payment_reviewed_at'] = $command->paymentReviewedAt;
        }

        if ($command->paymentRejectionComment !== null) {
            $attributes['payment_rejection_comment'] = $command->paymentRejectionComment;
        }

        if ($command->itemsSnapshot !== null) {
            $attributes['items_snapshot'] = $command->itemsSnapshot;
        }

        if ($command->itemsTotal !== null) {
            $attributes['items_total'] = $command->itemsTotal;
        }

        if ($command->deliveryCost !== null) {
            $attributes['delivery_cost'] = $command->deliveryCost;
        }

        if ($command->total !== null) {
            $attributes['total'] = $command->total;
        }

        return $attributes;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return (string) $value;
    }
}

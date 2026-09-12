<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Contracts;

/**
 * Доставка файла отчёта Food пользователю MAX (Bot API: upload + message attachment).
 */
interface FoodReportMaxDeliveryInterface
{
    /**
     * Загружает файл и отправляет его в диалог пользователя MAX.
     *
     * @param  int  $maxUserId  получатель (user_id Bot API)
     * @param  string  $binary  содержимое .xlsx
     * @param  string  $fileName  имя вложения
     * @param  string  $text  подпись к сообщению
     */
    public function deliver(int $maxUserId, string $binary, string $fileName, string $text): void;
}

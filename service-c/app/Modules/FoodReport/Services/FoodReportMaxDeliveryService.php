<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Services;

use App\Modules\FoodReport\Contracts\FoodReportMaxDeliveryInterface;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;

/**
 * Отправка .xlsx отчёта Food в диалог пользователя MAX через Bot API.
 *
 * @see https://dev.max.ru/docs-api
 */
final class FoodReportMaxDeliveryService implements FoodReportMaxDeliveryInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function deliver(int $maxUserId, string $binary, string $fileName, string $text): void
    {
        $fileToken = $this->client->uploadFile($binary, $fileName);

        $this->client->sendMessage(new MaxMessageDto(
            text: $text,
            userId: $maxUserId,
            fileAttachmentToken: $fileToken,
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Services;

use App\Modules\FoodReport\Contracts\FoodReportMaxDeliveryInterface;
use Shared\MaxMessenger\Client\NullMaxMessengerClient;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;

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
     *
     * @throws MaxMessengerRequestException если messenger driver = null (NullMaxMessengerClient)
     */
    public function deliver(int $maxUserId, string $binary, string $fileName, string $text): void
    {
        if ($this->client instanceof NullMaxMessengerClient) {
            throw new MaxMessengerRequestException(
                'Доставка в MAX недоступна (messenger driver = null)',
            );
        }

        $fileToken = $this->client->uploadFile($binary, $fileName);

        $this->client->sendMessage(new MaxMessageDto(
            text: $text,
            userId: $maxUserId,
            fileAttachmentToken: $fileToken,
        ));
    }
}

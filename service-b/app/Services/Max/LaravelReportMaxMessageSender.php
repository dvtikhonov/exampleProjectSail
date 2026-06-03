<?php

namespace App\Services\Max;

use App\Contracts\Max\MaxMessengerClientInterface;
use App\Contracts\Max\MaxReportConfigProviderInterface;
use App\Contracts\Max\ReportMaxMessageSenderInterface;
use App\DTO\Max\MaxMessageDto;
use InvalidArgumentException;

class LaravelReportMaxMessageSender implements ReportMaxMessageSenderInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly MaxReportConfigProviderInterface $configProvider,
    ) {}

    public function send(string $text, string $csvContent, string $fileName): void
    {
        $config = $this->configProvider->config();

        if (mb_strlen($text) > $config->maxTextLength) {
            throw new InvalidArgumentException(
                "MAX message text exceeds {$config->maxTextLength} characters.",
            );
        }

        $fileToken = $this->client->uploadFile($csvContent, $fileName);

        $recipients = [
            ...array_map(
                static fn (int $chatId): MaxMessageDto => new MaxMessageDto(
                    text: $text,
                    chatId: $chatId,
                    fileAttachmentToken: $fileToken,
                ),
                $config->chatIds,
            ),
            ...array_map(
                static fn (int $userId): MaxMessageDto => new MaxMessageDto(
                    text: $text,
                    userId: $userId,
                    fileAttachmentToken: $fileToken,
                ),
                $config->userIds,
            ),
        ];

        $lastIndex = count($recipients) - 1;

        foreach ($recipients as $index => $message) {
            $this->client->sendMessage($message);

            if ($index < $lastIndex && $config->interRecipientDelayMs > 0) {
                usleep($config->interRecipientDelayMs * 1000);
            }
        }
    }
}

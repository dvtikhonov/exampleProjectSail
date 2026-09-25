<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Services;

use App\Contracts\Shared\ClockInterface;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;
use App\Modules\MaxIncomingRelay\DTO\LastOrderSummaryDto;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Сборка многострочного текста уведомления о входящем сообщении боту.
 */
final class IncomingMessageNotificationBuilder
{
    private const string TIMEZONE = 'Europe/Moscow';

    private const string DATETIME_FORMAT = 'd.m.Y H:i';

    private const string DATE_FORMAT = 'd.m.Y';

    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Формирует текст в формате Home_chat / max_log.
     */
    public function build(IncomingBotMessageDto $message, ?LastOrderSummaryDto $lastOrder): string
    {
        $header = sprintf('Получено сообщение от user_id %d', $message->userId);
        $fullName = $this->formatFullName($message->firstName, $message->lastName);
        if ($fullName !== '') {
            $header .= ' '.$fullName;
        }

        $occurredAt = $this->resolveOccurredAt($message->timestampMs);
        $orderLine = $this->formatLastOrderLine($lastOrder);

        return implode("\n", [
            $header,
            'текст сообщения: '.$message->text,
            'Дата и время: '.$occurredAt->format(self::DATETIME_FORMAT),
            'Дата и номер последнего заказа: '.$orderLine,
        ]);
    }

    private function formatFullName(string $firstName, string $lastName): string
    {
        $parts = array_values(array_filter(
            [trim($firstName), trim($lastName)],
            static fn (string $part): bool => $part !== '',
        ));

        return implode(' ', $parts);
    }

    private function resolveOccurredAt(int $timestampMs): DateTimeImmutable
    {
        $timezone = new DateTimeZone(self::TIMEZONE);

        if ($timestampMs > 0) {
            $seconds = intdiv($timestampMs, 1000);

            return (new DateTimeImmutable('@'.$seconds))->setTimezone($timezone);
        }

        return $this->clock->now()->setTimezone($timezone);
    }

    private function formatLastOrderLine(?LastOrderSummaryDto $lastOrder): string
    {
        if ($lastOrder === null) {
            return 'нет';
        }

        $orderDate = $lastOrder->createdAt
            ->setTimezone(new DateTimeZone(self::TIMEZONE))
            ->format(self::DATE_FORMAT);

        return sprintf('%s №%d', $orderDate, $lastOrder->id);
    }
}

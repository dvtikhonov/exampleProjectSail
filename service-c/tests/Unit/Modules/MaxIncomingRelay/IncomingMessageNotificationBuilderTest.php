<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MaxIncomingRelay;

use App\Contracts\Shared\ClockInterface;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;
use App\Modules\MaxIncomingRelay\DTO\LastOrderSummaryDto;
use App\Modules\MaxIncomingRelay\Services\IncomingMessageNotificationBuilder;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

/**
 * Unit: формат текста уведомления Home_chat / max_log.
 */
final class IncomingMessageNotificationBuilderTest extends TestCase
{
    /** С ФИО и последним заказом — полный формат. */
    public function test_build_with_full_name_and_last_order(): void
    {
        $builder = new IncomingMessageNotificationBuilder($this->clockStub());

        $text = $builder->build(
            new IncomingBotMessageDto(
                userId: 54321,
                firstName: 'Иван',
                lastName: 'Петров',
                text: 'Здравствуйте, хочу заказ',
                timestampMs: 1_790_152_800_000,
                chatId: -100000000,
            ),
            new LastOrderSummaryDto(
                id: 1842,
                createdAt: new DateTimeImmutable('2026-09-20 12:00:00', new DateTimeZone('Europe/Moscow')),
            ),
        );

        $this->assertSame(
            implode("\n", [
                'Получено сообщение от user_id 54321 Иван Петров',
                'текст сообщения: Здравствуйте, хочу заказ',
                'Дата и время: 23.09.2026 11:40',
                'Дата и номер последнего заказа: 20.09.2026 №1842',
            ]),
            $text,
        );
    }

    /** Без ФИО и без заказа → «нет». */
    public function test_build_without_name_and_without_order(): void
    {
        $builder = new IncomingMessageNotificationBuilder($this->clockStub());

        $text = $builder->build(
            new IncomingBotMessageDto(
                userId: 54321,
                firstName: '',
                lastName: '',
                text: 'Привет',
                timestampMs: 1_790_152_860_000,
                chatId: null,
            ),
            null,
        );

        $this->assertSame(
            implode("\n", [
                'Получено сообщение от user_id 54321',
                'текст сообщения: Привет',
                'Дата и время: 23.09.2026 11:41',
                'Дата и номер последнего заказа: нет',
            ]),
            $text,
        );
    }

    /** Только имя (без фамилии) дописывается после user_id. */
    public function test_build_with_first_name_only(): void
    {
        $builder = new IncomingMessageNotificationBuilder($this->clockStub());

        $text = $builder->build(
            new IncomingBotMessageDto(
                userId: 1,
                firstName: 'Анна',
                lastName: '  ',
                text: '',
                timestampMs: 1_790_152_800_000,
                chatId: null,
            ),
            null,
        );

        $this->assertStringStartsWith('Получено сообщение от user_id 1 Анна'."\n", $text);
        $this->assertStringContainsString('текст сообщения: '."\n", $text);
    }

    /** timestampMs = 0 → текущее время из ClockInterface (Europe/Moscow). */
    public function test_build_falls_back_to_clock_when_timestamp_missing(): void
    {
        $now = new DateTimeImmutable('2026-01-15 09:05:00', new DateTimeZone('UTC'));
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $builder = new IncomingMessageNotificationBuilder($clock);

        $text = $builder->build(
            new IncomingBotMessageDto(
                userId: 7,
                firstName: '',
                lastName: '',
                text: 'x',
                timestampMs: 0,
                chatId: null,
            ),
            null,
        );

        $this->assertStringContainsString('Дата и время: 15.01.2026 12:05', $text);
    }

    private function clockStub(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC')));

        return $clock;
    }
}

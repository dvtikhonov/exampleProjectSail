<?php

namespace Tests\Unit;

use App\Contracts\Max\MaxMessengerClientInterface;
use App\Contracts\Max\MaxReportConfigProviderInterface;
use App\DTO\Max\MaxMessageDto;
use App\DTO\Max\MaxReportConfig;
use App\Exceptions\Max\MaxMessengerAuthException;
use App\Services\Max\LaravelReportMaxMessageSender;
use InvalidArgumentException;
use Tests\TestCase;

class LaravelReportMaxMessageSenderTest extends TestCase
{
    public function test_send_uploads_csv_once_and_calls_client_for_each_recipient(): void
    {
        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('uploadFile')
            ->with("\xEF\xBB\xBFcsv", 'report.csv')
            ->willReturn('file-token-xyz');
        $client
            ->expects($this->exactly(3))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });

        $sender = new LaravelReportMaxMessageSender(
            client: $client,
            configProvider: $this->makeConfigProvider(new MaxReportConfig(
                chatIds: [111, 222],
                userIds: [333],
                intro: 'Intro',
                maxTextLength: 4000,
                rateLimitRetryMax: 2,
                rateLimitRetryDelayMs: 0,
                interRecipientDelayMs: 0,
            )),
        );

        $sender->send('Объекты продаж — отчёт', "\xEF\xBB\xBFcsv", 'report.csv');

        $this->assertCount(3, $sentMessages);
        $this->assertSame([111, 222], array_map(
            static fn (MaxMessageDto $dto): ?int => $dto->chatId,
            array_filter($sentMessages, static fn (MaxMessageDto $dto): bool => $dto->chatId !== null),
        ));
        $this->assertSame(333, $sentMessages[2]->userId);
        $this->assertSame('Объекты продаж — отчёт', $sentMessages[0]->text);
        $this->assertSame('file-token-xyz', $sentMessages[0]->fileAttachmentToken);
    }

    public function test_first_recipient_auth_failure_stops_remaining_recipients(): void
    {
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('uploadFile')
            ->willReturn('file-token');
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willThrowException(new MaxMessengerAuthException);

        $sender = new LaravelReportMaxMessageSender(
            client: $client,
            configProvider: $this->makeConfigProvider(new MaxReportConfig(
                chatIds: [111, 222],
                userIds: [333],
                intro: 'Intro',
                maxTextLength: 4000,
                rateLimitRetryMax: 2,
                rateLimitRetryDelayMs: 0,
                interRecipientDelayMs: 0,
            )),
        );

        $this->expectException(MaxMessengerAuthException::class);

        $sender->send('Объекты продаж — отчёт', 'csv', 'report.csv');
    }

    public function test_text_exceeding_max_length_is_rejected_before_client_call(): void
    {
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->never())->method('uploadFile');
        $client->expects($this->never())->method('sendMessage');

        $sender = new LaravelReportMaxMessageSender(
            client: $client,
            configProvider: $this->makeConfigProvider(new MaxReportConfig(
                chatIds: [111],
                userIds: [],
                intro: 'Intro',
                maxTextLength: 4000,
                rateLimitRetryMax: 2,
                rateLimitRetryDelayMs: 0,
                interRecipientDelayMs: 0,
            )),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MAX message text exceeds 4000 characters.');

        $sender->send(str_repeat('x', 4001), 'csv', 'report.csv');
    }

    public function test_client_is_called_sequentially_for_multiple_recipients(): void
    {
        $callOrder = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->method('uploadFile')->willReturn('file-token');
        $client
            ->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$callOrder): void {
                $callOrder[] = $message->chatId ?? $message->userId;
            });

        $sender = new LaravelReportMaxMessageSender(
            client: $client,
            configProvider: $this->makeConfigProvider(new MaxReportConfig(
                chatIds: [10, 20],
                userIds: [],
                intro: 'Intro',
                maxTextLength: 4000,
                rateLimitRetryMax: 2,
                rateLimitRetryDelayMs: 0,
                interRecipientDelayMs: 0,
            )),
        );

        $sender->send('Объекты продаж — отчёт', 'csv', 'report.csv');

        $this->assertSame([10, 20], $callOrder);
    }

    private function makeConfigProvider(MaxReportConfig $config): MaxReportConfigProviderInterface
    {
        $provider = $this->createMock(MaxReportConfigProviderInterface::class);
        $provider->method('config')->willReturn($config);

        return $provider;
    }
}

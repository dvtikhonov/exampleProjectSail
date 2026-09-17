<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FoodReport;

use App\Modules\FoodReport\Services\FoodReportMaxDeliveryService;
use PHPUnit\Framework\TestCase;
use Shared\MaxMessenger\Client\NullMaxMessengerClient;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;

/**
 * Unit: FoodReportMaxDeliveryService — upload + sendMessage с file attachment.
 */
final class FoodReportMaxDeliveryServiceTest extends TestCase
{
    /** uploadFile один раз, sendMessage с token и user_id. */
    public function test_deliver_uploads_file_and_sends_message_to_user(): void
    {
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->once())
            ->method('uploadFile')
            ->with("PK\x03\x04binary", 'report_1_2026-09-01_2026-09-10.xlsx')
            ->willReturn('uploaded-token');

        $client->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(static function (MaxMessageDto $message): bool {
                return $message->userId === 20_020
                    && $message->chatId === null
                    && $message->fileAttachmentToken === 'uploaded-token'
                    && $message->text === 'Отчёт: Выручка за период';
            }));

        $service = new FoodReportMaxDeliveryService($client);
        $service->deliver(
            20_020,
            "PK\x03\x04binary",
            'report_1_2026-09-01_2026-09-10.xlsx',
            'Отчёт: Выручка за период',
        );
    }

    /** NullMaxMessengerClient → MaxMessengerException до upload (не silent ok). */
    public function test_deliver_rejects_null_messenger_client(): void
    {
        $service = new FoodReportMaxDeliveryService(new NullMaxMessengerClient);

        try {
            $service->deliver(
                20_020,
                "PK\x03\x04binary",
                'report_1_2026-09-01_2026-09-10.xlsx',
                'Отчёт: Выручка за период',
            );
            $this->fail('Expected MaxMessengerRequestException');
        } catch (MaxMessengerRequestException $exception) {
            $this->assertSame(
                'Доставка в MAX недоступна (messenger driver = null)',
                $exception->userMessage(),
            );
        }
    }
}

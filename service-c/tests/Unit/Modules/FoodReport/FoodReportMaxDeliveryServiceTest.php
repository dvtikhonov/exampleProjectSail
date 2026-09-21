<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FoodReport;

use App\Modules\FoodReport\Contracts\FoodReportMaxDeliveryInterface;
use App\Modules\FoodReport\Services\FoodReportMaxDeliveryService;
use App\Modules\FoodReport\Services\NullFoodReportMaxDelivery;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Tests\TestCase;

/**
 * Unit: FoodReportMaxDeliveryService / NullFoodReportMaxDelivery + DI binding.
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

    /** NullFoodReportMaxDelivery → MaxMessengerRequestException до upload (не silent ok). */
    public function test_null_delivery_rejects_before_upload(): void
    {
        $service = new NullFoodReportMaxDelivery;

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

    /** При messenger_driver=null контейнер отдаёт NullFoodReportMaxDelivery. */
    public function test_null_messenger_driver_binds_null_delivery(): void
    {
        config(['max.messenger_driver' => 'null']);

        $delivery = $this->app->make(FoodReportMaxDeliveryInterface::class);

        $this->assertInstanceOf(NullFoodReportMaxDelivery::class, $delivery);
    }

    /** При messenger_driver=http контейнер отдаёт FoodReportMaxDeliveryService. */
    public function test_http_messenger_driver_binds_real_delivery(): void
    {
        config(['max.messenger_driver' => 'http']);

        $delivery = $this->app->make(FoodReportMaxDeliveryInterface::class);

        $this->assertInstanceOf(FoodReportMaxDeliveryService::class, $delivery);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoOcrStartDto;
use App\Modules\SchedulePhoto\Infrastructure\HttpSchedulePhotoOcrOrchestrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpSchedulePhotoOcrOrchestratorTest extends TestCase
{
    #[Test]
    public function start_sends_secret_header_and_photo_key_without_file_bytes(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())->method('request')->with(
            'POST',
            '/schedule-ocr',
            $this->callback(function (array $headers): bool {
                return ($headers['Accept'] ?? null) === 'application/json'
                    && ($headers['X-Schedule-Photo-Capture-Secret'] ?? null) === 'secret';
            }),
            $this->callback(function (array $body): bool {
                return ($body['photo_key'] ?? null) === 'schedule-photo/a.jpg'
                    && ! array_key_exists('photo_base64', $body)
                    && ! array_key_exists('photo_path', $body)
                    && ! array_key_exists('capture_secret', $body);
            }),
            'http://sidecar.test',
            30,
        )->willReturn(new HttpResponseDto(
            status: 202,
            body: json_encode(['accepted' => true, 'phase' => 'running'], JSON_THROW_ON_ERROR),
            successful: true,
        ));

        $orchestrator = new HttpSchedulePhotoOcrOrchestrator($http, 'http://sidecar.test', 30, 'secret');
        $orchestrator->start($this->dto());
    }

    #[Test]
    public function start_rejects_empty_secret_before_request(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->never())->method('request');

        $orchestrator = new HttpSchedulePhotoOcrOrchestrator($http, 'http://sidecar.test', 30, '   ');

        try {
            $orchestrator->start($this->dto());
            $this->fail('Expected FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
        }
    }

    #[Test]
    public function start_rejects_non_202(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')->willReturn(new HttpResponseDto(
            status: 503,
            body: json_encode(['error' => 'handshake_failed', 'message' => 'boom'], JSON_THROW_ON_ERROR),
            successful: false,
        ));

        $orchestrator = new HttpSchedulePhotoOcrOrchestrator($http, 'http://sidecar.test', 30, 'secret');

        $this->expectException(FoodDomainException::class);
        $orchestrator->start($this->dto());
    }

    private function dto(): SchedulePhotoOcrStartDto
    {
        return new SchedulePhotoOcrStartDto(
            runId: '11111111-1111-4111-8111-111111111111',
            photoKey: 'schedule-photo/a.jpg',
            dateFrom: '2026-08-13',
            dateTo: '2026-08-19',
            restaurantId: 1,
            categoryIds: [2],
            catalogScope: [
                ['id' => 1, 'name' => 'Пюре', 'category_id' => 2, 'category_name' => 'Гарнир'],
            ],
            aliasesMarkdown: '| пюре | Картофельное пюре |',
            photoMimeType: 'image/jpeg',
        );
    }
}

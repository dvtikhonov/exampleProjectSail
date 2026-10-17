<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure;

use App\Contracts\Shared\HttpClientInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoOcrOrchestratorInterface;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoOcrStartDto;

/**
 * HTTP-клиент к sidecar schedule-ocr (POST /schedule-ocr handshake).
 */
final class HttpSchedulePhotoOcrOrchestrator implements SchedulePhotoOcrOrchestratorInterface
{
    private const string CAPTURE_SECRET_HEADER = 'X-Schedule-Photo-Capture-Secret';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $orchestratorBaseUrl,
        private readonly int $timeoutSeconds,
        private readonly string $captureSecret,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function start(SchedulePhotoOcrStartDto $dto): void
    {
        $origin = rtrim(trim($this->orchestratorBaseUrl), '/');
        if ($origin === '') {
            throw new FoodDomainException('URL sidecar SchedulePhoto не настроен.', 503);
        }

        $secret = trim($this->captureSecret);
        if ($secret === '') {
            throw new FoodDomainException('Секрет sidecar SchedulePhoto не настроен.', 503);
        }

        try {
            $response = $this->http->request(
                'POST',
                '/schedule-ocr',
                [
                    'Accept' => 'application/json',
                    self::CAPTURE_SECRET_HEADER => $secret,
                ],
                $dto->toArray(),
                $origin,
                $this->timeoutSeconds,
            );
        } catch (\Throwable $exception) {
            throw new FoodDomainException(
                'Orchestrator SchedulePhoto OCR недоступен: '.$exception->getMessage(),
                503,
            );
        }

        if ($response->status !== 202) {
            $detail = $this->errorDetail($response->body);
            throw new FoodDomainException(
                'Orchestrator SchedulePhoto OCR недоступен (HTTP '.$response->status.').'.$detail,
                503,
            );
        }

        $decoded = $response->json();
        $accepted = is_array($decoded) && ($decoded['accepted'] ?? false) === true;
        $phase = is_array($decoded) && isset($decoded['phase']) && is_string($decoded['phase'])
            ? $decoded['phase']
            : '';
        if (! $accepted || $phase !== 'running') {
            throw new FoodDomainException(
                'Orchestrator SchedulePhoto OCR не принял задачу.',
                503,
            );
        }
    }

    private function errorDetail(mixed $body): string
    {
        if (! is_string($body) || trim($body) === '') {
            return '';
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return '';
        }

        $message = $decoded['message'] ?? $decoded['error'] ?? null;
        if (! is_string($message) || trim($message) === '') {
            return '';
        }

        return ' '.$message;
    }
}

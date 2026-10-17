<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure;

use App\Contracts\Shared\HttpClientInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextProbeInterface;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoProbeResultDto;

/**
 * HTTP probe PhotoText выбранного origin: GET /api/food/phototext/restaurants + X-PhotoText-Token.
 *
 * Локальный Docker и loopback в тексте готовности не называются VPS.
 */
final class HttpSchedulePhotoPhotoTextProbe implements SchedulePhotoPhotoTextProbeInterface
{
    private const string AGENT_TOKEN_HEADER = 'X-PhotoText-Token';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $agentToken,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function probe(string $baseUrl): SchedulePhotoProbeResultDto
    {
        $origin = rtrim(trim($baseUrl), '/');
        if ($origin === '') {
            return new SchedulePhotoProbeResultDto(false, 'BASE_URL пуст.');
        }

        $subject = $this->subject($origin);

        if ($this->agentToken === '') {
            return new SchedulePhotoProbeResultDto(
                false,
                'PhotoText agent token не настроен на сервере.',
            );
        }

        try {
            $response = $this->http->request(
                'GET',
                '/api/food/phototext/restaurants',
                [
                    'Accept' => 'application/json',
                    self::AGENT_TOKEN_HEADER => $this->agentToken,
                ],
                null,
                $origin,
                $this->timeoutSeconds,
            );
        } catch (\Throwable $exception) {
            return new SchedulePhotoProbeResultDto(
                false,
                $subject.' недоступен: '.$exception->getMessage(),
            );
        }

        if ($response->status === 401) {
            return new SchedulePhotoProbeResultDto(false, $subject.': неверный токен (401).');
        }

        if (! $response->successful) {
            return new SchedulePhotoProbeResultDto(
                false,
                $subject.': HTTP '.$response->status.'.',
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            return new SchedulePhotoProbeResultDto(false, $subject.': пустой или неверный JSON.');
        }

        $restaurants = $json['restaurants'] ?? null;
        if (! is_array($restaurants) || $restaurants === []) {
            return new SchedulePhotoProbeResultDto(
                false,
                $subject.': пустой список ресторанов.',
            );
        }

        return new SchedulePhotoProbeResultDto(true, $subject.' доступен.');
    }

    /**
     * Подпись origin в тексте готовности: локальный Docker и loopback без слова VPS.
     */
    private function subject(string $origin): string
    {
        $host = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?: ''));

        if (in_array($host, ['localhost', '127.0.0.1', 'host.docker.internal'], true)) {
            return 'Локальный PhotoText';
        }

        return 'VPS PhotoText';
    }
}

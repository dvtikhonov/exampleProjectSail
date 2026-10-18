<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Http;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextProbeInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoProbeResultDto;
use App\Modules\SchedulePhoto\Domain\Support\SchedulePhotoLocalHosts;

/**
 * HTTP probe PhotoText выбранного origin: GET /api/food/phototext/restaurants + X-PhotoText-Token.
 *
 * Редиректы не следуются: 301/302 остаётся ответом allow-listed origin.
 * Не-JSON при успешном HTTP не считается ответом PhotoText (статус 0).
 * Локальный Docker и loopback в тексте готовности не называются VPS.
 */
final class HttpSchedulePhotoPhotoTextProbe implements SchedulePhotoPhotoTextProbeInterface
{
    private const string AGENT_TOKEN_HEADER = 'X-PhotoText-Token';

    /**
     * Фиксированная фраза EnsurePhotoTextAiAccess. Другой текст 403 в готовность не копируется.
     */
    private const string AI_REMOTE_DENIED = 'Доступ AI к базе не разрешён.';

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
            return $this->result(false, 'BASE_URL пуст.');
        }

        $subject = $this->subject($origin);

        if ($this->agentToken === '') {
            return $this->result(false, 'PhotoText agent token не настроен на сервере.');
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
                followRedirects: false,
            );
        } catch (\Throwable $exception) {
            return $this->result(
                false,
                $subject.' недоступен: '.$exception->getMessage(),
            );
        }

        if ($response->status === 401) {
            return $this->result(false, $subject.': неверный токен (401).', $response->status);
        }

        if ($response->status === 403) {
            return $this->result(false, $this->forbiddenMessage($response, $subject), $response->status);
        }

        if (! $response->successful) {
            return $this->result(
                false,
                $subject.': HTTP '.$response->status.'.',
                $response->status,
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            return $this->result(false, $subject.': пустой или неверный JSON.', 0);
        }

        $restaurants = $json['restaurants'] ?? null;
        if (! is_array($restaurants) || $restaurants === []) {
            return $this->result(
                false,
                $subject.': пустой список ресторанов.',
                $response->status,
            );
        }

        return $this->result(true, $subject.' доступен.', $response->status);
    }

    /**
     * Подпись origin в тексте готовности: локальный Docker и loopback без слова VPS.
     */
    private function subject(string $origin): string
    {
        if (SchedulePhotoLocalHosts::isLocalOrigin($origin)) {
            return 'Локальный PhotoText';
        }

        return 'VPS PhotoText';
    }

    /**
     * Текст 403: известная фраза отказа AI, иначе короткая подпись HTTP-статуса.
     * Произвольное тело ответа в готовность не копируется.
     */
    private function forbiddenMessage(HttpResponseDto $response, string $subject): string
    {
        $json = $response->json();
        $message = is_array($json) ? ($json['message'] ?? null) : null;
        if (is_string($message) && trim($message) === self::AI_REMOTE_DENIED) {
            return self::AI_REMOTE_DENIED;
        }

        return $subject.': HTTP 403.';
    }

    private function result(bool $ok, string $message, int $status = 0): SchedulePhotoProbeResultDto
    {
        return new SchedulePhotoProbeResultDto($ok, $message, $status);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Http;

use App\Contracts\Shared\HttpClientInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoSidecarHealthProbeInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoProbeResultDto;

/**
 * GET {orchestrator_base_url}/health → ожидается JSON {ok:true}.
 * Редиректы не следуются: 301/302 не подтверждают health.
 */
final class HttpSchedulePhotoSidecarHealthProbe implements SchedulePhotoSidecarHealthProbeInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $orchestratorBaseUrl,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function checkHealth(): SchedulePhotoProbeResultDto
    {
        $origin = rtrim(trim($this->orchestratorBaseUrl), '/');
        if ($origin === '') {
            return new SchedulePhotoProbeResultDto(false, 'URL sidecar не настроен.');
        }

        try {
            $response = $this->http->request(
                'GET',
                '/health',
                ['Accept' => 'application/json'],
                null,
                $origin,
                $this->timeoutSeconds,
                followRedirects: false,
            );
        } catch (\Throwable $exception) {
            return new SchedulePhotoProbeResultDto(
                false,
                'Sidecar LLM недоступен: '.$exception->getMessage(),
            );
        }

        if (! $response->successful) {
            return new SchedulePhotoProbeResultDto(
                false,
                'Sidecar LLM: HTTP '.$response->status.'.',
            );
        }

        $json = $response->json();
        $ok = is_array($json) && ($json['ok'] ?? false) === true;
        if (! $ok) {
            return new SchedulePhotoProbeResultDto(
                false,
                'Sidecar LLM: ответ health без ok:true.',
            );
        }

        return new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.');
    }
}

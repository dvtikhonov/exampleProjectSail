<?php

declare(strict_types=1);

namespace App\Infrastructure\Briskly;

use App\Contracts\Food\BrisklySync\BrisklySyncTokenCaptureGatewayInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\Exceptions\Food\FoodDomainException;

/**
 * HTTP-клиент к Node sidecar briskly-sync (POST /capture-token).
 */
final class HttpBrisklySyncTokenCaptureGateway implements BrisklySyncTokenCaptureGatewayInterface
{
    private const string CAPTURE_SECRET_HEADER = 'X-Briskly-Capture-Secret';

    /** @var array<string, array{message: string, status: int}> */
    private const array ERROR_MAP = [
        'capture_disabled' => [
            'message' => 'захват отключён',
            'status' => 503,
        ],
        'unauthorized' => [
            'message' => 'неверный секрет sidecar',
            'status' => 503,
        ],
        'cdp_unavailable' => [
            'message' => 'Chrome CDP недоступен',
            'status' => 503,
        ],
        'no_briskly_tab' => [
            'message' => 'нет вкладки briskly.business',
            'status' => 422,
        ],
        'not_logged_in' => [
            'message' => 'нужен вход в кабинет Briskly (пароль + SMS)',
            'status' => 422,
        ],
        'no_token_observed' => [
            'message' => 'токен не обнаружен в сети',
            'status' => 422,
        ],
        'timeout' => [
            'message' => 'истекло время ожидания',
            'status' => 422,
        ],
    ];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl,
        private readonly string $captureSecret,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function captureToken(): string
    {
        $secret = trim($this->captureSecret);
        if ($secret === '') {
            throw $this->captureFailure('capture_disabled');
        }

        try {
            $response = $this->http->request(
                'POST',
                '/capture-token',
                [
                    'Accept' => 'application/json',
                    self::CAPTURE_SECRET_HEADER => $secret,
                ],
                [],
                rtrim($this->baseUrl, '/'),
                $this->timeoutSeconds,
            );
        } catch (\Throwable) {
            throw $this->captureFailure('cdp_unavailable');
        }

        $decoded = $response->json();
        $errorCode = is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])
            ? $decoded['error']
            : null;

        if (! $response->successful) {
            if ($errorCode !== null && isset(self::ERROR_MAP[$errorCode])) {
                throw $this->captureFailure($errorCode);
            }

            throw new FoodDomainException(
                'Не удалось получить токен Briskly: sidecar недоступен.',
                503,
            );
        }

        if (! is_array($decoded)) {
            throw new FoodDomainException(
                'Не удалось получить токен Briskly: некорректный ответ sidecar.',
                503,
            );
        }

        $rawToken = $decoded['token'] ?? null;
        if (! is_string($rawToken) || trim($rawToken) === '') {
            throw new FoodDomainException(
                'Не удалось получить токен Briskly: токен отсутствует в ответе.',
                503,
            );
        }

        return $rawToken;
    }

    private function captureFailure(string $code): FoodDomainException
    {
        $mapped = self::ERROR_MAP[$code] ?? [
            'message' => 'неизвестная ошибка захвата',
            'status' => 503,
        ];

        return new FoodDomainException(
            'Не удалось получить токен Briskly: '.$mapped['message'].'.',
            $mapped['status'],
        );
    }
}

<?php

namespace App\Services\Max;

use App\Contracts\Max\MaxMessengerClientInterface;
use App\DTO\Max\MaxMessageDto;
use App\Exceptions\Max\MaxMessengerAuthException;
use App\Exceptions\Max\MaxMessengerRateLimitException;
use App\Exceptions\Max\MaxMessengerRequestException;
use App\Exceptions\Max\MaxMessengerUnavailableException;
use App\Support\Config\SalesOutletsReportsConfigKeys;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpMaxMessengerClient implements MaxMessengerClientInterface
{
    private const BASE_URL = 'https://platform-api.max.ru';

    private const MESSAGES_ENDPOINT = '/messages';

    private const UPLOADS_ENDPOINT = '/uploads';

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function uploadFile(string $contents, string $fileName): string
    {
        $token = $this->botAccessToken();
        $uploadUrl = $this->requestFileUploadUrl($token);

        return $this->postFileToUploadUrl($uploadUrl, $contents, $fileName);
    }

    public function sendMessage(MaxMessageDto $message): void
    {
        $token = $this->botAccessToken();

        $retryMax = $this->rateLimitRetryMax();
        $retryDelayMs = $this->rateLimitRetryDelayMs();
        $attachmentRetryMax = $this->attachmentNotReadyRetryMax();
        $attachmentRetryDelayMs = $this->attachmentNotReadyRetryDelayMs();

        $rateLimitAttempt = 0;
        $attachmentAttempt = 0;

        while (true) {
            $response = $this->httpClient($token)
                ->post($this->endpointWithRecipient($message), $this->messagePayload($message));

            if ($response->successful()) {
                $this->logDebug('MAX message sent.', $message, $response->status());

                return;
            }

            $status = $response->status();

            if ($status === 401) {
                $this->logWarning('MAX API authentication failed.', $message, $status);

                throw new MaxMessengerAuthException;
            }

            if ($this->isAttachmentNotReady($response) && $attachmentAttempt < $attachmentRetryMax) {
                $this->logWarning('MAX attachment not ready, retrying.', $message, $status);
                $this->backoff($attachmentRetryDelayMs, $attachmentAttempt);
                $attachmentAttempt++;

                continue;
            }

            if ($status === 429 && $rateLimitAttempt < $retryMax) {
                $this->logWarning('MAX API rate limit hit, retrying.', $message, $status);
                $this->backoff($retryDelayMs, $rateLimitAttempt);
                $rateLimitAttempt++;

                continue;
            }

            if ($status === 429) {
                $this->logWarning('MAX API rate limit exhausted.', $message, $status);

                throw new MaxMessengerRateLimitException;
            }

            if ($status === 503 && $rateLimitAttempt < $retryMax) {
                $this->logWarning('MAX API unavailable, retrying.', $message, $status);
                $this->backoff($retryDelayMs, $rateLimitAttempt);
                $rateLimitAttempt++;

                continue;
            }

            if ($status === 503) {
                $this->logWarning('MAX API unavailable.', $message, $status);

                throw new MaxMessengerUnavailableException;
            }

            $this->logWarning('MAX API request failed.', $message, $status);

            throw new MaxMessengerRequestException(
                safeUserMessage: $this->safeErrorMessageForStatus($status),
            );
        }
    }

    private function requestFileUploadUrl(string $token): string
    {
        $response = $this->httpClient($token)
            ->post(self::UPLOADS_ENDPOINT.'?type=file');

        if ($response->status() === 401) {
            throw new MaxMessengerAuthException;
        }

        if (! $response->successful()) {
            throw new MaxMessengerRequestException(
                safeUserMessage: 'Не удалось подготовить загрузку файла в MAX.',
            );
        }

        $uploadUrl = (string) $response->json('url');

        if ($uploadUrl === '') {
            throw new MaxMessengerRequestException(
                safeUserMessage: 'Не удалось подготовить загрузку файла в MAX.',
            );
        }

        return $uploadUrl;
    }

    private function postFileToUploadUrl(string $uploadUrl, string $contents, string $fileName): string
    {
        $response = Http::asMultipart()
            ->attach('data', $contents, $fileName)
            ->post($uploadUrl);

        if (! $response->successful()) {
            throw new MaxMessengerRequestException(
                safeUserMessage: 'Не удалось загрузить CSV-файл в MAX.',
            );
        }

        $fileToken = (string) $response->json('token');

        if ($fileToken === '') {
            throw new MaxMessengerRequestException(
                safeUserMessage: 'Не удалось загрузить CSV-файл в MAX.',
            );
        }

        return $fileToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(MaxMessageDto $message): array
    {
        $payload = [
            'text' => $message->text,
            'notify' => true,
        ];

        if ($message->fileAttachmentToken !== null) {
            $payload['attachments'] = [
                [
                    'type' => 'file',
                    'payload' => [
                        'token' => $message->fileAttachmentToken,
                    ],
                ],
            ];
        }

        return $payload;
    }

    private function isAttachmentNotReady(Response $response): bool
    {
        return $response->json('code') === 'attachment.not.ready';
    }

    private function botAccessToken(): string
    {
        $token = (string) $this->config->get(
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_BOT_ACCESS_TOKEN,
            '',
        );

        if ($token === '') {
            throw new MaxMessengerAuthException;
        }

        return $token;
    }

    private function rateLimitRetryMax(): int
    {
        return (int) $this->config->get(
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_MAX,
            2,
        );
    }

    private function rateLimitRetryDelayMs(): int
    {
        return (int) $this->config->get(
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_DELAY_MS,
            500,
        );
    }

    private function attachmentNotReadyRetryMax(): int
    {
        return (int) $this->config->get(
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_ATTACHMENT_NOT_READY_RETRY_MAX,
            3,
        );
    }

    private function attachmentNotReadyRetryDelayMs(): int
    {
        return (int) $this->config->get(
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_ATTACHMENT_NOT_READY_RETRY_DELAY_MS,
            200,
        );
    }

    private function httpClient(string $token): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withHeaders([
                'Authorization' => $token,
            ])
            ->acceptJson()
            ->asJson();
    }

    private function endpointWithRecipient(MaxMessageDto $message): string
    {
        if ($message->chatId !== null) {
            return self::MESSAGES_ENDPOINT.'?chat_id='.$message->chatId;
        }

        return self::MESSAGES_ENDPOINT.'?user_id='.$message->userId;
    }

    private function backoff(int $baseDelayMs, int $attempt): void
    {
        usleep($baseDelayMs * (2 ** $attempt) * 1000);
    }

    private function safeErrorMessageForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Некорректный запрос к MAX API. Проверьте настройки получателей.',
            404 => 'Получатель MAX не найден. Проверьте chat_id и user_id в настройках.',
            405 => 'Операция не поддерживается MAX API.',
            default => 'Не удалось отправить сообщение в MAX. Обратитесь к администратору.',
        };
    }

    private function logDebug(string $message, MaxMessageDto $dto, int $status): void
    {
        Log::debug($message, $this->safeLogContext($dto, $status));
    }

    private function logWarning(string $message, MaxMessageDto $dto, int $status): void
    {
        Log::warning($message, $this->safeLogContext($dto, $status));
    }

    /**
     * @return array<string, int|string>
     */
    private function safeLogContext(MaxMessageDto $dto, int $status): array
    {
        $context = [
            'endpoint' => self::MESSAGES_ENDPOINT,
            'http_status' => $status,
        ];

        if ($dto->chatId !== null) {
            $context['recipient_type'] = 'chat_id';
            $context['recipient_id'] = $dto->chatId;
        } else {
            $context['recipient_type'] = 'user_id';
            $context['recipient_id'] = $dto->userId;
        }

        return $context;
    }
}

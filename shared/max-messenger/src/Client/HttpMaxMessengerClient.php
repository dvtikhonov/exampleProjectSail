<?php

namespace Shared\MaxMessenger\Client;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Config\MaxMessengerRetryConfig;
use Shared\MaxMessenger\Contracts\MaxBotTokenProviderInterface;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerAuthException;
use Shared\MaxMessenger\Exceptions\MaxMessengerRateLimitException;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Shared\MaxMessenger\Exceptions\MaxMessengerUnavailableException;

class HttpMaxMessengerClient implements MaxMessengerClientInterface
{
    private const BASE_URL = 'https://platform-api.max.ru';

    private const MESSAGES_ENDPOINT = '/messages';

    private const ANSWERS_ENDPOINT = '/answers';

    private const UPLOADS_ENDPOINT = '/uploads';

    public function __construct(
        private readonly MaxBotTokenProviderInterface $tokenProvider,
        private readonly MaxMessengerRetryConfig $retryConfig = new MaxMessengerRetryConfig,
    ) {}

    public function uploadFile(string $contents, string $fileName): string
    {
        $token = $this->botAccessToken();
        $uploadUrl = $this->requestFileUploadUrl($token);

        return $this->postFileToUploadUrl($uploadUrl, $contents, $fileName);
    }

    public function sendMessage(MaxMessageDto $message): void
    {
        $this->postWithRetry(
            endpoint: $this->endpointWithRecipient($message),
            payload: $this->messagePayload($message),
            logContext: fn (int $status): array => $this->safeLogContextForRecipient(
                endpoint: self::MESSAGES_ENDPOINT,
                status: $status,
                chatId: $message->chatId,
                userId: $message->userId,
            ),
            successLogMessage: 'MAX message sent.',
            retryAttachmentNotReady: true,
        );
    }

    public function sendInlineKeyboardMessage(MaxInlineKeyboardMessageDto $message): void
    {
        $this->postWithRetry(
            endpoint: $this->endpointWithInlineKeyboardRecipient($message),
            payload: $this->inlineKeyboardPayload($message),
            logContext: fn (int $status): array => $this->safeLogContextForRecipient(
                endpoint: self::MESSAGES_ENDPOINT,
                status: $status,
                chatId: $message->chatId,
                userId: $message->userId,
            ),
            successLogMessage: 'MAX inline keyboard message sent.',
        );
    }

    public function answerCallback(
        string $callbackId,
        ?string $notification = null,
        ?string $messageText = null,
    ): void
    {
        $payload = [];

        if ($notification !== null && $notification !== '') {
            $payload['notification'] = $notification;
        }

        if ($messageText !== null && $messageText !== '') {
            $payload['message'] = ['text' => $messageText];
        }

        $this->postWithRetry(
            endpoint: self::ANSWERS_ENDPOINT.'?callback_id='.rawurlencode($callbackId),
            payload: $payload,
            logContext: fn (int $status): array => [
                'endpoint' => self::ANSWERS_ENDPOINT,
                'http_status' => $status,
                'callback_id' => $callbackId,
            ],
            successLogMessage: 'MAX callback answered.',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(int): array<string, int|string>  $logContext
     */
    private function postWithRetry(
        string $endpoint,
        array $payload,
        callable $logContext,
        string $successLogMessage,
        bool $retryAttachmentNotReady = false,
    ): void {
        $token = $this->botAccessToken();

        $retryMax = $this->retryConfig->rateLimitRetryMax;
        $retryDelayMs = $this->retryConfig->rateLimitRetryDelayMs;
        $attachmentRetryMax = $this->retryConfig->attachmentNotReadyRetryMax;
        $attachmentRetryDelayMs = $this->retryConfig->attachmentNotReadyRetryDelayMs;

        $rateLimitAttempt = 0;
        $attachmentAttempt = 0;

        while (true) {
            $response = $this->httpClient($token)->post($endpoint, $payload);

            if ($response->successful()) {
                Log::debug($successLogMessage, $logContext($response->status()));

                return;
            }

            $status = $response->status();
            $context = $logContext($status);

            if ($status === 401) {
                Log::warning('MAX API authentication failed.', $context);

                throw new MaxMessengerAuthException;
            }

            if ($retryAttachmentNotReady && $this->isAttachmentNotReady($response) && $attachmentAttempt < $attachmentRetryMax) {
                Log::warning('MAX attachment not ready, retrying.', $context);
                $this->backoff($attachmentRetryDelayMs, $attachmentAttempt);
                $attachmentAttempt++;

                continue;
            }

            if ($status === 429 && $rateLimitAttempt < $retryMax) {
                Log::warning('MAX API rate limit hit, retrying.', $context);
                $this->backoff($retryDelayMs, $rateLimitAttempt);
                $rateLimitAttempt++;

                continue;
            }

            if ($status === 429) {
                Log::warning('MAX API rate limit exhausted.', $context);

                throw new MaxMessengerRateLimitException;
            }

            if ($status === 503 && $rateLimitAttempt < $retryMax) {
                Log::warning('MAX API unavailable, retrying.', $context);
                $this->backoff($retryDelayMs, $rateLimitAttempt);
                $rateLimitAttempt++;

                continue;
            }

            if ($status === 503) {
                Log::warning('MAX API unavailable.', $context);

                throw new MaxMessengerUnavailableException;
            }

            $errorBody = $response->json();
            if (! is_array($errorBody)) {
                $errorBody = ['raw' => $response->body()];
            }

            Log::warning('MAX API request failed.', array_merge($context, [
                'response' => $errorBody,
            ]));

            throw new MaxMessengerRequestException(
                safeUserMessage: $this->safeErrorMessageFromResponse($response, $status),
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

    /**
     * @return array<string, mixed>
     */
    private function inlineKeyboardPayload(MaxInlineKeyboardMessageDto $message): array
    {
        return [
            'text' => $message->text,
            'notify' => true,
            'attachments' => [
                [
                    'type' => 'inline_keyboard',
                    'payload' => [
                        'buttons' => $this->serializeButtonRows($message->buttonRows),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     * @return array<int, array<int, array<string, string>>>
     */
    private function serializeButtonRows(array $buttonRows): array
    {
        $rows = [];

        foreach ($buttonRows as $row) {
            $serializedRow = [];

            foreach ($row as $button) {
                $serializedButton = [
                    'type' => $button->type,
                    'text' => $button->text,
                ];

                if ($button->payload !== '') {
                    $serializedButton['payload'] = $button->payload;
                }

                if ($button->webApp !== null && $button->webApp !== '') {
                    $serializedButton['web_app'] = $button->webApp;
                }

                if ($button->url !== null && $button->url !== '') {
                    $serializedButton['url'] = $button->url;
                }

                if ($button->contactId !== null && $button->contactId > 0) {
                    $serializedButton['contact_id'] = $button->contactId;
                }

                $serializedRow[] = $serializedButton;
            }

            $rows[] = $serializedRow;
        }

        return $rows;
    }

    private function isAttachmentNotReady(Response $response): bool
    {
        return $response->json('code') === 'attachment.not.ready';
    }

    private function botAccessToken(): string
    {
        $token = $this->tokenProvider->botAccessToken();

        if ($token === '') {
            throw new MaxMessengerAuthException;
        }

        return $token;
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
        return $this->messagesEndpointWithRecipient($message->chatId, $message->userId);
    }

    private function endpointWithInlineKeyboardRecipient(MaxInlineKeyboardMessageDto $message): string
    {
        return $this->messagesEndpointWithRecipient($message->chatId, $message->userId);
    }

    private function messagesEndpointWithRecipient(?int $chatId, ?int $userId): string
    {
        if ($chatId !== null) {
            return self::MESSAGES_ENDPOINT.'?chat_id='.$chatId;
        }

        return self::MESSAGES_ENDPOINT.'?user_id='.$userId;
    }

    private function backoff(int $baseDelayMs, int $attempt): void
    {
        usleep($baseDelayMs * (2 ** $attempt) * 1000);
    }

    private function safeErrorMessageFromResponse(Response $response, int $status): string
    {
        $message = (string) $response->json('message', '');

        if (str_contains($message, 'Link not found')) {
            return 'Ссылка mini-app не зарегистрирована в MAX для текущего токена бота. '
                .'Проверьте, что MAX_BOT_ACCESS_TOKEN совпадает с токеном в кабинете MAX, '
                .'а URL в кабинете и MAX_MINI_APP_URL совпадают посимвольно, затем снова нажмите «Сохранить».';
        }

        return $this->safeErrorMessageForStatus($status);
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

    /**
     * @return array<string, int|string>
     */
    private function safeLogContextForRecipient(
        string $endpoint,
        int $status,
        ?int $chatId,
        ?int $userId,
    ): array {
        $context = [
            'endpoint' => $endpoint,
            'http_status' => $status,
        ];

        if ($chatId !== null) {
            $context['recipient_type'] = 'chat_id';
            $context['recipient_id'] = $chatId;
        } else {
            $context['recipient_type'] = 'user_id';
            $context['recipient_id'] = $userId;
        }

        return $context;
    }
}

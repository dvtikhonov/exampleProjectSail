<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Client\HttpMaxMessengerClient;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerAuthException;
use Shared\MaxMessenger\Exceptions\MaxMessengerRateLimitException;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Shared\MaxMessenger\Exceptions\MaxMessengerUnavailableException;
use Tests\TestCase;

class HttpMaxMessengerClientTest extends TestCase
{
    private const TOKEN = 'secret-max-token-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sales_outlets_reports.types.max_message.bot_access_token' => self::TOKEN,
            'sales_outlets_reports.types.max_message.rate_limit_retry_max' => 2,
            'sales_outlets_reports.types.max_message.rate_limit_retry_delay_ms' => 0,
        ]);
    }

    public function test_successful_send_uses_authorization_header_without_token_in_url(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->sendMessage(new MaxMessageDto(text: "ID | Магазин\n1 | Курск", chatId: 123));

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'chat_id=123')
                && ! str_contains($request->url(), self::TOKEN)
                && $request->hasHeader('Authorization', self::TOKEN)
                && $request['text'] === "ID | Магазин\n1 | Курск"
                && ! array_key_exists('format', $request->data())
                && $request['notify'] === true;
        });
    }

    public function test_revoked_token_throws_auth_exception_without_token_in_message(): void
    {
        Log::spy();

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);

        try {
            $client->sendMessage(new MaxMessageDto(text: 'test', userId: 456));
            $this->fail('Expected MaxMessengerAuthException was not thrown.');
        } catch (MaxMessengerAuthException $exception) {
            $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
            $this->assertStringContainsString('MAX_BOT_ACCESS_TOKEN', $exception->getMessage());
        }

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => ! str_contains($request->url(), self::TOKEN));

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context = []): bool {
                $encoded = json_encode([$message, $context], JSON_THROW_ON_ERROR);

                return ! str_contains($encoded, self::TOKEN);
            });
    }

    public function test_rate_limit_retries_until_success(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::sequence()
                ->push(['error' => 'rate limit'], 429)
                ->push(['error' => 'rate limit'], 429)
                ->push(['message' => ['id' => 1]], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->sendMessage(new MaxMessageDto(text: 'retry-test', chatId: 100));

        Http::assertSentCount(3);
    }

    public function test_rate_limit_exhausted_throws_exception(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['error' => 'rate limit'], 429),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);

        $this->expectException(MaxMessengerRateLimitException::class);

        $client->sendMessage(new MaxMessageDto(text: 'fail', chatId: 100));

        Http::assertSentCount(3);
    }

    public function test_unavailable_retries_until_success(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::sequence()
                ->push(['error' => 'unavailable'], 503)
                ->push(['message' => ['id' => 1]], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->sendMessage(new MaxMessageDto(text: '503-retry', userId: 789));

        Http::assertSentCount(2);
    }

    public function test_unavailable_exhausted_throws_exception(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);

        $this->expectException(MaxMessengerUnavailableException::class);

        $client->sendMessage(new MaxMessageDto(text: '503-fail', userId: 789));

        Http::assertSentCount(3);
    }

    public function test_bad_request_does_not_retry(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);

        try {
            $client->sendMessage(new MaxMessageDto(text: 'bad', chatId: 1));
            $this->fail('Expected MaxMessengerRequestException was not thrown.');
        } catch (MaxMessengerRequestException $exception) {
            $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
            $this->assertStringContainsString('Некорректный запрос', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_empty_token_throws_auth_exception_without_http_call(): void
    {
        config(['sales_outlets_reports.types.max_message.bot_access_token' => '']);

        Http::fake();

        $client = $this->app->make(HttpMaxMessengerClient::class);

        $this->expectException(MaxMessengerAuthException::class);

        $client->sendMessage(new MaxMessageDto(text: 'no-token', chatId: 1));

        Http::assertNothingSent();
    }

    public function test_send_message_with_file_attachment_includes_token_in_payload(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->sendMessage(new MaxMessageDto(
            text: 'Объекты продаж — отчёт',
            chatId: 123,
            fileAttachmentToken: 'csv-file-token',
        ));

        Http::assertSent(function ($request): bool {
            $attachments = $request['attachments'] ?? [];

            return str_contains($request->url(), 'chat_id=123')
                && $request['text'] === 'Объекты продаж — отчёт'
                && ($attachments[0]['type'] ?? null) === 'file'
                && ($attachments[0]['payload']['token'] ?? null) === 'csv-file-token';
        });
    }

    public function test_upload_file_requests_url_then_posts_multipart(): void
    {
        Http::fake([
            'platform-api.max.ru/uploads*' => Http::response([
                'url' => 'https://fu.test.example/upload',
            ], 200),
            'fu.test.example/*' => Http::response(['token' => 'uploaded-csv-token'], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $token = $client->uploadFile("\xEF\xBB\xBF\"id\";\"shop\"\n\"1\";\"Курск\"", 'objects-sales-outlets.csv');

        $this->assertSame('uploaded-csv-token', $token);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'platform-api.max.ru/uploads')
                && str_contains($request->url(), 'type=file')
                && $request->hasHeader('Authorization', self::TOKEN);
        });

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'fu.test.example/upload')
                && str_contains((string) $request->body(), 'Курск');
        });
    }

    public function test_send_inline_keyboard_message_includes_open_app_button(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
            text: 'Откройте mini-app:',
            buttonRows: [[
                new MaxInlineKeyboardButtonDto(
                    text: 'Заказ еды',
                    type: 'open_app',
                    webApp: 'https://example.test/max-app',
                ),
            ]],
            chatId: 321,
        ));

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $buttons = $request['attachments'][0]['payload']['buttons'] ?? [];

            return str_contains($request->url(), 'chat_id=321')
                && ($buttons[0][0]['type'] ?? null) === 'open_app'
                && ($buttons[0][0]['text'] ?? null) === 'Заказ еды'
                && ($buttons[0][0]['web_app'] ?? null) === 'https://example.test/max-app'
                && ! array_key_exists('payload', $buttons[0][0]);
        });
    }

    public function test_send_inline_keyboard_message_includes_open_app_contact_id(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
            text: 'Откройте mini-app:',
            buttonRows: [[
                new MaxInlineKeyboardButtonDto(
                    text: 'Заказ еды',
                    type: 'open_app',
                    webApp: 'https://example.test/max-app',
                    contactId: 421816864057,
                ),
            ]],
            chatId: 321,
        ));

        Http::assertSent(function ($request): bool {
            $buttons = $request['attachments'][0]['payload']['buttons'] ?? [];

            return ($buttons[0][0]['contact_id'] ?? null) === 421816864057;
        });
    }

    public function test_send_inline_keyboard_message_includes_callback_buttons(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
            text: 'Привет! Выберите ответ:',
            buttonRows: [[
                new MaxInlineKeyboardButtonDto(text: 'да', payload: 'yes'),
                new MaxInlineKeyboardButtonDto(text: 'нет', payload: 'no'),
            ]],
            chatId: 123,
        ));

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $attachments = $request['attachments'] ?? [];
            $buttons = $attachments[0]['payload']['buttons'] ?? [];

            return str_contains($request->url(), 'chat_id=123')
                && $request->hasHeader('Authorization', self::TOKEN)
                && $request['text'] === 'Привет! Выберите ответ:'
                && ($attachments[0]['type'] ?? null) === 'inline_keyboard'
                && ($buttons[0][0]['type'] ?? null) === 'callback'
                && ($buttons[0][0]['text'] ?? null) === 'да'
                && ($buttons[0][0]['payload'] ?? null) === 'yes'
                && ($buttons[0][1]['text'] ?? null) === 'нет'
                && ($buttons[0][1]['payload'] ?? null) === 'no';
        });
    }

    public function test_answer_callback_posts_to_answers_endpoint(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response([], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->answerCallback('cb-123', notification: 'Спасибо!');

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/answers')
                && str_contains($request->url(), 'callback_id=cb-123')
                && $request->hasHeader('Authorization', self::TOKEN)
                && ($request['notification'] ?? null) === 'Спасибо!';
        });
    }

    public function test_answer_callback_with_message_text_updates_chat_message(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response([], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->answerCallback('cb-789', messageText: 'Вы нажали кнопку: да');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'callback_id=cb-789')
                && ($request['message']['text'] ?? null) === 'Вы нажали кнопку: да'
                && ! isset($request['notification']);
        });
    }

    public function test_answer_callback_without_notification_sends_empty_body(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response([], 200),
        ]);

        $client = $this->app->make(HttpMaxMessengerClient::class);
        $client->answerCallback('cb-456');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'callback_id=cb-456')
                && $request->data() === [];
        });
    }
}

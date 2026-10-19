<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\DTO\Telegram\TelegramInlineKeyboardButtonDto;
use App\Exceptions\Telegram\TelegramMessengerRequestException;
use App\Infrastructure\Laravel\HttpTelegramMessengerClient;
use Tests\TestCase;

class HttpTelegramMessengerClientTest extends TestCase
{
    /** sendMessage без кнопок постит text и chat_id на Bot API. */
    public function test_send_message_posts_chat_id_and_text(): void
    {
        $captured = null;
        $http = $this->recordingHttp(function (string $method, string $url, ?array $jsonBody, ?string $baseUrl) use (&$captured): HttpResponseDto {
            $captured = compact('method', 'url', 'jsonBody', 'baseUrl');

            return new HttpResponseDto(status: 200, body: '{"ok":true,"result":{}}', successful: true);
        });

        $client = new HttpTelegramMessengerClient($http, $this->configWithToken('TOKEN:1'));

        $client->sendMessage(777, 'Hello');

        $this->assertSame('POST', $captured['method']);
        $this->assertSame('https://api.telegram.org', $captured['baseUrl']);
        $this->assertSame('/botTOKEN:1/sendMessage', $captured['url']);
        $this->assertSame([
            'chat_id' => 777,
            'text' => 'Hello',
        ], $captured['jsonBody']);
    }

    /** sendMessage с web_app-кнопкой кладёт reply_markup.inline_keyboard. */
    public function test_send_message_includes_web_app_inline_keyboard(): void
    {
        $captured = null;
        $http = $this->recordingHttp(function (string $method, string $url, ?array $jsonBody) use (&$captured): HttpResponseDto {
            $captured = $jsonBody;

            return new HttpResponseDto(status: 200, body: '{"ok":true}', successful: true);
        });

        $client = new HttpTelegramMessengerClient($http, $this->configWithToken('tok'));

        $client->sendMessage(1, 'Open', [[
            new TelegramInlineKeyboardButtonDto(
                text: 'Открыть заказ №5',
                webAppUrl: 'https://94-228-117-27.sslip.io/tg-app?order_id=5&view=chat',
            ),
        ]]);

        $this->assertSame([
            'chat_id' => 1,
            'text' => 'Open',
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => 'Открыть заказ №5',
                        'web_app' => [
                            'url' => 'https://94-228-117-27.sslip.io/tg-app?order_id=5&view=chat',
                        ],
                    ],
                ]],
            ],
        ], $captured);
    }

    /** ok=false в теле ответа → исключение с description. */
    public function test_send_message_throws_when_ok_false(): void
    {
        $http = $this->recordingHttp(function (): HttpResponseDto {
            return new HttpResponseDto(
                status: 200,
                body: '{"ok":false,"description":"Forbidden: bot was blocked by the user"}',
                successful: true,
            );
        });

        $client = new HttpTelegramMessengerClient($http, $this->configWithToken('tok'));

        $this->expectException(TelegramMessengerRequestException::class);
        $this->expectExceptionMessage('Forbidden: bot was blocked by the user');

        $client->sendMessage(1, 'Hi');
    }

    /** Без токена — исключение до HTTP. */
    public function test_send_message_throws_when_token_missing(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->never())->method('request');

        $config = $this->createMock(ApplicationConfigInterface::class);
        $config->method('get')->with('telegram.bot_token', '')->willReturn('');

        $client = new HttpTelegramMessengerClient($http, $config);

        $this->expectException(TelegramMessengerRequestException::class);
        $this->expectExceptionMessage('Telegram bot token is not configured');

        $client->sendMessage(1, 'Hi');
    }

    /**
     * @param  callable(string, string, ?array, ?string): HttpResponseDto  $handler
     */
    private function recordingHttp(callable $handler): HttpClientInterface
    {
        return new class($handler) implements HttpClientInterface
        {
            public function __construct(private readonly mixed $handler) {}

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
                bool $followRedirects = true,
            ): HttpResponseDto {
                return ($this->handler)($method, $url, $jsonBody, $baseUrl);
            }
        };
    }

    private function configWithToken(string $token): ApplicationConfigInterface
    {
        $config = $this->createMock(ApplicationConfigInterface::class);
        $config->method('get')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => match ($key) {
                'telegram.bot_token' => $token,
                default => $default,
            },
        );

        return $config;
    }
}

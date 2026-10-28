<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

/**
 * Feature: POST /internal/messenger-choice пишет визит в max_log только с токеном.
 */
class InternalMessengerChoiceLogTest extends TestCase
{
    private const string ENDPOINT = 'http://service-c/internal/messenger-choice';

    private const string CLICK_ENDPOINT = 'http://service-c/internal/messenger-choice/clicks';

    private const string TOKEN = 'messenger-choice-test-token';

    /** @var list<MessageLogged> */
    private array $capturedLogs = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'messenger_choice.log_token' => self::TOKEN,
            'max.public_app_url' => '',
            'max.webhook.url' => 'https://exampleprojectsail.fxtun.dev/api/webhooks/max',
        ]);

        $this->capturedLogs = [];
        Log::channel('max_log')->listen(function (MessageLogged $event): void {
            $this->capturedLogs[] = $event;
        });
    }

    /** С токеном пишет переданные host и ip; is_tunnel считается по host из тела. */
    public function test_logs_passed_host_and_ip_when_token_matches(): void
    {
        $response = $this->postChoice([
            'host' => 'exampleprojectsail.fxtun.dev',
            'ip' => '203.0.113.8',
            'user_agent' => 'Mozilla/5.0',
            'init_data' => 'auth_date=1&hash=secret',
        ]);

        $response->assertNoContent();

        $log = MessMaxLogTestHelper::assertSingleMessage($this->capturedLogs, 'Messenger choice page requested');
        $this->assertSame('info', $log->level);
        $this->assertSame('exampleprojectsail.fxtun.dev', $log->context['host']);
        $this->assertTrue($log->context['is_tunnel']);
        $this->assertSame('203.0.113.8', $log->context['ip']);
        $this->assertSame('Mozilla/5.0', $log->context['user_agent']);
        $this->assertArrayNotHasKey('init_data', $log->context);
        $this->assertArrayNotHasKey('init_data_length', $log->context);
        $this->assertStringNotContainsString('hash=secret', (string) json_encode($log->context));
        $this->assertStringNotContainsString(self::TOKEN, (string) json_encode($log->context));
    }

    /** Без заголовка токена отвечает 401 и не пишет в max_log. */
    public function test_does_not_log_when_token_header_is_missing(): void
    {
        $response = $this->postChoice([
            'host' => 'exampleprojectsail.fxtun.dev',
            'ip' => '203.0.113.8',
            'user_agent' => 'Mozilla/5.0',
        ], null);

        $response->assertUnauthorized();
        $this->assertSame('', $response->getContent());
        $this->assertChoiceNotLogged();
    }

    /** Неверный токен отвечает 401 и не пишет в max_log. */
    public function test_does_not_log_when_token_header_is_wrong(): void
    {
        $response = $this->postChoice([
            'host' => 'exampleprojectsail.fxtun.dev',
            'ip' => '203.0.113.8',
        ], 'wrong-token');

        $response->assertUnauthorized();
        $this->assertChoiceNotLogged();
    }

    /** Пустой секрет в конфигурации отвечает 401 и не пишет в max_log. */
    public function test_does_not_log_when_configured_token_is_empty(): void
    {
        config(['messenger_choice.log_token' => '']);

        $response = $this->postChoice([
            'host' => 'exampleprojectsail.fxtun.dev',
            'ip' => '203.0.113.8',
        ]);

        $response->assertUnauthorized();
        $this->assertChoiceNotLogged();
    }

    /** user_agent длиннее 120 символов обрезается; чужой host не считается туннелем. */
    public function test_truncates_user_agent_and_marks_non_tunnel_host(): void
    {
        $userAgent = str_repeat('M', 200);

        $response = $this->postChoice([
            'host' => '127.0.0.1',
            'ip' => null,
            'user_agent' => $userAgent,
        ]);

        $response->assertNoContent();

        $log = MessMaxLogTestHelper::assertSingleMessage($this->capturedLogs, 'Messenger choice page requested');
        $this->assertSame('127.0.0.1', $log->context['host']);
        $this->assertFalse($log->context['is_tunnel']);
        $this->assertNull($log->context['ip']);
        $this->assertSame(Str::limit($userAgent, 120, '…'), $log->context['user_agent']);
    }

    /** Без host отвечает 422 и не пишет в max_log. */
    public function test_does_not_log_when_host_is_missing(): void
    {
        $response = $this->postChoice([
            'ip' => '203.0.113.8',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['host']);
        $this->assertChoiceNotLogged();
    }

    /** Слишком длинные ip и user_agent отклоняются и не пишутся. */
    public function test_does_not_log_when_ip_or_user_agent_exceeds_max_length(): void
    {
        $tooLongIp = $this->postChoice([
            'host' => '127.0.0.1',
            'ip' => str_repeat('1', 46),
            'user_agent' => 'Mozilla/5.0',
        ]);

        $tooLongIp->assertUnprocessable();
        $tooLongIp->assertJsonValidationErrors(['ip']);

        $tooLongAgent = $this->postChoice([
            'host' => '127.0.0.1',
            'ip' => '203.0.113.8',
            'user_agent' => str_repeat('M', 2049),
        ]);

        $tooLongAgent->assertUnprocessable();
        $tooLongAgent->assertJsonValidationErrors(['user_agent']);
        $this->assertChoiceNotLogged();
    }

    /** Клик пишет messenger=telegram и не подменяет этим запись визита страницы. */
    public function test_logs_clicked_messenger_when_token_matches(): void
    {
        $response = $this->postClick([
            'host' => 'exampleprojectsail.fxtun.dev',
            'ip' => '203.0.113.8',
            'user_agent' => 'Mozilla/5.0',
            'messenger' => 'telegram',
            'url' => 'https://t.me/obedov_bot',
        ]);

        $response->assertNoContent();

        $log = MessMaxLogTestHelper::assertSingleMessage($this->capturedLogs, 'Messenger choice link clicked');
        $this->assertSame('telegram', $log->context['messenger']);
        $this->assertSame('exampleprojectsail.fxtun.dev', $log->context['host']);
        $this->assertTrue($log->context['is_tunnel']);
        $this->assertSame('203.0.113.8', $log->context['ip']);
        $this->assertArrayNotHasKey('url', $log->context);
        $this->assertStringNotContainsString('obedov_bot', (string) json_encode($log->context));
        $this->assertChoiceNotLogged();
    }

    /** Клик MAX пишет messenger=max. */
    public function test_logs_max_when_messenger_is_max(): void
    {
        $response = $this->postClick([
            'host' => '127.0.0.1',
            'ip' => '203.0.113.8',
            'user_agent' => 'Mozilla/5.0',
            'messenger' => 'max',
        ]);

        $response->assertNoContent();

        $log = MessMaxLogTestHelper::assertSingleMessage($this->capturedLogs, 'Messenger choice link clicked');
        $this->assertSame('max', $log->context['messenger']);
        $this->assertFalse($log->context['is_tunnel']);
        $this->assertStringNotContainsString('id421816864057_bot', (string) json_encode($log->context));
    }

    /** Ник бота не принимается вместо кода мессенджера и не пишется в max_log. */
    public function test_does_not_log_when_messenger_is_a_bot_name(): void
    {
        $response = $this->postClick([
            'host' => '127.0.0.1',
            'ip' => '203.0.113.8',
            'user_agent' => 'Mozilla/5.0',
            'messenger' => 'obedov_bot',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['messenger']);
        $this->assertClickNotLogged();
        $this->assertChoiceNotLogged();
        $this->assertStringNotContainsString('obedov_bot', (string) json_encode($this->capturedLogs));
    }

    /** Без messenger отвечает 422 и не пишет клик. */
    public function test_does_not_log_click_when_messenger_is_missing(): void
    {
        $response = $this->postClick([
            'host' => '127.0.0.1',
            'ip' => '203.0.113.8',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['messenger']);
        $this->assertClickNotLogged();
    }

    /** Без токена клик не пишется. */
    public function test_does_not_log_click_when_token_header_is_missing(): void
    {
        $response = $this->postClick([
            'host' => '127.0.0.1',
            'messenger' => 'telegram',
        ], null);

        $response->assertUnauthorized();
        $this->assertClickNotLogged();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postChoice(array $body, ?string $token = self::TOKEN): TestResponse
    {
        $headers = [];

        if ($token !== null) {
            $headers['X-Messenger-Choice-Token'] = $token;
        }

        return $this->withHeaders($headers)->postJson(self::ENDPOINT, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postClick(array $body, ?string $token = self::TOKEN): TestResponse
    {
        $headers = [];

        if ($token !== null) {
            $headers['X-Messenger-Choice-Token'] = $token;
        }

        return $this->withHeaders($headers)->postJson(self::CLICK_ENDPOINT, $body);
    }

    private function assertChoiceNotLogged(): void
    {
        $matches = array_values(array_filter(
            $this->capturedLogs,
            static fn (MessageLogged $event): bool => $event->message === 'Messenger choice page requested',
        ));

        $this->assertCount(0, $matches);
    }

    private function assertClickNotLogged(): void
    {
        $matches = array_values(array_filter(
            $this->capturedLogs,
            static fn (MessageLogged $event): bool => $event->message === 'Messenger choice link clicked',
        ));

        $this->assertCount(0, $matches);
    }
}

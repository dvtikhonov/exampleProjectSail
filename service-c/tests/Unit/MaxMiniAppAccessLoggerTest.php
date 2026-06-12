<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaxMiniAppAccessLogger;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class MaxMiniAppAccessLoggerTest extends TestCase
{
    public function test_log_page_request_writes_to_mess_max_channel(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        config([
            'max.webhook.url' => 'https://exampleprojectsail.fxtun.dev/api/webhooks/max',
        ]);

        $request = Request::create('/max-app', 'GET', server: [
            'HTTP_HOST' => 'exampleprojectsail.fxtun.dev',
            'HTTP_USER_AGENT' => 'MAX-Desktop/1.0',
        ]);

        $this->app->make(MaxMiniAppAccessLogger::class)->logPageRequest($request);

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX mini-app page requested');
        $this->assertSame('info', $log->level);
        $this->assertSame('exampleprojectsail.fxtun.dev', $log->context['host']);
        $this->assertTrue($log->context['is_tunnel']);
        $this->assertSame('MAX-Desktop/1.0', $log->context['user_agent']);
        $this->assertArrayNotHasKey('init_data_length', $log->context);
    }

    public function test_log_auth_request_writes_status_without_init_data(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $initData = 'auth_date=1&user=%7B%22id%22%3A123%7D&hash=secret';

        $request = Request::create('/api/max/auth', 'POST', [
            'init_data' => $initData,
        ], server: [
            'HTTP_HOST' => '127.0.0.1:8083',
        ]);

        $this->app->make(MaxMiniAppAccessLogger::class)->logAuthRequest($request, 200, 123);

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX mini-app auth requested');
        $this->assertSame(strlen($initData), $log->context['init_data_length']);
        $this->assertSame(200, $log->context['status']);
        $this->assertSame(123, $log->context['max_user_id']);
        $this->assertArrayNotHasKey('init_data', $log->context);
    }
}

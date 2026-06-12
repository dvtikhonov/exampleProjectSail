<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaxMiniAppAccessLogger;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MaxMiniAppAccessLoggerTest extends TestCase
{
    public function test_log_page_request_writes_to_mess_max_channel(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $request = Request::create('/max-app', 'GET', server: [
            'HTTP_HOST' => 'exampleprojectsail.fxtun.dev',
            'HTTP_USER_AGENT' => 'MAX-Desktop/1.0',
        ]);

        $this->app->make(MaxMiniAppAccessLogger::class)->logPageRequest($request);

        $this->assertCount(1, $captured);
        $this->assertSame('info', $captured[0]->level);
        $this->assertSame('MAX mini-app page requested', $captured[0]->message);
        $this->assertSame('exampleprojectsail.fxtun.dev', $captured[0]->context['host']);
        $this->assertTrue($captured[0]->context['is_tunnel']);
        $this->assertSame('MAX-Desktop/1.0', $captured[0]->context['user_agent']);
        $this->assertArrayNotHasKey('init_data_length', $captured[0]->context);
    }

    public function test_log_auth_request_writes_status_without_init_data(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $request = Request::create('/api/max/auth', 'POST', [
            'init_data' => 'auth_date=1&user=%7B%22id%22%3A123%7D&hash=secret',
        ], server: [
            'HTTP_HOST' => '127.0.0.1:8083',
        ]);

        $this->app->make(MaxMiniAppAccessLogger::class)->logAuthRequest($request, 200, 123);

        $this->assertCount(1, $captured);
        $this->assertSame('MAX mini-app auth requested', $captured[0]->message);
        $this->assertSame(42, $captured[0]->context['init_data_length']);
        $this->assertSame(200, $captured[0]->context['status']);
        $this->assertSame(123, $captured[0]->context['max_user_id']);
        $this->assertArrayNotHasKey('init_data', $captured[0]->context);
    }
}

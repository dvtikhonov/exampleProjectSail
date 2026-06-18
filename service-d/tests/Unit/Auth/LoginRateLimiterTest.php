<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Services\Auth\LoginRateLimiter;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginRateLimiterTest extends TestCase
{
    private LoginRateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = new LoginRateLimiter;
        RateLimiter::clear($this->rateLimiter->throttleKey('user@example.com', '127.0.0.1'));
    }

    public function test_throttle_key_uses_transliterated_lowercase_email_and_ip(): void
    {
        $key = $this->rateLimiter->throttleKey('User@Example.com', '192.168.1.1');

        $expected = Str::transliterate('user@example.com|192.168.1.1');

        $this->assertSame($expected, $key);
    }

    public function test_ensure_is_not_rate_limited_dispatches_lockout_event(): void
    {
        Event::fake([Lockout::class]);

        $email = 'throttle-test@example.com';
        $ip = '10.0.0.1';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->rateLimiter->hit($email, $ip);
        }

        $request = Request::create('/api/login', 'POST');

        try {
            $this->rateLimiter->ensureIsNotRateLimited($email, $ip, $request);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        Event::assertDispatched(Lockout::class);
    }
}

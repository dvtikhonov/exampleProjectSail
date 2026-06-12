<?php

namespace Tests\Unit;

use App\Http\Middleware\VerifyMaxWebhookSecret;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class VerifyMaxWebhookSecretTest extends TestCase
{
    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['max.webhook.secret' => self::SECRET]);
    }

    public function test_returns_unauthorized_when_secret_header_is_wrong(): void
    {
        $middleware = new VerifyMaxWebhookSecret;
        $request = Request::create('/api/webhooks/max', 'POST');
        $request->headers->set('X-Max-Bot-Api-Secret', 'wrong-secret');

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_returns_unauthorized_when_secret_header_is_missing(): void
    {
        $middleware = new VerifyMaxWebhookSecret;
        $request = Request::create('/api/webhooks/max', 'POST');

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function test_returns_unauthorized_when_configured_secret_is_empty(): void
    {
        config(['max.webhook.secret' => '']);

        $middleware = new VerifyMaxWebhookSecret;
        $request = Request::create('/api/webhooks/max', 'POST');
        $request->headers->set('X-Max-Bot-Api-Secret', self::SECRET);

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function test_passes_request_when_secret_matches(): void
    {
        $middleware = new VerifyMaxWebhookSecret;
        $request = Request::create('/api/webhooks/max', 'POST');
        $request->headers->set('X-Max-Bot-Api-Secret', self::SECRET);

        $response = $middleware->handle($request, fn (Request $req) => response('ok', Response::HTTP_OK));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }
}

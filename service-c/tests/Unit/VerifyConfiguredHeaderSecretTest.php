<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\VerifyMaxWebhookSecret;
use App\Http\Middleware\VerifyPhotoTextAgentToken;
use Closure;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class VerifyConfiguredHeaderSecretTest extends TestCase
{
    private const MAX_SECRET = 'test-webhook-secret';

    private const PHOTOTEXT_TOKEN = 'phototext-test-token';

    /**
     * Наборы middleware: MAX webhook и PhotoText agent token.
     *
     * @return array<string, array{
     *     middlewareClass: class-string,
     *     configKey: string,
     *     secret: string,
     *     headerName: string,
     *     requestUri: string,
     *     requestMethod: string
     * }>
     */
    public static function middlewareProvider(): array
    {
        return [
            'max_webhook' => [
                'middlewareClass' => VerifyMaxWebhookSecret::class,
                'configKey' => 'max.webhook.secret',
                'secret' => self::MAX_SECRET,
                'headerName' => 'X-Max-Bot-Api-Secret',
                'requestUri' => '/api/webhooks/max',
                'requestMethod' => 'POST',
            ],
            'phototext_agent' => [
                'middlewareClass' => VerifyPhotoTextAgentToken::class,
                'configKey' => 'phototext.agent_token',
                'secret' => self::PHOTOTEXT_TOKEN,
                'headerName' => 'X-PhotoText-Token',
                'requestUri' => '/api/food/phototext/restaurants',
                'requestMethod' => 'GET',
            ],
        ];
    }

    /**
     * @param  class-string  $middlewareClass
     */
    #[DataProvider('middlewareProvider')]
    public function test_returns_unauthorized_when_secret_header_is_wrong(
        string $middlewareClass,
        string $configKey,
        string $secret,
        string $headerName,
        string $requestUri,
        string $requestMethod,
    ): void {
        config([$configKey => $secret]);

        $middleware = new $middlewareClass;
        $request = Request::create($requestUri, $requestMethod);
        $request->headers->set($headerName, 'wrong-value');

        $response = $middleware->handle($request, $this->okNext());

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    /**
     * @param  class-string  $middlewareClass
     */
    #[DataProvider('middlewareProvider')]
    public function test_returns_unauthorized_when_secret_header_is_missing(
        string $middlewareClass,
        string $configKey,
        string $secret,
        string $headerName,
        string $requestUri,
        string $requestMethod,
    ): void {
        config([$configKey => $secret]);

        $middleware = new $middlewareClass;
        $request = Request::create($requestUri, $requestMethod);

        $response = $middleware->handle($request, $this->okNext());

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @param  class-string  $middlewareClass
     */
    #[DataProvider('middlewareProvider')]
    public function test_returns_unauthorized_when_configured_secret_is_empty(
        string $middlewareClass,
        string $configKey,
        string $secret,
        string $headerName,
        string $requestUri,
        string $requestMethod,
    ): void {
        config([$configKey => '']);

        $middleware = new $middlewareClass;
        $request = Request::create($requestUri, $requestMethod);
        $request->headers->set($headerName, $secret);

        $response = $middleware->handle($request, $this->okNext());

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @param  class-string  $middlewareClass
     */
    #[DataProvider('middlewareProvider')]
    public function test_passes_request_when_secret_matches(
        string $middlewareClass,
        string $configKey,
        string $secret,
        string $headerName,
        string $requestUri,
        string $requestMethod,
    ): void {
        config([$configKey => $secret]);

        $middleware = new $middlewareClass;
        $request = Request::create($requestUri, $requestMethod);
        $request->headers->set($headerName, $secret);

        $response = $middleware->handle($request, $this->okNext());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    /**
     * @return Closure(Request): Response
     */
    private function okNext(): Closure
    {
        return fn (Request $req) => response('ok', Response::HTTP_OK);
    }
}

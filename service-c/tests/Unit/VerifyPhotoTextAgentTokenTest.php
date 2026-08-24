<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\VerifyPhotoTextAgentToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class VerifyPhotoTextAgentTokenTest extends TestCase
{
    private const string TOKEN = 'phototext-test-token';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['phototext.agent_token' => self::TOKEN]);
    }

    /** Возвращает 401, если заголовок токена неверный. */
    public function test_returns_unauthorized_when_token_header_is_wrong(): void
    {
        $middleware = new VerifyPhotoTextAgentToken;
        $request = Request::create('/api/food/phototext/restaurants', 'GET');
        $request->headers->set('X-PhotoText-Token', 'wrong-token');

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    /** Возвращает 401, если заголовок токена отсутствует. */
    public function test_returns_unauthorized_when_token_header_is_missing(): void
    {
        $middleware = new VerifyPhotoTextAgentToken;
        $request = Request::create('/api/food/phototext/restaurants', 'GET');

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /** Возвращает 401, если настроенный токен пуст. */
    public function test_returns_unauthorized_when_configured_token_is_empty(): void
    {
        config(['phototext.agent_token' => '']);

        $middleware = new VerifyPhotoTextAgentToken;
        $request = Request::create('/api/food/phototext/restaurants', 'GET');
        $request->headers->set('X-PhotoText-Token', self::TOKEN);

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /** Пропускает запрос, когда токен совпадает. */
    public function test_passes_request_when_token_matches(): void
    {
        $middleware = new VerifyPhotoTextAgentToken;
        $request = Request::create('/api/food/phototext/restaurants', 'GET');
        $request->headers->set('X-PhotoText-Token', self::TOKEN);

        $response = $middleware->handle($request, fn (Request $req) => response('ok', Response::HTTP_OK));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }
}

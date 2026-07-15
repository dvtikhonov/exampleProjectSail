<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\AuthenticateMaxMiniApp;
use App\Models\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthenticateMaxMiniAppTest extends TestCase
{
    use RefreshDatabase;

    /** Возвращает 401 без Bearer-токена. */
    public function test_returns_unauthorized_without_bearer_token(): void
    {
        $middleware = new AuthenticateMaxMiniApp;
        $request = Request::create('/api/max/me', 'GET');

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /** Пропускает запрос с валидным токеном MAX мини-приложения. */
    public function test_passes_request_with_valid_max_miniapp_token(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 12_345,
            'first_name' => 'Max',
        ]);

        $plainTextToken = $maxUser->createToken('max-miniapp', ['max-miniapp'], now()->addHour())->plainTextToken;

        $middleware = new AuthenticateMaxMiniApp;
        $request = Request::create('/api/max/me', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$plainTextToken);

        $response = $middleware->handle($request, function (Request $req) {
            return response()->json([
                'max_user_id' => $req->user()?->max_user_id,
            ]);
        });

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('{"max_user_id":12345}', $response->getContent());
    }

    /** Возвращает 401 за истёкший токен. */
    public function test_returns_unauthorized_for_expired_token(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 54_321,
            'first_name' => 'Expired',
        ]);

        $plainTextToken = $maxUser->createToken(
            'max-miniapp',
            ['max-miniapp'],
            now()->subMinute(),
        )->plainTextToken;

        $middleware = new AuthenticateMaxMiniApp;
        $request = Request::create('/api/max/me', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$plainTextToken);

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}

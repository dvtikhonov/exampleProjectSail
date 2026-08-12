<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\AuthenticateMaxMiniApp;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
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

    /** Не обновляет last_used_at чаще чем раз в 60 секунд. */
    public function test_throttles_last_used_at_within_sixty_seconds(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 77_001,
            'first_name' => 'Throttle',
        ]);

        $plainTextToken = $maxUser->createToken('max-miniapp', ['max-miniapp'], now()->addHour())->plainTextToken;
        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        $this->assertNotNull($accessToken);

        $frozenLastUsed = now()->subSeconds(30);
        $accessToken->forceFill(['last_used_at' => $frozenLastUsed])->save();

        $middleware = new AuthenticateMaxMiniApp;
        $request = Request::create('/api/max/me', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$plainTextToken);

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $accessToken->refresh();
        $this->assertNotNull($accessToken->last_used_at);
        $this->assertSame(
            $frozenLastUsed->getTimestamp(),
            $accessToken->last_used_at->getTimestamp(),
        );
    }

    /** Обновляет last_used_at, если прошло больше 60 секунд. */
    public function test_updates_last_used_at_after_throttle_window(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 77_002,
            'first_name' => 'Touch',
        ]);

        $plainTextToken = $maxUser->createToken('max-miniapp', ['max-miniapp'], now()->addHour())->plainTextToken;
        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        $this->assertNotNull($accessToken);

        $staleLastUsed = now()->subSeconds(61);
        $accessToken->forceFill(['last_used_at' => $staleLastUsed])->save();

        $middleware = new AuthenticateMaxMiniApp;
        $request = Request::create('/api/max/me', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$plainTextToken);

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $accessToken->refresh();
        $this->assertNotNull($accessToken->last_used_at);
        $this->assertTrue($accessToken->last_used_at->greaterThan($staleLastUsed));
    }
}

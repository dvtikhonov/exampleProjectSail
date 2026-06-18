<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Contracts\UserRepositoryInterface;
use App\DTO\Auth\LoginUserDto;
use App\DTO\Auth\RegisterUserDto;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\LoginRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private UserRepositoryInterface&MockInterface $userRepository;

    private LoginRateLimiter&MockInterface $loginRateLimiter;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->loginRateLimiter = Mockery::mock(LoginRateLimiter::class);
        $this->authService = new AuthService($this->userRepository, $this->loginRateLimiter);
    }

    public function test_register_creates_user_via_repository_and_logs_in(): void
    {
        $dto = new RegisterUserDto(
            name: 'Test User',
            email: 'test@example.com',
            password: 'secret-password',
        );

        $user = new User([
            'name' => $dto->name,
            'email' => $dto->email,
        ]);
        $user->id = 1;

        $this->userRepository
            ->shouldReceive('create')
            ->once()
            ->with([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'secret-password',
            ])
            ->andReturn($user);

        $request = Request::create('/api/register', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $result = $this->authService->register($dto, $request);

        $this->assertSame($user, $result);
        $this->assertTrue(Auth::check());
        $this->assertTrue(Auth::user()->is($user));
    }

    public function test_login_checks_rate_limit_before_attempt(): void
    {
        $user = User::factory()->make();
        $dto = new LoginUserDto(
            email: $user->email,
            password: 'password',
            remember: false,
        );

        $request = Request::create('/api/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $this->loginRateLimiter
            ->shouldReceive('ensureIsNotRateLimited')
            ->once()
            ->with($dto->email, $request->ip(), $request);

        $this->loginRateLimiter
            ->shouldReceive('clear')
            ->once()
            ->with($dto->email, $request->ip());

        Auth::shouldReceive('attempt')
            ->once()
            ->with(['email' => $dto->email, 'password' => $dto->password], false)
            ->andReturn(true);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $result = $this->authService->login($dto, $request);

        $this->assertSame($user, $result);
    }

    public function test_login_hits_rate_limiter_on_failed_attempt(): void
    {
        $dto = new LoginUserDto(
            email: 'user@example.com',
            password: 'wrong-password',
            remember: false,
        );

        $request = Request::create('/api/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $this->loginRateLimiter
            ->shouldReceive('ensureIsNotRateLimited')
            ->once()
            ->with($dto->email, $request->ip(), $request);

        $this->loginRateLimiter
            ->shouldReceive('hit')
            ->once()
            ->with($dto->email, $request->ip());

        Auth::shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        $this->expectException(ValidationException::class);

        try {
            $this->authService->login($dto, $request);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());

            throw $exception;
        }
    }

    public function test_login_throws_when_rate_limited(): void
    {
        $dto = new LoginUserDto(
            email: 'user@example.com',
            password: 'password',
            remember: false,
        );

        $request = Request::create('/api/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $this->loginRateLimiter
            ->shouldReceive('ensureIsNotRateLimited')
            ->once()
            ->with($dto->email, $request->ip(), $request)
            ->andThrow(ValidationException::withMessages([
                'email' => ['Too many login attempts.'],
            ]));

        $this->expectException(ValidationException::class);

        $this->authService->login($dto, $request);
    }
}

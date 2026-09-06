<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\CurrentHttpRequestInterface;
use App\Infrastructure\Laravel\RequestGatewayUserContext;
use stdClass;
use Tests\TestCase;

/**
 * Unit-тесты RequestGatewayUserContext через порт CurrentHttpRequest.
 */
class RequestGatewayUserContextTest extends TestCase
{
    /** Возвращает int id пользователя из текущего запроса. */
    public function test_current_user_id_returns_int_id(): void
    {
        $user = new stdClass;
        $user->id = 17;

        $currentHttpRequest = $this->createMock(CurrentHttpRequestInterface::class);
        $currentHttpRequest
            ->expects($this->once())
            ->method('user')
            ->willReturn($user);

        $context = new RequestGatewayUserContext($currentHttpRequest);

        $this->assertSame(17, $context->currentUserId());
    }

    /** null user → null. */
    public function test_current_user_id_returns_null_when_user_missing(): void
    {
        $currentHttpRequest = $this->createMock(CurrentHttpRequestInterface::class);
        $currentHttpRequest
            ->expects($this->once())
            ->method('user')
            ->willReturn(null);

        $context = new RequestGatewayUserContext($currentHttpRequest);

        $this->assertNull($context->currentUserId());
    }
}

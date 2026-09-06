<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\CurrentHttpRequestInterface;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\DTO\Max\MaxUserRecord;
use App\Http\Mappers\MaxUserIdentityMapper;
use App\Http\Resolvers\AuthenticatedMaxUserResolver;
use App\Models\Max\MaxUser;
use App\Repositories\Max\MaxUserMapper;
use RuntimeException;
use stdClass;
use Tests\TestCase;

/**
 * Unit-тесты AuthenticatedMaxUserResolver: MaxUser / не-MaxUser через порт CurrentHttpRequest.
 */
class AuthenticatedMaxUserResolverTest extends TestCase
{
    /** identity() маппит MaxUser из текущего HTTP-контекста. */
    public function test_identity_returns_mapped_max_user(): void
    {
        $user = new MaxUser([
            'max_user_id' => 42_001,
            'first_name' => 'Admin',
        ]);
        $identity = new MaxUserIdentity(maxUserId: 42_001, adminRoles: []);

        $currentHttpRequest = $this->createMock(CurrentHttpRequestInterface::class);
        $currentHttpRequest
            ->expects($this->once())
            ->method('user')
            ->willReturn($user);

        $identityMapper = $this->createMock(MaxUserIdentityMapper::class);
        $identityMapper
            ->expects($this->once())
            ->method('fromModel')
            ->with($user)
            ->willReturn($identity);

        $maxUserMapper = $this->createMock(MaxUserMapper::class);
        $maxUserMapper->expects($this->never())->method('toRecord');

        $resolver = new AuthenticatedMaxUserResolver(
            $currentHttpRequest,
            $identityMapper,
            $maxUserMapper,
        );

        $this->assertSame($identity, $resolver->identity());
    }

    /** record() маппит MaxUser из текущего HTTP-контекста. */
    public function test_record_returns_mapped_max_user(): void
    {
        $user = new MaxUser([
            'max_user_id' => 42_002,
            'first_name' => 'User',
        ]);
        $record = new MaxUserRecord(
            maxUserId: 42_002,
            firstName: 'User',
            lastName: null,
            username: null,
            languageCode: null,
            photoUrl: null,
            aiAccessUntil: null,
            customerCategoryId: null,
            deliveryAddress: null,
        );

        $currentHttpRequest = $this->createMock(CurrentHttpRequestInterface::class);
        $currentHttpRequest
            ->expects($this->once())
            ->method('user')
            ->willReturn($user);

        $identityMapper = $this->createMock(MaxUserIdentityMapper::class);
        $identityMapper->expects($this->never())->method('fromModel');

        $maxUserMapper = $this->createMock(MaxUserMapper::class);
        $maxUserMapper
            ->expects($this->once())
            ->method('toRecord')
            ->with($user)
            ->willReturn($record);

        $resolver = new AuthenticatedMaxUserResolver(
            $currentHttpRequest,
            $identityMapper,
            $maxUserMapper,
        );

        $this->assertSame($record, $resolver->record());
    }

    /** null вместо MaxUser → RuntimeException. */
    public function test_identity_throws_when_user_is_null(): void
    {
        $currentHttpRequest = $this->createMock(CurrentHttpRequestInterface::class);
        $currentHttpRequest
            ->expects($this->once())
            ->method('user')
            ->willReturn(null);

        $resolver = new AuthenticatedMaxUserResolver(
            $currentHttpRequest,
            $this->createMock(MaxUserIdentityMapper::class),
            $this->createMock(MaxUserMapper::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Authenticated MaxUser is required.');

        $resolver->identity();
    }

    /** Не-MaxUser → RuntimeException. */
    public function test_record_throws_when_user_is_not_max_user(): void
    {
        $currentHttpRequest = $this->createMock(CurrentHttpRequestInterface::class);
        $currentHttpRequest
            ->expects($this->once())
            ->method('user')
            ->willReturn(new stdClass);

        $resolver = new AuthenticatedMaxUserResolver(
            $currentHttpRequest,
            $this->createMock(MaxUserIdentityMapper::class),
            $this->createMock(MaxUserMapper::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Authenticated MaxUser is required.');

        $resolver->record();
    }
}

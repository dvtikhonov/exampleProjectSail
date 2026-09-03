<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Max\MaxLoadTestDataRepositoryInterface;
use App\Contracts\Max\MaxMiniAppTokenIssuerInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\ApplicationEnvironmentInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\LocalFileWriterInterface;
use App\DTO\Max\MaxUserRecord;
use App\Services\Max\MaxLoadTestService;
use App\Support\Max\MaxLoadTestUserIds;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Tests\TestCase;

/**
 * Фаза 4c: MaxLoadTestService оркестрирует порты без Eloquent/Facades.
 */
class MaxLoadTestServiceTest extends TestCase
{
    private ApplicationConfigInterface&MockObject $config;

    private CustomerCategoryRepositoryInterface&MockObject $customerCategoryRepository;

    private MenuCatalogCacheInvalidatorInterface&MockObject $catalogCacheInvalidator;

    private MaxUserRepositoryInterface&MockObject $maxUserRepository;

    private MaxLoadTestDataRepositoryInterface&MockObject $loadTestDataRepository;

    private MaxMiniAppTokenIssuerInterface&MockObject $tokenIssuer;

    private ClockInterface&MockObject $clock;

    private ApplicationEnvironmentInterface&MockObject $environment;

    private LocalFileWriterInterface&MockObject $localFileWriter;

    private MaxLoadTestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ApplicationConfigInterface::class);
        $this->customerCategoryRepository = $this->createMock(CustomerCategoryRepositoryInterface::class);
        $this->catalogCacheInvalidator = $this->createMock(MenuCatalogCacheInvalidatorInterface::class);
        $this->maxUserRepository = $this->createMock(MaxUserRepositoryInterface::class);
        $this->loadTestDataRepository = $this->createMock(MaxLoadTestDataRepositoryInterface::class);
        $this->tokenIssuer = $this->createMock(MaxMiniAppTokenIssuerInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->environment = $this->createMock(ApplicationEnvironmentInterface::class);
        $this->localFileWriter = $this->createMock(LocalFileWriterInterface::class);

        $this->service = new MaxLoadTestService(
            $this->config,
            $this->customerCategoryRepository,
            $this->catalogCacheInvalidator,
            $this->maxUserRepository,
            $this->loadTestDataRepository,
            $this->tokenIssuer,
            $this->clock,
            $this->environment,
            $this->localFileWriter,
        );
    }

    public function test_issue_tokens_writes_json_and_creates_users(): void
    {
        $this->allowEnvironment();
        $now = new DateTimeImmutable('2026-09-03 12:00:00');
        $outputPath = '/tmp/load-test-tokens-unit.json';

        $this->config->method('get')->with('max.miniapp.token_ttl_seconds', 86_400)->willReturn(3600);
        $this->clock->method('now')->willReturn($now);
        $this->customerCategoryRepository->method('findOrCreateDefaultCategoryId')->willReturn(7);

        $this->maxUserRepository->expects($this->exactly(2))
            ->method('upsertLoadTestUser')
            ->willReturnCallback(function (int $maxUserId, string $firstName, string $username, ?int $categoryId): MaxUserRecord {
                $this->assertSame(7, $categoryId);

                return new MaxUserRecord(
                    maxUserId: $maxUserId,
                    firstName: $firstName,
                    lastName: null,
                    username: $username,
                    languageCode: 'ru',
                    photoUrl: null,
                    aiAccessUntil: null,
                    customerCategoryId: $categoryId,
                    deliveryAddress: null,
                );
            });

        $this->tokenIssuer->expects($this->exactly(2))->method('revokeNamedTokens');
        $this->tokenIssuer->expects($this->exactly(2))
            ->method('createToken')
            ->willReturnOnConsecutiveCalls('plain-a', 'plain-b');

        $this->localFileWriter->expects($this->once())
            ->method('ensureDirectory')
            ->with(dirname($outputPath));
        $this->localFileWriter->expects($this->once())
            ->method('put')
            ->with(
                $outputPath,
                $this->callback(function (string $json): bool {
                    $decoded = json_decode(rtrim($json), true);

                    return is_array($decoded)
                        && count($decoded) === 2
                        && $decoded[0]['max_user_id'] === MaxLoadTestUserIds::BASE_ID
                        && $decoded[0]['token'] === 'plain-a'
                        && $decoded[1]['token'] === 'plain-b';
                }),
            );

        $result = $this->service->issueTokens(2, $outputPath);

        $this->assertSame($outputPath, $result->outputPath);
        $this->assertCount(2, $result->tokens);
        $this->assertSame('plain-a', $result->tokens[0]->token);
        $this->assertSame(MaxLoadTestUserIds::BASE_ID + 1, $result->tokens[1]->maxUserId);
    }

    public function test_issue_tokens_rejects_disallowed_environment(): void
    {
        $this->environment->method('is')->with(['local', 'testing'])->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('local или testing');

        $this->service->issueTokens(1, '/tmp/x.json');
    }

    public function test_issue_tokens_rejects_invalid_count(): void
    {
        $this->allowEnvironment();

        $this->expectException(InvalidArgumentException::class);

        $this->service->issueTokens(0, '/tmp/x.json');
    }

    public function test_prepare_menu_enables_dishes_and_invalidates_cache(): void
    {
        $this->allowEnvironment();

        $this->loadTestDataRepository->method('listActiveRestaurantIds')->willReturn([10, 20]);
        $this->loadTestDataRepository->expects($this->once())
            ->method('enableUnavailableDishesForRestaurants')
            ->with([10, 20])
            ->willReturn(3);
        $this->catalogCacheInvalidator->expects($this->once())->method('invalidateAll');

        $result = $this->service->prepareMenu();

        $this->assertSame(3, $result->dishesEnabled);
        $this->assertSame([10, 20], $result->restaurantIds);
    }

    public function test_prepare_menu_skips_when_no_active_restaurants(): void
    {
        $this->allowEnvironment();

        $this->loadTestDataRepository->method('listActiveRestaurantIds')->willReturn([]);
        $this->loadTestDataRepository->expects($this->never())->method('enableUnavailableDishesForRestaurants');
        $this->catalogCacheInvalidator->expects($this->never())->method('invalidateAll');

        $result = $this->service->prepareMenu();

        $this->assertSame(0, $result->dishesEnabled);
        $this->assertSame([], $result->restaurantIds);
    }

    public function test_cleanup_deletes_orders_then_carts(): void
    {
        $this->allowEnvironment();
        $ids = MaxLoadTestUserIds::range(2);

        $this->loadTestDataRepository->expects($this->once())
            ->method('deleteOrdersForMaxUserIds')
            ->with($ids)
            ->willReturn(4);
        $this->loadTestDataRepository->expects($this->once())
            ->method('deleteCartsForMaxUserIds')
            ->with($ids)
            ->willReturn(2);

        $result = $this->service->cleanup(2);

        $this->assertSame(4, $result->ordersDeleted);
        $this->assertSame(2, $result->cartsDeleted);
        $this->assertSame($ids, $result->maxUserIds);
    }

    private function allowEnvironment(): void
    {
        $this->environment->method('is')->with(['local', 'testing'])->willReturn(true);
    }
}

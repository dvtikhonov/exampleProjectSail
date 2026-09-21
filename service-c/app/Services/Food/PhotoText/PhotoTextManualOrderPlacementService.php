<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCustomerResolverInterface;
use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\Order\ManualOrderSubmissionServiceInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishLineResolverInterface;
use App\Contracts\Food\PhotoText\PhotoTextManualOrderPlacementServiceInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\PhotoText\PhotoTextMatchedLineDto;
use App\DTO\Food\PhotoText\PhotoTextPlacementResultDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Exceptions\Food\FoodDomainException;

/**
 * Сверка имён в restaurant_id и оформление matched как черновик после сканирования.
 */
class PhotoTextManualOrderPlacementService implements PhotoTextManualOrderPlacementServiceInterface
{
    public function __construct(
        private readonly ManualOrderCustomerResolverInterface $customerResolver,
        private readonly PhotoTextDishLineResolverInterface $dishLineResolver,
        private readonly ManualOrderCartServiceInterface $manualOrderCartService,
        private readonly ManualOrderSubmissionServiceInterface $orderSubmissionService,
        private readonly MaxUserIdentityRepositoryInterface $maxUserRepository,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
        private readonly MaxAiAccessServiceInterface $maxAiAccessService,
        private readonly ClockInterface $clock,
        private readonly ApplicationConfigInterface $config,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function match(string $customerQuery, int $restaurantId, array $items): PhotoTextPlacementResultDto
    {
        $this->customerResolver->resolveExactlyOne($customerQuery);

        return $this->dishLineResolver->resolveAgentItems($items, $restaurantId);
    }

    /**
     * {@inheritDoc}
     */
    public function place(
        string $customerQuery,
        string $orderDate,
        int $restaurantId,
        array $items,
    ): PhotoTextPlacementResultDto {
        $customer = $this->customerResolver->resolveExactlyOne($customerQuery);
        $result = $this->dishLineResolver->resolveAgentItems($items, $restaurantId);

        if ($result->matchedCount === 0 || $result->matched === []) {
            return $result;
        }

        $manager = $this->resolveManager();
        $this->assertMatchedBelongToRestaurant($result->matched, $restaurantId);

        /** @var OrderDto $order */
        $order = $this->transactionManager->run(function () use (
            $customer,
            $manager,
            $result,
            $orderDate,
        ): OrderDto {
            $this->manualOrderCartService->clear($customer, $manager);

            foreach ($result->matched as $line) {
                $this->manualOrderCartService->addItem(
                    $customer,
                    $manager,
                    $line->dishId,
                    $line->quantity,
                    $line->comboRef,
                    $line->comboPartnerDishId,
                );
            }

            return $this->orderSubmissionService->submitDraftAfterScanning(
                $customer,
                $manager,
                $orderDate,
            );
        });

        return $result->withOrderId($order->id);
    }

    /**
     * Менеджер write-операций = активный AI-пользователь с ролью max_manager.
     *
     * PHOTOTEXT_MANAGER_MAX_USER_ID (>0) — опциональный allow-list: active должен совпадать.
     *
     * @throws FoodDomainException
     */
    private function resolveManager(): MaxUserIdentity
    {
        $status = $this->maxAiAccessService->getStatus($this->clock->now());

        if (! $status->enabled || $status->activeMaxUserId === null) {
            throw new FoodDomainException('Доступ AI к базе не разрешён.', 403);
        }

        $managerId = $status->activeMaxUserId;
        $allowedManagerId = (int) $this->config->get('phototext.manager_max_user_id');

        if ($allowedManagerId > 0 && $allowedManagerId !== $managerId) {
            throw new FoodDomainException(
                'Активный AI-пользователь не совпадает с PhotoText-менеджером.',
                403,
            );
        }

        $manager = $this->maxUserRepository->findByMaxUserId($managerId);

        if ($manager === null) {
            throw new FoodDomainException('PhotoText-менеджер не найден.', 403);
        }

        if (! $this->foodOrderAdminRepository->hasActiveRole($managerId, FoodOrderAdminRole::MaxManager)) {
            throw new FoodDomainException('PhotoText-менеджер не имеет роли max_manager.', 403);
        }

        return new MaxUserIdentity(
            maxUserId: $managerId,
            adminRoles: [FoodOrderAdminRole::MaxManager],
        );
    }

    /**
     * Защита от смешения ресторанов: matched только из переданного restaurant_id.
     *
     * @param  list<PhotoTextMatchedLineDto>  $matched
     *
     * @throws FoodDomainException
     */
    private function assertMatchedBelongToRestaurant(array $matched, int $restaurantId): void
    {
        foreach ($matched as $line) {
            if ($line->restaurantId !== $restaurantId) {
                throw new FoodDomainException(
                    'Блюдо не относится к указанному ресторану: '.$line->dishName,
                );
            }
        }
    }
}

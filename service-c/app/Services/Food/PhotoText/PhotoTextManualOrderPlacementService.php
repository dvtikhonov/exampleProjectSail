<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCustomerResolverInterface;
use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\Order\OrderSubmissionServiceInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishLineResolverInterface;
use App\Contracts\Food\PhotoText\PhotoTextManualOrderPlacementServiceInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Food\PhotoText\PhotoTextMatchedLineDto;
use App\DTO\Food\PhotoText\PhotoTextPlacementResultDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Max\MaxUser;

/**
 * Сверка имён в restaurant_id и оформление matched как черновик после сканирования.
 */
class PhotoTextManualOrderPlacementService implements PhotoTextManualOrderPlacementServiceInterface
{
    public function __construct(
        private readonly ManualOrderCustomerResolverInterface $customerResolver,
        private readonly PhotoTextDishLineResolverInterface $dishLineResolver,
        private readonly ManualOrderCartServiceInterface $manualOrderCartService,
        private readonly OrderSubmissionServiceInterface $orderSubmissionService,
        private readonly MaxUserRepositoryInterface $maxUserRepository,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
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

        $order = $this->orderSubmissionService->submitDraftAfterScanning($customer, $manager, $orderDate);

        return $result->withOrderId($order->id);
    }

    /**
     * Менеджер из PHOTOTEXT_MANAGER_MAX_USER_ID с активной ролью max_manager.
     *
     * @throws FoodDomainException
     */
    private function resolveManager(): MaxUser
    {
        $managerId = (int) config('phototext.manager_max_user_id');

        if ($managerId < 1) {
            throw new FoodDomainException('PhotoText-менеджер не настроен.', 500);
        }

        $manager = $this->maxUserRepository->findByMaxUserId($managerId);

        if ($manager === null) {
            throw new FoodDomainException('PhotoText-менеджер не найден.', 500);
        }

        if (! $this->foodOrderAdminRepository->hasActiveRole($managerId, FoodOrderAdminRole::MaxManager)) {
            throw new FoodDomainException('PhotoText-менеджер не имеет роли max_manager.', 500);
        }

        return $manager;
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

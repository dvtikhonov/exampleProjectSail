<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\ManualOrder\ManualOrderCustomerResolverInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\DTO\Max\MaxUserRecord;
use App\Exceptions\Food\FoodDomainException;

/**
 * Поиск ровно одного клиента MAX по подстроке имени (STOP при 0 или >1).
 */
class ManualOrderCustomerResolver implements ManualOrderCustomerResolverInterface
{
    public function __construct(
        private readonly MaxUserRepositoryInterface $maxUserRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolveExactlyOne(string $customerQuery): MaxUserIdentity
    {
        $query = trim($customerQuery);

        if ($query === '') {
            throw new FoodDomainException('Клиент не найден: пустой ключ поиска.');
        }

        $users = $this->maxUserRepository->findByNameFieldsSubstring($query);

        if ($users === []) {
            throw new FoodDomainException('Клиент не найден: '.$query);
        }

        if (count($users) > 1) {
            $ids = implode(
                ', ',
                array_map(static fn (MaxUserRecord $user): string => (string) $user->maxUserId, $users),
            );

            throw new FoodDomainException('Найдено несколько клиентов: '.$query.' (max_user_id: '.$ids.')');
        }

        $customer = $users[0];

        return new MaxUserIdentity(
            maxUserId: $customer->maxUserId,
            adminRoles: [],
        );
    }
}

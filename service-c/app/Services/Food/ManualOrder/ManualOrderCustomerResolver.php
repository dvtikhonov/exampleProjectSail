<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\ManualOrder\ManualOrderCustomerResolverInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Max\MaxUser;

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
    public function resolveExactlyOne(string $customerQuery): MaxUser
    {
        $query = trim($customerQuery);

        if ($query === '') {
            throw new FoodDomainException('Клиент не найден: пустой ключ поиска.');
        }

        $users = $this->maxUserRepository->findByNameFieldsSubstring($query);

        if ($users->isEmpty()) {
            throw new FoodDomainException('Клиент не найден: '.$query);
        }

        if ($users->count() > 1) {
            $ids = $users
                ->map(static fn (MaxUser $user): string => (string) $user->max_user_id)
                ->implode(', ');

            throw new FoodDomainException('Найдено несколько клиентов: '.$query.' (max_user_id: '.$ids.')');
        }

        /** @var MaxUser $customer */
        $customer = $users->first();

        return $customer;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Models\MaxUser;
use Illuminate\Console\Command;

/**
 * Artisan-команда назначения роли администратора заказов еды и меню.
 */
class MaxFoodAdminAssignCommand extends Command
{
    protected $signature = 'max:food-admin:assign
                            {max_user_id : MAX user ID из max_users}
                            {role : address_reviewer, composition_reviewer или menu_manager}';

    protected $description = 'Назначить роль администратора заказов еды или меню пользователю MAX';

    /**
     * Назначает активную роль администратора, если пользователь MAX существует.
     */
    public function handle(FoodOrderAdminRepositoryInterface $adminRepository): int
    {
        $maxUserId = (int) $this->argument('max_user_id');
        $roleValue = (string) $this->argument('role');
        $role = FoodOrderAdminRole::tryFrom($roleValue);

        if ($role === null) {
            $this->error(
                'Недопустимая роль. Допустимые значения: '
                .FoodOrderAdminRole::AddressReviewer->value.', '
                .FoodOrderAdminRole::CompositionReviewer->value.', '
                .FoodOrderAdminRole::MenuManager->value.'.',
            );

            return self::FAILURE;
        }

        if (! MaxUser::query()->whereKey($maxUserId)->exists()) {
            $this->error("Пользователь MAX с max_user_id={$maxUserId} не найден в max_users.");

            return self::FAILURE;
        }

        $assignment = $adminRepository->assignActiveRole($maxUserId, $role);

        $this->info(
            "Роль {$role->value} назначена пользователю max_user_id={$maxUserId} "
            ."(запись #{$assignment->id}, is_active=1).",
        );

        return self::SUCCESS;
    }
}

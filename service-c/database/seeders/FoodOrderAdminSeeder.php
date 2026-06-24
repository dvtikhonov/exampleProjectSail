<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Food\FoodOrderAdminRole;
use App\Models\FoodOrderAdmin;
use App\Models\MaxUser;
use Illuminate\Database\Seeder;

/**
 * Демо-администраторы проверки заказов еды (MAX mini-app).
 */
class FoodOrderAdminSeeder extends Seeder
{
    public function run(): void
    {
        MaxUser::query()->updateOrCreate(
            ['max_user_id' => 1003],
            [
                'first_name' => 'Demo',
                'last_name' => 'Админ адреса',
                'username' => 'demo_address_admin',
                'language_code' => 'ru',
            ],
        );

        MaxUser::query()->updateOrCreate(
            ['max_user_id' => 1004],
            [
                'first_name' => 'Demo',
                'last_name' => 'Админ состава',
                'username' => 'demo_composition_admin',
                'language_code' => 'ru',
            ],
        );

        FoodOrderAdmin::query()->updateOrCreate(
            [
                'max_user_id' => 1003,
                'role' => FoodOrderAdminRole::AddressReviewer,
            ],
            [
                'is_active' => true,
            ],
        );

        FoodOrderAdmin::query()->updateOrCreate(
            [
                'max_user_id' => 1004,
                'role' => FoodOrderAdminRole::CompositionReviewer,
            ],
            [
                'is_active' => true,
            ],
        );
    }
}

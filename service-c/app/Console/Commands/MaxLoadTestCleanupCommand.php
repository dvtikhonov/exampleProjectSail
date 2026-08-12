<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Max\MaxLoadTestServiceInterface;
use App\Support\Max\MaxLoadTestUserIds;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Artisan-команда очистки заказов и корзин load-test пользователей после k6-прогона.
 */
class MaxLoadTestCleanupCommand extends Command
{
    protected $signature = 'max:load-test:cleanup
                            {count='.MaxLoadTestUserIds::DEFAULT_COUNT.' : Число VU / пользователей (тот же диапазон, что у tokens)}';

    protected $description = 'Удалить заказы и корзины load-test пользователей (только local/testing)';

    /**
     * Удаляет max_food_orders, затем max_carts для диапазона load-test id.
     */
    public function handle(MaxLoadTestServiceInterface $loadTestService): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Команда доступна только при APP_ENV=local или testing.');

            return self::FAILURE;
        }

        $count = (int) $this->argument('count');

        try {
            $result = $loadTestService->cleanup($count);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Не удалось очистить данные: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Удалено заказов: %d, корзин: %d (max_user_id %d–%d).',
            $result->ordersDeleted,
            $result->cartsDeleted,
            MaxLoadTestUserIds::BASE_ID,
            MaxLoadTestUserIds::BASE_ID + $count - 1,
        ));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Max\MaxLoadTestServiceInterface;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Artisan-команда включения меню активных ресторанов перед k6-прогоном.
 */
class MaxLoadTestPrepareMenuCommand extends Command
{
    protected $signature = 'max:load-test:prepare-menu';

    protected $description = 'Включить is_available у блюд активных ресторанов и сбросить кэш меню (только local/testing)';

    /**
     * Делает клиентское меню непустым для food_order_flow (k6).
     */
    public function handle(MaxLoadTestServiceInterface $loadTestService): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Команда доступна только при APP_ENV=local или testing.');

            return self::FAILURE;
        }

        try {
            $result = $loadTestService->prepareMenu();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Не удалось подготовить меню: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($result->restaurantIds === []) {
            $this->warn('Нет активных ресторанов — меню не изменено.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Включено блюд: %d (рестораны: %s). Кэш меню сброшен.',
            $result->dishesEnabled,
            implode(', ', $result->restaurantIds),
        ));

        return self::SUCCESS;
    }
}

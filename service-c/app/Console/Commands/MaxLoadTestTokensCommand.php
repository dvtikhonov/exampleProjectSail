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
 * Artisan-команда выдачи Sanctum-токенов для VU нагрузочного стенда service-h.
 */
class MaxLoadTestTokensCommand extends Command
{
    protected $signature = 'max:load-test:tokens
                            {count='.MaxLoadTestUserIds::DEFAULT_COUNT.' : Число VU / пользователей (max_user_id от 900001)}
                            {--output= : Путь к JSON (по умолчанию storage/app/load-test-tokens.json)}';

    protected $description = 'Создать load-test MaxUser и выдать Sanctum-токены max-miniapp (только local/testing)';

    /**
     * Выдаёт токены и пишет JSON для k6.
     */
    public function handle(MaxLoadTestServiceInterface $loadTestService): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Команда доступна только при APP_ENV=local или testing.');

            return self::FAILURE;
        }

        $count = (int) $this->argument('count');
        $output = $this->option('output');
        $outputPath = is_string($output) && $output !== ''
            ? $output
            : MaxLoadTestUserIds::defaultTokenFilePath();

        try {
            $result = $loadTestService->issueTokens($count, $outputPath);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Не удалось выдать токены: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Выдано токенов: %d (max_user_id %d–%d).',
            count($result->tokens),
            MaxLoadTestUserIds::BASE_ID,
            MaxLoadTestUserIds::BASE_ID + $count - 1,
        ));
        $this->line('Файл: '.$result->outputPath);

        return self::SUCCESS;
    }
}

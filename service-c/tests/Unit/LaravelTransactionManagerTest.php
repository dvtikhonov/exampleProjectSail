<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\TransactionManagerInterface;
use App\Models\Max\MaxUser;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class LaravelTransactionManagerTest extends TestCase
{
    /** Успешная транзакция коммитит изменения в sail_db_testing. */
    public function test_run_commits_changes_on_success(): void
    {
        $maxUserId = 99_001;

        $this->app->make(TransactionManagerInterface::class)->run(
            static function () use ($maxUserId): void {
                MaxUser::query()->updateOrCreate(
                    ['max_user_id' => $maxUserId],
                    ['first_name' => 'Committed'],
                );
            },
        );

        $this->assertTrue(
            MaxUser::query()->whereKey($maxUserId)->exists(),
        );
    }

    /** Исключение внутри транзакции откатывает изменения. */
    public function test_run_rolls_back_changes_on_exception(): void
    {
        $maxUserId = 99_002;

        try {
            $this->app->make(TransactionManagerInterface::class)->run(
                static function () use ($maxUserId): void {
                    MaxUser::query()->updateOrCreate(
                        ['max_user_id' => $maxUserId],
                        ['first_name' => 'Rolled back'],
                    );

                    throw new RuntimeException('Force rollback');
                },
            );
        } catch (RuntimeException) {
            // expected
        }

        $this->assertFalse(
            MaxUser::query()->whereKey($maxUserId)->exists(),
        );
        $this->assertSame(0, DB::transactionLevel());
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\TransactionManagerInterface;
use Illuminate\Support\Facades\DB;

/**
 * Laravel-адаптер {@see TransactionManagerInterface} поверх DB::transaction.
 */
class LaravelTransactionManager implements TransactionManagerInterface
{
    /**
     * {@inheritDoc}
     */
    public function run(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}

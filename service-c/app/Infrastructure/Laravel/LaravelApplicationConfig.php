<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\ApplicationConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Laravel-адаптер {@see ApplicationConfigInterface} поверх Config Repository.
 */
class LaravelApplicationConfig implements ApplicationConfigInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }
}

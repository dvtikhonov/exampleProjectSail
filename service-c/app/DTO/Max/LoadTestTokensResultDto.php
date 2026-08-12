<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Результат выдачи токенов для нагрузочного прогона.
 */
readonly class LoadTestTokensResultDto
{
    /**
     * @param  list<LoadTestTokenDto>  $tokens
     */
    public function __construct(
        public array $tokens,
        public string $outputPath,
    ) {}
}

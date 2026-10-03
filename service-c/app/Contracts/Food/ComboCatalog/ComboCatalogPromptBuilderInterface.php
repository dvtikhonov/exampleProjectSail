<?php

declare(strict_types=1);

namespace App\Contracts\Food\ComboCatalog;

use App\DTO\Food\ComboCatalog\ComboCatalogPromptDishDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\Enums\Food\ComboCatalog\ComboCatalogPromptScenario;

/**
 * Сборка system/user промпта из ENUM-секций и динамического контекста.
 */
interface ComboCatalogPromptBuilderInterface
{
    /**
     * @param  list<ComboCatalogPromptDishDto>  $sourceDishes
     * @param  list<array{id: int|string, name: string}>  $brisklyItems
     */
    public function build(
        ComboCatalogPromptScenario $scenario,
        int $restaurantId,
        ?string $clarification,
        array $sourceDishes,
        array $brisklyItems,
    ): ComboCatalogPromptDto;
}

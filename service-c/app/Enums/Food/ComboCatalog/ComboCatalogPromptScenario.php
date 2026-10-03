<?php

declare(strict_types=1);

namespace App\Enums\Food\ComboCatalog;

/**
 * Сценарий промпта: выбирает набор секций ComboCatalogPromptSection.
 */
enum ComboCatalogPromptScenario: string
{
    case MatchNames = 'match_names';

    /**
     * Секции system-промпта для сценария (порядок важен).
     *
     * @return list<ComboCatalogPromptSection>
     */
    public function sections(): array
    {
        return match ($this) {
            self::MatchNames => [
                ComboCatalogPromptSection::SystemRole,
                ComboCatalogPromptSection::ComboCartesianRules,
                ComboCatalogPromptSection::WeightRules,
                ComboCatalogPromptSection::NamingRules,
                ComboCatalogPromptSection::OutputContract,
            ],
        };
    }
}

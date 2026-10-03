<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\ComboCatalog\ComboCatalogPromptDishDto;
use App\Enums\Food\ComboCatalog\ComboCatalogPromptScenario;
use App\Enums\Food\ComboCatalog\ComboCatalogPromptSection;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Services\Food\ComboCatalog\ComboCatalogPromptBuilder;
use App\Services\Food\ComboCatalog\WeightLabelCanonicalizer;
use PHPUnit\Framework\TestCase;

class ComboCatalogPromptBuilderTest extends TestCase
{
    public function test_match_names_sections_are_non_empty(): void
    {
        $sections = ComboCatalogPromptScenario::MatchNames->sections();

        $this->assertNotEmpty($sections);
        foreach ($sections as $section) {
            $this->assertInstanceOf(ComboCatalogPromptSection::class, $section);
            $this->assertNotSame('', trim($section->text()));
        }
    }

    public function test_every_section_case_has_non_empty_text(): void
    {
        foreach (ComboCatalogPromptSection::cases() as $section) {
            $this->assertNotSame('', trim($section->text()), $section->name);
        }
    }

    public function test_build_system_is_concatenation_of_scenario_sections(): void
    {
        $scenario = ComboCatalogPromptScenario::MatchNames;
        $builder = new ComboCatalogPromptBuilder(new WeightLabelCanonicalizer);

        $prompt = $builder->build(
            $scenario,
            restaurantId: 7,
            clarification: null,
            sourceDishes: [],
            brisklyItems: [],
        );

        $expectedSystem = implode(
            "\n\n",
            array_map(
                static fn (ComboCatalogPromptSection $section): string => $section->text(),
                $scenario->sections(),
            ),
        );

        $this->assertSame($expectedSystem, $prompt->system);
        foreach ($scenario->sections() as $section) {
            $this->assertStringContainsString($section->text(), $prompt->system);
        }
    }

    public function test_build_user_uses_canonical_weight_not_compact_form(): void
    {
        $builder = new ComboCatalogPromptBuilder(new WeightLabelCanonicalizer);

        $prompt = $builder->build(
            ComboCatalogPromptScenario::MatchNames,
            restaurantId: 3,
            clarification: 'не обращать внимание на скобки и вес',
            sourceDishes: [
                new ComboCatalogPromptDishDto(
                    id: 11,
                    name: 'Салат с морковью',
                    price: '120.00',
                    weightLabel: '120г',
                ),
                new ComboCatalogPromptDishDto(
                    id: 12,
                    name: 'Суп',
                    price: '90.00',
                    weightLabel: '250 г.',
                ),
            ],
            brisklyItems: [
                ['id' => 100, 'name' => 'Салат морковь'],
            ],
        );

        $this->assertStringNotContainsString('120г', $prompt->user);
        $this->assertStringNotContainsString('250 г.', $prompt->user);
        $this->assertStringContainsString('120 грамм', $prompt->user);
        $this->assertStringContainsString('250 грамм', $prompt->user);
        $this->assertStringContainsString('не обращать внимание на скобки и вес', $prompt->user);
        $this->assertStringContainsString('"restaurant_id":3', $prompt->user);
        $this->assertStringContainsString('Салат морковь', $prompt->user);
    }

    public function test_empty_clarification_uses_explicit_marker(): void
    {
        $builder = new ComboCatalogPromptBuilder(new WeightLabelCanonicalizer);

        $prompt = $builder->build(
            ComboCatalogPromptScenario::MatchNames,
            restaurantId: 1,
            clarification: '   ',
            sourceDishes: [],
            brisklyItems: [],
        );

        $this->assertStringContainsString('дополнительных уточнений нет', $prompt->user);
    }

    public function test_weight_unit_canonical_word_and_aliases(): void
    {
        $this->assertSame('грамм', DishWeightUnit::Gram->canonicalWord());
        $this->assertSame('килограмм', DishWeightUnit::Kilogram->canonicalWord());
        $this->assertSame('миллилитр', DishWeightUnit::Milliliter->canonicalWord());
        $this->assertSame('литр', DishWeightUnit::Liter->canonicalWord());

        $this->assertContains('г', DishWeightUnit::Gram->aliases());
        $this->assertContains('грамм', DishWeightUnit::Gram->aliases());
        $this->assertSame(DishWeightUnit::Gram, DishWeightUnit::tryFromAlias('г.'));
        $this->assertSame(DishWeightUnit::Kilogram, DishWeightUnit::tryFromAlias('кг'));
        $this->assertNull(DishWeightUnit::tryFromAlias('шт'));
    }
}

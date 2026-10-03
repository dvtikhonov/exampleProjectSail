<?php

declare(strict_types=1);

namespace App\Services\Food\ComboCatalog;

use App\Contracts\Food\ComboCatalog\ComboCatalogPromptBuilderInterface;
use App\Contracts\Food\ComboCatalog\WeightLabelCanonicalizerInterface;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDishDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\Enums\Food\ComboCatalog\ComboCatalogPromptScenario;

/**
 * Склеивает ENUM-секции в system и собирает user-контекст с каноническим весом.
 */
final class ComboCatalogPromptBuilder implements ComboCatalogPromptBuilderInterface
{
    private const string EMPTY_CLARIFICATION_MARKER = 'дополнительных уточнений нет';

    public function __construct(
        private readonly WeightLabelCanonicalizerInterface $weightLabelCanonicalizer,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function build(
        ComboCatalogPromptScenario $scenario,
        int $restaurantId,
        ?string $clarification,
        array $sourceDishes,
        array $brisklyItems,
    ): ComboCatalogPromptDto {
        $system = implode(
            "\n\n",
            array_map(
                static fn ($section): string => $section->text(),
                $scenario->sections(),
            ),
        );

        $user = $this->buildUser($restaurantId, $clarification, $sourceDishes, $brisklyItems);

        return new ComboCatalogPromptDto(system: $system, user: $user);
    }

    /**
     * @param  list<ComboCatalogPromptDishDto>  $sourceDishes
     * @param  list<array{id: int|string, name: string}>  $brisklyItems
     */
    private function buildUser(
        int $restaurantId,
        ?string $clarification,
        array $sourceDishes,
        array $brisklyItems,
    ): string {
        $clarificationText = trim((string) $clarification);
        if ($clarificationText === '') {
            $clarificationText = self::EMPTY_CLARIFICATION_MARKER;
        }

        $dishesPayload = [];
        foreach ($sourceDishes as $dish) {
            $weightLabel = $this->weightLabelCanonicalizer->fromLabel($dish->weightLabel);

            $entry = [
                'id' => $dish->id,
                'name' => $dish->name,
                'price' => $dish->price,
                'weight_label' => $weightLabel,
            ];
            if ($dish->lineKey !== null && $dish->lineKey !== '') {
                $entry['line_key'] = $dish->lineKey;
            }
            $dishesPayload[] = $entry;
        }

        $brisklyPayload = [];
        foreach ($brisklyItems as $item) {
            $brisklyPayload[] = [
                'id' => $item['id'],
                'name' => $item['name'],
            ];
        }

        $payload = [
            'restaurant_id' => $restaurantId,
            'clarification' => $clarificationText,
            'source_dishes' => $dishesPayload,
            'briskly_items' => $brisklyPayload,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return "Контекст матчинга (JSON):\n".$json;
    }
}

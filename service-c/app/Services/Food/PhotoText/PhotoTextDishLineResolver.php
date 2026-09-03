<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\PhotoText\PhotoTextComboRefGrouperInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishLineResolverInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishNameMatcherInterface;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\PhotoText\PhotoTextAgentItemDto;
use App\DTO\Food\PhotoText\PhotoTextIssueDto;
use App\DTO\Food\PhotoText\PhotoTextMatchedLineDto;
use App\DTO\Food\PhotoText\PhotoTextPlacementResultDto;
use App\Enums\Food\PhotoText\PhotoTextComboRefGroupKind;
use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Composition\ComboPairValidator;

/**
 * Матчинг канонических позиций агента PhotoText к блюдам/комбо: matched[] + issues[].
 */
class PhotoTextDishLineResolver implements PhotoTextDishLineResolverInterface
{
    public function __construct(
        private readonly PhotoTextDishNameMatcherInterface $dishNameMatcher,
        private readonly ComboPairValidator $comboPairValidator,
        private readonly PhotoTextComboRefGrouperInterface $comboRefGrouper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolveAgentItems(array $items, int $restaurantId): PhotoTextPlacementResultDto
    {
        $matched = [];
        $issues = [];

        foreach ($this->comboRefGrouper->group($items) as $group) {
            if ($group->kind === PhotoTextComboRefGroupKind::Unresolved) {
                foreach ($group->items as $item) {
                    $issues[] = $this->agentComboUnresolved(
                        $item,
                        'Неполная или некорректная комбо-пара combo_ref.',
                    );
                }

                continue;
            }

            if ($group->kind === PhotoTextComboRefGroupKind::Single) {
                $item = $group->items[0];
                $resolved = $this->resolveAgentSingle($item, $restaurantId);

                if ($resolved instanceof PhotoTextMatchedLineDto) {
                    $matched[] = $resolved;
                } else {
                    $issues[] = $resolved;
                }

                continue;
            }

            $pairResolved = $this->resolveAgentPair(
                $group->items[0],
                $group->items[1],
                $restaurantId,
            );
            $matched = [...$matched, ...$pairResolved['matched']];
            $issues = [...$issues, ...$pairResolved['issues']];
        }

        return new PhotoTextPlacementResultDto(
            matchedCount: count($matched),
            matched: $matched,
            issues: $issues,
            orderId: null,
        );
    }

    private function resolveAgentSingle(
        PhotoTextAgentItemDto $item,
        int $restaurantId,
    ): PhotoTextMatchedLineDto|PhotoTextIssueDto {
        $searchName = trim($item->name);

        if ($searchName === '') {
            return new PhotoTextIssueDto(
                code: PhotoTextMatchIssueCode::DishNotFound,
                message: 'Пустое название блюда после нормализации.',
                rawTitle: $item->name,
                quantity: $item->quantity,
            );
        }

        $matchResult = $this->dishNameMatcher->match($searchName, $restaurantId);

        if (! $matchResult->isSuccess()) {
            return new PhotoTextIssueDto(
                code: $matchResult->code ?? PhotoTextMatchIssueCode::DishNotFound,
                message: $matchResult->message ?? 'Блюдо не найдено: '.$searchName,
                rawTitle: $item->name,
                quantity: $item->quantity,
            );
        }

        return $this->matchedFromDish($item->name, $item->quantity, $matchResult->dish);
    }

    /**
     * @return array{matched: list<PhotoTextMatchedLineDto>, issues: list<PhotoTextIssueDto>}
     */
    private function resolveAgentPair(
        PhotoTextAgentItemDto $leftItem,
        PhotoTextAgentItemDto $rightItem,
        int $restaurantId,
    ): array {
        $leftName = trim($leftItem->name);
        $rightName = trim($rightItem->name);
        $comboRef = trim((string) $leftItem->comboRef);
        $message = 'Комбо не резолвится: «'.$leftName.'» / «'.$rightName.'».';

        if ($leftName === '' || $rightName === '' || $comboRef === '') {
            return [
                'matched' => [],
                'issues' => [
                    $this->agentComboUnresolved($leftItem, $message),
                    $this->agentComboUnresolved($rightItem, $message),
                ],
            ];
        }

        $leftResult = $this->dishNameMatcher->match($leftName, $restaurantId);
        $rightResult = $this->dishNameMatcher->match($rightName, $restaurantId);
        $left = $leftResult->dish;
        $right = $rightResult->dish;

        if (! $left instanceof DishRecord || ! $right instanceof DishRecord) {
            return [
                'matched' => [],
                'issues' => [
                    $this->agentComboUnresolved($leftItem, $message),
                    $this->agentComboUnresolved($rightItem, $message),
                ],
            ];
        }

        try {
            $this->comboPairValidator->assertCompatiblePair(
                $left,
                $right,
                requirePartnerAvailable: false,
            );
        } catch (FoodDomainException $exception) {
            $incompatible = 'Комбо несовместимо: '.$exception->getMessage();

            return [
                'matched' => [],
                'issues' => [
                    $this->agentComboUnresolved($leftItem, $incompatible),
                    $this->agentComboUnresolved($rightItem, $incompatible),
                ],
            ];
        }

        return [
            'matched' => [
                $this->matchedFromDish($leftItem->name, $leftItem->quantity, $left, $right, $comboRef),
                $this->matchedFromDish($rightItem->name, $rightItem->quantity, $right, $left, $comboRef),
            ],
            'issues' => [],
        ];
    }

    private function matchedFromDish(
        string $rawTitle,
        int $quantity,
        DishRecord $dish,
        ?DishRecord $partner = null,
        ?string $comboRef = null,
    ): PhotoTextMatchedLineDto {
        return new PhotoTextMatchedLineDto(
            rawTitle: $rawTitle,
            quantity: $quantity,
            dishId: $dish->id,
            dishName: $dish->name,
            comboPartnerDishId: $partner?->id,
            comboPartnerDishName: $partner?->name,
            comboRef: $comboRef,
            restaurantId: (int) ($dish->menuCategory?->restaurantId ?? 0),
        );
    }

    private function agentComboUnresolved(PhotoTextAgentItemDto $item, string $message): PhotoTextIssueDto
    {
        return new PhotoTextIssueDto(
            code: PhotoTextMatchIssueCode::ComboUnresolved,
            message: $message,
            rawTitle: $item->name,
            quantity: $item->quantity,
        );
    }
}

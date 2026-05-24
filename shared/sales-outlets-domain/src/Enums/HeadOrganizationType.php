<?php

namespace Shared\SalesOutletsDomain\Enums;

enum HeadOrganizationType: string
{
    case IndividualEntrepreneur = 'ip';
    case LimitedLiabilityCompany = 'ooo';
    case JointStockCompany = 'ao';
    case AgriculturalCooperative = 'spk';

    public static function fromLabelOrValue(string $value): ?self
    {
        $normalizedValue = mb_strtolower(trim($value));

        foreach (self::cases() as $type) {
            if ($normalizedValue === mb_strtolower($type->value) || $normalizedValue === mb_strtolower($type->label())) {
                return $type;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::IndividualEntrepreneur => 'ИП',
            self::LimitedLiabilityCompany => 'ООО',
            self::JointStockCompany => 'АО',
            self::AgriculturalCooperative => 'СПК',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}

<?php

namespace App\Enums;

enum HeadOrganizationType: string
{
    case IndividualEntrepreneur = 'ip';
    case LimitedLiabilityCompany = 'ooo';
    case JointStockCompany = 'ao';
    case AgriculturalCooperative = 'spk';

    public function label(): string
    {
        return match ($this) {
            self::IndividualEntrepreneur => 'ИП',
            self::LimitedLiabilityCompany => 'ООО',
            self::JointStockCompany => 'АО',
            self::AgriculturalCooperative => 'СПК',
        };
    }
}

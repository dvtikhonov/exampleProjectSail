<?php

declare(strict_types=1);

namespace Shared\SalesOutletsDomain\Enums;

enum SalesOutletStatus: string
{
    case Approved = 'approved';
    case Review = 'review';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Одобрено',
            self::Review => 'На проверке',
            self::Blocked => 'Есть изменения',
        };
    }

    public function rowTone(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Review => 'warning',
            self::Blocked => 'danger',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Режим отображения блюд в админ-списке: все / доступные / скрытые.
 */
enum AdminDishAvailabilityFilter: string
{
    case All = 'all';
    case Available = 'available';
    case Hidden = 'hidden';

    /**
     * Значение фильтра для колонки is_available; null — без фильтра.
     */
    public function toIsAvailable(): ?bool
    {
        return match ($this) {
            self::All => null,
            self::Available => true,
            self::Hidden => false,
        };
    }
}

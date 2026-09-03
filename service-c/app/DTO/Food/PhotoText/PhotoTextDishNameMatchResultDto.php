<?php

declare(strict_types=1);

namespace App\DTO\Food\PhotoText;

use App\DTO\Food\Menu\DishRecord;
use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;

/**
 * Результат exact-match имени блюда PhotoText к каталогу ресторана.
 */
readonly class PhotoTextDishNameMatchResultDto
{
    public function __construct(
        public ?DishRecord $dish = null,
        public ?PhotoTextMatchIssueCode $code = null,
        public ?string $message = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->dish !== null;
    }

    public static function success(DishRecord $dish): self
    {
        return new self(dish: $dish);
    }

    public static function failure(PhotoTextMatchIssueCode $code, string $message): self
    {
        return new self(code: $code, message: $message);
    }
}

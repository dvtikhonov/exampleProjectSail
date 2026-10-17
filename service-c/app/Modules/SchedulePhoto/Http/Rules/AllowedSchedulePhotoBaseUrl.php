<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Http\Rules;

use App\Modules\SchedulePhoto\Support\SchedulePhotoOriginAllowList;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SSRF-защита: base_url только из schedule_photo.allowed_base_urls (exact origin).
 */
final class AllowedSchedulePhotoBaseUrl implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        unset($attribute);

        if (! is_string($value) || $this->allowList()->normalize($value) === null) {
            $fail(SchedulePhotoOriginAllowList::REJECTION_MESSAGE);
        }
    }

    private function allowList(): SchedulePhotoOriginAllowList
    {
        /** @var list<string>|mixed $allowedRaw */
        $allowedRaw = config('schedule_photo.allowed_base_urls', []);
        $allowed = [];

        if (is_array($allowedRaw)) {
            foreach ($allowedRaw as $origin) {
                if (is_string($origin)) {
                    $allowed[] = $origin;
                }
            }
        }

        return new SchedulePhotoOriginAllowList($allowed);
    }
}

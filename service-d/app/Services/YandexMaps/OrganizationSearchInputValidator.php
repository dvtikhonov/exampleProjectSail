<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\DTO\YandexMaps\OrganizationSearchInputDto;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Валидация и разбор пользовательского ввода для поиска организации.
 *
 * Поддерживает:
 * - прямую ссылку на Яндекс.Карты (с опциональным уточнением через пробел);
 * - произвольную ссылку на сайт + уточнение (город, филиал и т.д.).
 */
class OrganizationSearchInputValidator
{
    private const YANDEX_MAPS_PATTERN = '#^https?://yandex\.(ru|com|kz|com\.tr)/maps/#i';

    private const YANDEX_MAPS_INPUT_PATTERN = '#^(https?://yandex\.(?:ru|com|kz|com\.tr)/maps/\S*)(?:\s+(.+))?$#iu';

    public function isValid(string $input): bool
    {
        return $this->parse($input) !== null;
    }

    /**
     * Разбирает строку на linkPart и clarification; null — невалидный ввод.
     */
    public function parse(string $input): ?OrganizationSearchInputDto
    {
        $rawInput = trim($input);

        if ($rawInput === '') {
            return null;
        }

        if (preg_match(self::YANDEX_MAPS_PATTERN, $rawInput) === 1) {
            if (preg_match(self::YANDEX_MAPS_INPUT_PATTERN, $rawInput, $matches) !== 1) {
                return null;
            }

            return new OrganizationSearchInputDto(
                rawInput: $rawInput,
                linkPart: $matches[1],
                clarification: isset($matches[2]) ? trim($matches[2]) : null,
                isYandexMapsUrl: true,
            );
        }

        if (preg_match('#^(\S+)(?:\s+(.+))?$#u', $rawInput, $matches) !== 1) {
            return null;
        }

        $linkPart = $matches[1];

        if (! $this->isValidLink($linkPart)) {
            return null;
        }

        return new OrganizationSearchInputDto(
            rawInput: $rawInput,
            linkPart: $linkPart,
            clarification: isset($matches[2]) ? trim($matches[2]) : null,
            isYandexMapsUrl: false,
        );
    }

    /**
     * Преобразует ввод пользователя в URL для yandex-parser.
     */
    public function toResolverUrl(OrganizationSearchInputDto $input): string
    {
        if ($input->isYandexMapsUrl && ($input->clarification === null || $input->clarification === '')) {
            return $input->linkPart;
        }

        return 'https://yandex.ru/maps/?text='.rawurlencode($input->mapsSearchQuery());
    }

    /**
     * Правила Laravel Validation для поля ввода организации.
     *
     * @return array<int, ValidationRule|string>
     */
    public function validationRules(): array
    {
        return [
            'required',
            'string',
            'max:2048',
            $this->validationRule(),
        ];
    }

    private function validationRule(): ValidationRule
    {
        return new class($this) implements ValidationRule
        {
            public function __construct(
                private readonly OrganizationSearchInputValidator $validator,
            ) {}

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (! is_string($value) || ! $this->validator->isValid($value)) {
                    $fail('Укажите ссылку в начале, затем при необходимости уточнение через пробел (например: www.invitro.ru Новокузнецк).');
                }
            }
        };
    }

    private function isValidLink(string $link): bool
    {
        if (preg_match('#^(?:https?://)?[^\s]+$#iu', $link) !== 1) {
            return false;
        }

        if (preg_match('#^(?:https?://)?(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}(/[^\s]*)?$#iu', $link) !== 1) {
            return false;
        }

        $normalized = $link;

        if (preg_match('#^https?://#i', $normalized) !== 1) {
            $normalized = 'https://'.$normalized;
        }

        return filter_var($normalized, FILTER_VALIDATE_URL) !== false;
    }
}

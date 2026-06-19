<?php

declare(strict_types=1);

namespace App\Services\YandexMaps\Parsing;

use App\DTO\YandexMaps\DomOrgHarvestDto;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\PageMetaDto;

/**
 * Преобразует сырые DOM-данные Яндекс.Карт в кандидатов организации.
 *
 * Два источника:
 * - строки карточек поиска ({@see mapHarvest}) — href, текст ссылки, meta и card;
 * - метаданные страницы организации ({@see mapPageMeta}) — title, header, address.
 *
 * Невалидные или неполные записи отбрасываются (возвращается null).
 */
class DomHarvestMapper
{
    public function __construct(
        private readonly YandexUrlHelper $urlHelper,
        private readonly OrganizationRecordMapper $recordMapper,
    ) {}

    /**
     * Собирает кандидата из одной DOM-строки (карточка в выдаче поиска).
     *
     * @param  string  $origin  Базовый origin страницы (например, https://yandex.ru) для canonical URL.
     */
    public function mapHarvest(DomOrgHarvestDto $harvest, string $origin): ?OrganizationCandidateDto
    {
        $orgId = $this->urlHelper->extractOrgIdFromHref($harvest->href)
            ?? $this->urlHelper->extractOrgIdFromUrl($harvest->href);

        if ($orgId === null) {
            return null;
        }

        // Отсекаем подстраницы организации (/reviews, /gallery и т.п.) — нужна только карточка org.
        if (preg_match('/\/org\/[^\/]+\/\d+(\/[^\/\?#]+)/i', $harvest->href, $matches) === 1) {
            $extraPath = $matches[1];

            if ($extraPath !== '' && $extraPath !== '/') {
                return null;
            }
        }

        $name = trim($harvest->linkText);

        if (! $this->recordMapper->isPlausibleOrgName($name)) {
            return null;
        }

        $slug = $this->extractSlugFromHref($harvest->href);
        $address = trim($harvest->metaText);

        // meta_text часто пустой или дублирует название — тогда адрес берём из card_text.
        if ($address === '' || $address === $name) {
            $address = $this->extractAddressFromCardText($harvest->cardText, $name);
        }

        return new OrganizationCandidateDto(
            orgId: $orgId,
            name: $name,
            address: $address,
            averageRating: $this->urlHelper->parseRating($harvest->ratingAriaLabel),
            reviewsCount: $this->parseReviewsCount($harvest->cardText),
            ratingsCount: $this->parseRatingsCount($harvest->cardText),
            canonicalUrl: "{$origin}/maps/org/{$slug}/{$orgId}/",
        );
    }

    /**
     * Собирает кандидата из метаданных страницы конкретной организации (прямой переход по URL).
     *
     * Используется вместе с API/DOM-кандидатами и мержится через {@see OrganizationCandidateMerger}.
     *
     * @param  string  $resolvedUrl  Финальный URL после редиректов (для slug и origin).
     * @param  string  $orgId  Идентификатор организации, уже извлечённый из URL или payload.
     */
    public function mapPageMeta(PageMetaDto $pageMeta, string $resolvedUrl, string $orgId): ?OrganizationCandidateDto
    {
        $name = $this->extractNameFromTitle($pageMeta->title);

        if (! $this->recordMapper->isPlausibleOrgName($name)) {
            return null;
        }

        $origin = $this->urlHelper->safeOrigin($resolvedUrl);
        $slug = $this->extractSlugFromHref($resolvedUrl);

        return new OrganizationCandidateDto(
            orgId: $orgId,
            name: $name,
            address: trim($pageMeta->addressText),
            averageRating: $this->parseRatingFromText($pageMeta->headerText),
            reviewsCount: $this->parseReviewsCount($pageMeta->headerText),
            ratingsCount: $this->parseRatingsCount($pageMeta->headerText),
            canonicalUrl: "{$origin}/maps/org/{$slug}/{$orgId}/",
        );
    }

    /**
     * Человекочитаемый slug из пути /maps/org/{slug}/{id}/.
     */
    private function extractSlugFromHref(string $href): string
    {
        if (preg_match('/\/org\/([^\/]+)\/\d+/i', $href, $matches) === 1) {
            return $matches[1];
        }

        return 'organization';
    }

    /**
     * Убирает суффикс «— Яндекс Карты» из document.title.
     */
    private function extractNameFromTitle(string $title): string
    {
        $name = preg_replace('/\s*—\s*Яндекс\.?\s*Карты.*/iu', '', trim($title)) ?? trim($title);

        return trim($name);
    }

    /**
     * Запасной адрес из card_text, если meta_text не дал результата.
     *
     * card_text — весь текст карточки; отдельного поля адреса в DOM нет,
     * поэтому возвращаем строку целиком, если она не пустая и не совпадает с названием.
     */
    private function extractAddressFromCardText(string $cardText, string $name): string
    {
        $trimmed = trim($cardText);

        if ($trimmed === '' || $trimmed === $name) {
            return '';
        }

        return $trimmed;
    }

    /**
     * Первая десятичная цифра в произвольном тексте (header страницы организации).
     */
    private function parseRatingFromText(string $text): ?float
    {
        $normalized = str_replace(',', '.', $text);

        if (preg_match('/(\d+(?:\.\d+)?)/', $normalized, $matches) !== 1) {
            return null;
        }

        return $this->urlHelper->parseRating($matches[1]);
    }

    /**
     * Число отзывов: «24 отзыва» или вкладка «Отзывы24» (не «Фото11» перед «Отзывы»).
     */
    private function parseReviewsCount(string $text): ?int
    {
        if (preg_match('/(?:^|[^\d,])(\d{1,3}(?:\s\d{3})*|\d+)\s+отзыв(?:ов|а|ы)?(?:[^\p{L}]|$)/u', $text, $matches) === 1) {
            $count = $this->urlHelper->parseCount($matches[1]);
        } elseif (preg_match('/отзыв(?:ов|а|ы)?\s*(\d+)/iu', $text, $matches) === 1) {
            $count = $this->urlHelper->parseCount($matches[1]);
        } else {
            $count = null;
        }

        return $count;
    }

    /**
     * Число оценок по шаблону «N оцен(ок)» — отдельно от отзывов в разметке Яндекса.
     */
    private function parseRatingsCount(string $text): ?int
    {
        if (preg_match('/(?:^|[^\d,])(\d{1,3}(?:\s\d{3})*|\d+)\s*оцен/iu', $text, $matches) !== 1) {
            return null;
        }

        return $this->urlHelper->parseCount($matches[1]);
    }
}

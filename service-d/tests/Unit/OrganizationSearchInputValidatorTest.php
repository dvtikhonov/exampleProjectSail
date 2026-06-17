<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\YandexMaps\OrganizationSearchInputValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrganizationSearchInputValidatorTest extends TestCase
{
    private OrganizationSearchInputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new OrganizationSearchInputValidator;
    }

    #[DataProvider('validInputsProvider')]
    public function test_is_valid_accepts_supported_inputs(string $input): void
    {
        $this->assertTrue($this->validator->isValid($input));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validInputsProvider(): array
    {
        return [
            'website with clarification' => ['www.invitro.ru Новокузнецк'],
            'website only' => ['www.invitro.ru'],
            'website with https' => ['https://www.invitro.ru Москва'],
            'yandex.ru https' => ['https://yandex.ru/maps/org/test/1234567890/'],
            'yandex.ru http' => ['http://yandex.ru/maps/?text=cafe'],
            'yandex.com' => ['https://yandex.com/maps/org/cafe/9876543210/'],
            'yandex.kz' => ['https://yandex.kz/maps/213/almaty/search/cafe'],
            'yandex.com.tr' => ['https://yandex.com.tr/maps/org/restoran/5555555555/'],
            'uppercase host' => ['https://YANDEX.RU/maps/org/test/1/'],
            'yandex with clarification' => ['https://yandex.ru/maps/?text=cafe Новокузнецк'],
        ];
    }

    #[DataProvider('invalidInputsProvider')]
    public function test_is_valid_rejects_invalid_inputs(string $input): void
    {
        $this->assertFalse($this->validator->isValid($input));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidInputsProvider(): array
    {
        return [
            'clarification before link' => ['Новокузнецк www.invitro.ru'],
            'empty string' => [''],
            'not a url' => ['not-a-url'],
            'plain text only' => ['Новокузнецк'],
        ];
    }

    public function test_parse_splits_link_and_clarification(): void
    {
        $parsed = $this->validator->parse('www.invitro.ru Новокузнецк');

        $this->assertNotNull($parsed);
        $this->assertSame('www.invitro.ru Новокузнецк', $parsed->rawInput);
        $this->assertSame('www.invitro.ru', $parsed->linkPart);
        $this->assertSame('Новокузнецк', $parsed->clarification);
        $this->assertFalse($parsed->isYandexMapsUrl);
    }

    public function test_to_resolver_url_builds_yandex_search_for_website_input(): void
    {
        $parsed = $this->validator->parse('www.invitro.ru Новокузнецк');

        $this->assertNotNull($parsed);
        $this->assertSame('www.invitro.ru Новокузнецк', $parsed->mapsSearchQuery());
        $this->assertSame(
            'https://yandex.ru/maps/?text=www.invitro.ru%20%D0%9D%D0%BE%D0%B2%D0%BE%D0%BA%D1%83%D0%B7%D0%BD%D0%B5%D1%86%D0%BA',
            $this->validator->toResolverUrl($parsed),
        );
    }

    public function test_maps_search_query_uses_full_link_for_website_only(): void
    {
        $parsed = $this->validator->parse('www.invitro.ru');

        $this->assertNotNull($parsed);
        $this->assertSame('www.invitro.ru', $parsed->mapsSearchQuery());
    }

    public function test_to_resolver_url_keeps_direct_yandex_org_url(): void
    {
        $url = 'https://yandex.ru/maps/org/test-cafe/1248139252/';
        $parsed = $this->validator->parse($url);

        $this->assertNotNull($parsed);
        $this->assertSame($url, $this->validator->toResolverUrl($parsed));
    }

    public function test_validation_rules_include_required_string_and_custom_rule(): void
    {
        $rules = $this->validator->validationRules();

        $this->assertSame('required', $rules[0]);
        $this->assertSame('string', $rules[1]);
        $this->assertSame('max:2048', $rules[2]);
        $this->assertCount(4, $rules);
    }
}

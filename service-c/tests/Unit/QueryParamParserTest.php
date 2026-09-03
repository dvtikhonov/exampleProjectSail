<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Http\QueryParamParser;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Разбор опциональных query-параметров.
 */
class QueryParamParserTest extends TestCase
{
    private QueryParamParser $parser;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new QueryParamParser;
    }

    /** optionalPositiveInt возвращает null для отсутствующего параметра. */
    public function test_optional_positive_int_returns_null_when_missing(): void
    {
        $request = Request::create('/test', 'GET');

        $this->assertNull($this->parser->optionalPositiveInt($request, 'restaurant_id'));
    }

    /** optionalPositiveInt возвращает null для пустой строки и неположительных значений. */
    public function test_optional_positive_int_returns_null_for_empty_or_non_positive_values(): void
    {
        $this->assertNull($this->parser->optionalPositiveInt($this->requestWithQuery(['id' => '']), 'id'));
        $this->assertNull($this->parser->optionalPositiveInt($this->requestWithQuery(['id' => '0']), 'id'));
        $this->assertNull($this->parser->optionalPositiveInt($this->requestWithQuery(['id' => '-5']), 'id'));
    }

    /** optionalPositiveInt возвращает int для положительного значения. */
    public function test_optional_positive_int_returns_value_for_positive_integer(): void
    {
        $request = $this->requestWithQuery(['restaurant_id' => '42']);

        $this->assertSame(42, $this->parser->optionalPositiveInt($request, 'restaurant_id'));
    }

    /** optionalTrimmedString возвращает null для нестрокового значения. */
    public function test_optional_trimmed_string_returns_null_for_non_string(): void
    {
        $request = $this->requestWithQuery(['name' => 123]);

        $this->assertNull($this->parser->optionalTrimmedString($request, 'name', 255));
    }

    /** optionalTrimmedString обрезает пробелы и ограничивает длину. */
    public function test_optional_trimmed_string_trims_and_limits_length(): void
    {
        $request = $this->requestWithQuery(['name' => '  abcdef  ']);

        $this->assertSame('abc', $this->parser->optionalTrimmedString($request, 'name', 3));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function requestWithQuery(array $query): Request
    {
        return Request::create('/test', 'GET', $query);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Database\LikeEscape;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LikeEscapeTest extends TestCase
{
    /** contains экранирует спецсимволы LIKE и оборачивает в %…%. */
    #[DataProvider('containsProvider')]
    public function test_contains_escapes_like_metacharacters(string $input, string $expected): void
    {
        $this->assertSame($expected, LikeEscape::contains($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function containsProvider(): array
    {
        return [
            'plain substring' => ['Alice', '%Alice%'],
            'percent' => ['%', '%\\%%'],
            'underscore' => ['_', '%\\_%'],
            'backslash' => ['\\', '%\\\\%'],
            'mixed' => ['a%b_c\\d', '%a\\%b\\_c\\\\d%'],
        ];
    }
}

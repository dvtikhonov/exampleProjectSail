<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Загрузка JSON-фикстур ответов yandex-parser для тестов.
 */
final class YandexParserFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function loadCollect(string $name): array
    {
        $path = dirname(__DIR__).'/Fixtures/yandex/collect/'.$name.'.json';

        if (! is_file($path)) {
            throw new RuntimeException("Yandex parser collect fixture not found: {$name}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read Yandex parser collect fixture: {$name}");
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public static function load(string $name): array
    {
        $path = dirname(__DIR__).'/Fixtures/yandex/'.$name.'.json';

        if (! is_file($path)) {
            throw new RuntimeException("Yandex parser fixture not found: {$name}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read Yandex parser fixture: {$name}");
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}

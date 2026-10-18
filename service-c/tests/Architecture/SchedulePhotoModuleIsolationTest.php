<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Architecture guard: SchedulePhoto Domain/ and Application/ (scanned recursively)
 * must not import Illuminate\* or App\Models\*, must not call Laravel helpers/facades,
 * and must not depend on Food/Max application types.
 * Infrastructure / Interface / Providers are adapters — not scanned.
 */
final class SchedulePhotoModuleIsolationTest extends TestCase
{
    /** @var list<string> */
    private const SCAN_DIRS = [
        'app/Modules/SchedulePhoto/Domain',
        'app/Modules/SchedulePhoto/Application',
    ];

    /** @var list<string> */
    private const FORBIDDEN_NEEDLES = [
        'Illuminate\\',
        'App\\Models\\',
        'App\\Exceptions\\Food\\',
        'App\\DTO\\Food\\',
        'App\\Enums\\Food\\',
        'App\\Contracts\\Food\\',
        'App\\Contracts\\Max\\',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_HELPER_PATTERNS = [
        '/config\s*\(\s*[\'"]/',
        '/\bevent\s*\(/',
        '/\bDB::/',
        '/\bLog::/',
        '/\bStorage::/',
        '/\bCache::/',
    ];

    #[Test]
    public function schedule_photo_domain_dirs_have_no_framework_or_food_max_leaks(): void
    {
        $root = dirname(__DIR__, 2);
        $leaks = $this->collectLeaks($root);

        $this->assertSame(
            [],
            $leaks,
            "Illuminate\\ / App\\Models\\ / Food\\Max / helper+facade leaks in SchedulePhoto Domain/Application:\n  "
            .implode("\n  ", $leaks)
            ."\nKeep Eloquent and Food/Max in Infrastructure; Domain/Application depend on module ports only.",
        );
    }

    /**
     * @return list<string>
     */
    private function collectLeaks(string $root): array
    {
        $leaks = [];

        foreach (self::SCAN_DIRS as $relativeDir) {
            $dir = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $contents = (string) file_get_contents($file->getPathname());

                foreach (self::FORBIDDEN_NEEDLES as $needle) {
                    if (str_contains($contents, $needle)) {
                        $leaks[] = $relative.' ('.$needle.')';
                    }
                }

                foreach (self::FORBIDDEN_HELPER_PATTERNS as $pattern) {
                    if (preg_match($pattern, $contents) === 1) {
                        $leaks[] = $relative.' ('.$pattern.')';
                    }
                }
            }
        }

        sort($leaks);

        return array_values(array_unique($leaks));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Architecture guard: app/Support must not import Illuminate\* or App\Models\*,
 * and must not call Laravel helpers/facades (config/request/event/DB/Log/Storage/Cache).
 */
final class SupportLayerIsolationTest extends TestCase
{
    private const SCAN_DIR = 'app/Support';

    /** @var list<string> */
    private const FORBIDDEN_NEEDLES = [
        'Illuminate\\',
        'App\\Models\\',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_HELPER_PATTERNS = [
        '/config\s*\(\s*[\'"]/',
        '/\brequest\s*\(/',
        '/\bevent\s*\(/',
        '/\bDB::/',
        '/\bLog::/',
        '/\bStorage::/',
        '/\bCache::/',
    ];

    #[Test]
    public function support_layer_has_no_illuminate_or_laravel_helper_leaks(): void
    {
        $root = dirname(__DIR__, 2);
        $leaks = $this->collectLeaks($root);

        $this->assertSame(
            [],
            $leaks,
            "Illuminate\\ / App\\Models\\ / helper+facade leaks in app/Support:\n  "
            .implode("\n  ", $leaks)
            ."\nMove adapters to Http/ or Infrastructure/Laravel; keep Support framework-free.",
        );
    }

    /**
     * @return list<string>
     */
    private function collectLeaks(string $root): array
    {
        $dir = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::SCAN_DIR);

        if (! is_dir($dir)) {
            return [];
        }

        $leaks = [];
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

        sort($leaks);

        return array_values(array_unique($leaks));
    }
}

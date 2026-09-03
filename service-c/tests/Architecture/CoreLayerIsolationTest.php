<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Architecture guard: app/Services and app/Contracts must not import Illuminate\*
 * or App\Models\* (beyond the checked-in baseline inventory).
 *
 * End-state: empty baseline — 0 leaks in core layers.
 */
final class CoreLayerIsolationTest extends TestCase
{
    private const SCAN_DIRS = [
        'app/Services',
        'app/Contracts',
    ];

    private const BASELINE_RELATIVE = 'tests/Architecture/baselines/core-illuminate-models-leaks.txt';

    /** @var list<string> */
    private const FORBIDDEN_NEEDLES = [
        'Illuminate\\',
        'App\\Models\\',
    ];

    #[Test]
    public function services_and_contracts_do_not_gain_illuminate_or_eloquent_leaks(): void
    {
        $root = dirname(__DIR__, 2);
        $baselinePath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::BASELINE_RELATIVE);

        $this->assertFileExists(
            $baselinePath,
            'Baseline inventory missing. Create via: bash scripts/architecture-leak-inventory.sh --write-baseline'
        );

        $allowed = $this->readBaseline($baselinePath);
        $current = $this->collectLeaks($root);

        $newLeaks = array_values(array_diff($current, $allowed));
        $stale = array_values(array_diff($allowed, $current));

        $messages = [];

        if ($newLeaks !== []) {
            $messages[] = "New Illuminate\\ / App\\Models\\ leaks in Services/Contracts (not in baseline):\n  "
                .implode("\n  ", $newLeaks)
                ."\nFix via ports/adapters; do not extend the baseline.";
        }

        if ($stale !== []) {
            $messages[] = "Baseline is stale (paths no longer leak — remove them):\n  "
                .implode("\n  ", $stale)
                ."\nRun: bash scripts/architecture-leak-inventory.sh --write-baseline";
        }

        $this->assertSame(
            [],
            $messages,
            $messages === [] ? '' : implode("\n\n", $messages)
        );
    }

    /**
     * @return list<string>
     */
    private function readBaseline(string $baselinePath): array
    {
        $lines = file($baselinePath, FILE_IGNORE_NEW_LINES);
        $this->assertNotFalse($lines, "Unable to read baseline: {$baselinePath}");

        $paths = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $paths[] = str_replace('\\', '/', $trimmed);
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function collectLeaks(string $root): array
    {
        $leaks = [];

        foreach (self::SCAN_DIRS as $relativeDir) {
            $absoluteDir = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
            if (! is_dir($absoluteDir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absoluteDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                if ($contents === false) {
                    continue;
                }

                if (! $this->containsForbiddenNeedle($contents)) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $leaks[] = $relative;
            }
        }

        $leaks = array_values(array_unique($leaks));
        sort($leaks);

        return $leaks;
    }

    private function containsForbiddenNeedle(string $contents): bool
    {
        foreach (self::FORBIDDEN_NEEDLES as $needle) {
            if (str_contains($contents, $needle)) {
                return true;
            }
        }

        return false;
    }
}

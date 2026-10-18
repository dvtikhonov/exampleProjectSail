<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Domain\Support\SchedulePhotoOriginAllowList;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit: hard-reject metadata / link-local даже при попадании в env allow-list.
 */
final class SchedulePhotoOriginAllowListTest extends TestCase
{
    #[DataProvider('blockedEvenIfAllowListedProvider')]
    public function test_rejects_metadata_and_link_local_even_if_allow_listed(string $origin): void
    {
        $allowList = new SchedulePhotoOriginAllowList([$origin]);

        $this->assertNull($allowList->normalize($origin));
        $this->assertNull($allowList->normalize(rtrim($origin, '/').'/'));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function blockedEvenIfAllowListedProvider(): array
    {
        return [
            ['http://169.254.169.254'],
            ['http://169.254.0.1:80'],
            ['http://169.254.255.255'],
            ['http://[fe80::1]'],
            ['http://[fe80::abcd:1]:8080'],
            ['http://metadata.google.internal'],
            ['http://metadata'],
        ];
    }

    #[DataProvider('localOriginsStillAllowedProvider')]
    public function test_allows_localhost_and_docker_internal_when_allow_listed(string $origin): void
    {
        $allowList = new SchedulePhotoOriginAllowList([$origin]);

        $this->assertSame(rtrim($origin, '/'), $allowList->normalize($origin));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function localOriginsStillAllowedProvider(): array
    {
        return [
            ['http://localhost:8083'],
            ['http://127.0.0.1:8083'],
            ['http://host.docker.internal:8083'],
        ];
    }
}

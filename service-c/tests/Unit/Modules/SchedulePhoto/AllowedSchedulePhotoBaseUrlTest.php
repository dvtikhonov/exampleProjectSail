<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Http\Rules\AllowedSchedulePhotoBaseUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit: allow-list origin для SchedulePhoto base_url (SSRF).
 */
final class AllowedSchedulePhotoBaseUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'schedule_photo.allowed_base_urls' => [
                'http://host.docker.internal:8083',
                'http://localhost:8083',
                'https://max-dev.94-228-117-27.sslip.io',
            ],
        ]);
    }

    #[DataProvider('allowedOriginsProvider')]
    public function test_passes_for_allowed_origins(string $value): void
    {
        $failed = false;
        (new AllowedSchedulePhotoBaseUrl)->validate(
            'base_url',
            $value,
            static function () use (&$failed): void {
                $failed = true;
            },
        );

        $this->assertFalse($failed, 'Expected origin to be allowed: '.$value);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function allowedOriginsProvider(): array
    {
        return [
            ['http://host.docker.internal:8083'],
            ['http://host.docker.internal:8083/'],
            ['http://localhost:8083'],
            ['https://max-dev.94-228-117-27.sslip.io'],
            ['https://max-dev.94-228-117-27.sslip.io/'],
        ];
    }

    #[DataProvider('rejectedOriginsProvider')]
    public function test_rejects_ssrf_and_unknown_origins(string $value): void
    {
        $message = null;
        (new AllowedSchedulePhotoBaseUrl)->validate(
            'base_url',
            $value,
            static function (string $failMessage) use (&$message): void {
                $message = $failMessage;
            },
        );

        $this->assertNotNull($message);
        $this->assertStringContainsString('разрешённых origin', (string) $message);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function rejectedOriginsProvider(): array
    {
        return [
            ['http://169.254.169.254'],
            ['http://169.254.169.254/latest/meta-data/'],
            ['http://127.0.0.1:8083'],
            ['http://evil.example'],
            ['https://max-dev.94-228-117-27.sslip.io/evil'],
            ['https://user:pass@max-dev.94-228-117-27.sslip.io'],
            ['https://max-dev.94-228-117-27.sslip.io?x=1'],
            ['ftp://host.docker.internal:8083'],
            ['not-a-url'],
        ];
    }
}

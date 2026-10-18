<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\UseCase\Admin\SchedulePhotoUiOriginConfigBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchedulePhotoUiOriginConfigBuilderTest extends TestCase
{
    #[Test]
    public function build_filters_localhost_and_labels_docker_and_vps(): void
    {
        $builder = new SchedulePhotoUiOriginConfigBuilder(
            localBaseUrl: 'http://host.docker.internal:8083',
            defaultBaseUrl: 'http://host.docker.internal:8083',
            allowedBaseUrls: [
                'http://host.docker.internal:8083',
                'http://localhost:8083',
                'http://127.0.0.1:8083',
                'https://max-dev.94-228-117-27.sslip.io',
            ],
        );

        $config = $builder->build();
        $payload = $config->toArray();

        $this->assertSame('http://host.docker.internal:8083', $payload['default_base_url']);
        $this->assertSame(
            [
                [
                    'value' => 'http://host.docker.internal:8083',
                    'label' => 'Локальный (host.docker.internal:8083)',
                ],
                [
                    'value' => 'https://max-dev.94-228-117-27.sslip.io',
                    'label' => 'VPS (max-dev.94-228-117-27.sslip.io)',
                ],
            ],
            $payload['allowed_base_urls'],
        );
    }

    #[Test]
    public function build_falls_back_to_local_when_default_not_in_ui_list(): void
    {
        $builder = new SchedulePhotoUiOriginConfigBuilder(
            localBaseUrl: 'http://host.docker.internal:8083',
            defaultBaseUrl: 'http://localhost:8083',
            allowedBaseUrls: [
                'http://host.docker.internal:8083',
                'https://example.vps.example',
            ],
        );

        $config = $builder->build();

        $this->assertSame('http://host.docker.internal:8083', $config->defaultBaseUrl);
    }

    #[Test]
    public function build_falls_back_to_first_ui_option_when_default_and_local_missing(): void
    {
        $builder = new SchedulePhotoUiOriginConfigBuilder(
            localBaseUrl: 'http://localhost:8083',
            defaultBaseUrl: 'http://127.0.0.1:8083',
            allowedBaseUrls: [
                'https://first.vps.example',
                'https://second.vps.example',
            ],
        );

        $config = $builder->build();

        $this->assertSame('https://first.vps.example', $config->defaultBaseUrl);
        $this->assertSame('VPS (first.vps.example)', $config->allowedBaseUrls[0]->label);
    }

    #[Test]
    public function build_skips_empty_and_duplicate_origins(): void
    {
        $builder = new SchedulePhotoUiOriginConfigBuilder(
            localBaseUrl: '',
            defaultBaseUrl: 'https://vps.example/',
            allowedBaseUrls: [
                '',
                '  ',
                'https://vps.example/',
                'https://vps.example',
            ],
        );

        $payload = $builder->build()->toArray();

        $this->assertSame('https://vps.example', $payload['default_base_url']);
        $this->assertCount(1, $payload['allowed_base_urls']);
        $this->assertSame('https://vps.example', $payload['allowed_base_urls'][0]['value']);
    }
}

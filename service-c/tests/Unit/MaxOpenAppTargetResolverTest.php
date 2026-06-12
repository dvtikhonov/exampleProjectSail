<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaxOpenAppTargetResolver;
use Tests\TestCase;

class MaxOpenAppTargetResolverTest extends TestCase
{
    public function test_resolve_web_app_prefers_explicit_mini_app_url(): void
    {
        config([
            'max.ui_stand.mini_app_url' => 'https://explicit.test/max-app',
            'max.webhook.url' => 'https://tunnel.test/api/webhooks/max',
        ]);

        $resolver = $this->app->make(MaxOpenAppTargetResolver::class);

        $this->assertSame('https://explicit.test/max-app', $resolver->resolveWebApp());
    }

    public function test_resolve_web_app_derives_from_webhook_url(): void
    {
        config([
            'max.ui_stand.mini_app_url' => '',
            'max.webhook.url' => 'https://exampleprojectsail.fxtun.dev/api/webhooks/max',
        ]);

        $resolver = $this->app->make(MaxOpenAppTargetResolver::class);

        $this->assertSame(
            'https://exampleprojectsail.fxtun.dev/max-app',
            $resolver->resolveWebApp(),
        );
    }

    public function test_resolve_contact_id_returns_null_when_not_configured(): void
    {
        config(['max.bot_user_id' => 0]);

        $resolver = $this->app->make(MaxOpenAppTargetResolver::class);

        $this->assertNull($resolver->resolveContactId());
    }
}

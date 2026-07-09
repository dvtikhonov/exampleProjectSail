<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaxUiStandRecipientRegistry;
use App\Support\MaxUiStandRecipientResolver;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MaxUiStandRecipientResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_resolves_chat_and_user_ids_from_ui_stand_config(): void
    {
        config([
            'max.ui_stand.recipient_chat_ids' => [111, 222],
            'max.ui_stand.recipient_user_ids' => [333],
        ]);

        $resolver = $this->app->make(MaxUiStandRecipientResolver::class);

        $this->assertSame([111, 222], $resolver->configuredChatIds());
        $this->assertSame([333], $resolver->configuredUserIds());
        $this->assertSame([111, 222], $resolver->chatIds());
        $this->assertSame([333], $resolver->userIds());
    }

    public function test_merges_configured_and_registered_recipients(): void
    {
        config([
            'max.ui_stand.recipient_chat_ids' => [111],
            'max.ui_stand.recipient_user_ids' => [],
        ]);

        $registry = $this->app->make(MaxUiStandRecipientRegistry::class);
        $registry->rememberChatId(-100500);
        $registry->rememberUserId(777);

        $resolver = $this->app->make(MaxUiStandRecipientResolver::class);

        $this->assertSame([111, -100500], $resolver->chatIds());
        $this->assertSame([777], $resolver->userIds());
    }
}

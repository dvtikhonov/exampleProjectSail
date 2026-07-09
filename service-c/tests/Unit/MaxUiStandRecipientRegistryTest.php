<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaxUiStandRecipientRegistry;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MaxUiStandRecipientRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_remembers_chat_and_user_ids(): void
    {
        $registry = $this->app->make(MaxUiStandRecipientRegistry::class);

        $registry->rememberChatId(-100500);
        $registry->rememberUserId(777);

        $this->assertSame([-100500], $registry->chatIds());
        $this->assertSame([777], $registry->userIds());
    }

    public function test_moves_recent_recipient_to_front_without_duplicates(): void
    {
        $registry = $this->app->make(MaxUiStandRecipientRegistry::class);

        $registry->rememberChatId(100);
        $registry->rememberChatId(200);
        $registry->rememberChatId(100);

        $this->assertSame([100, 200], $registry->chatIds());
    }
}

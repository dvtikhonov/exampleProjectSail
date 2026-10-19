<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Desktop Telegram Mini App: blade bootstrap должен выходить из fullscreen
 * (иначе SeparatePanel на Windows прижимается вправо без drag-handle).
 */
class TelegramBridgeWindowedDesktopTest extends TestCase
{
    #[Test]
    public function test_tg_app_blade_exits_fullscreen_on_non_mobile(): void
    {
        $blade = file_get_contents(resource_path('views/tg-app.blade.php'));

        $this->assertNotFalse($blade);
        $this->assertStringContainsString('exitFullscreen', $blade);
        $this->assertStringContainsString('fullscreenChanged', $blade);
        $this->assertStringContainsString("platform === 'ios'", $blade);
        $this->assertStringContainsString("platform === 'android'", $blade);
        $this->assertStringContainsString('tg.expand', $blade);
        $this->assertStringContainsString('isMobile', $blade);
    }

    #[Test]
    public function test_telegram_bridge_exports_ensure_windowed_desktop(): void
    {
        $bridge = file_get_contents(resource_path('js/max-app/bridge/telegramBridge.js'));

        $this->assertNotFalse($bridge);
        $this->assertStringContainsString('export function ensureWindowedDesktop', $bridge);
        $this->assertStringContainsString('exitFullscreen', $bridge);
        $this->assertStringContainsString('isMobileTelegramPlatform', $bridge);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Chat\FoodOrderChatMaxMessageBuilderInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Infrastructure\Laravel\TelegramWebAppButtonFactory;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TelegramWebAppButtonFactoryTest extends TestCase
{
    /** С bot_username — url-кнопка t.me с startapp. */
    public function test_build_order_chat_button_rows_uses_startapp_url_when_bot_username_set(): void
    {
        Config::set('telegram.mini_app_url', 'https://94-228-117-27.sslip.io/tg-app');
        Config::set('telegram.bot_username', 'obedov_bot');

        $factory = $this->app->make(TelegramWebAppButtonFactory::class);
        $rows = $factory->buildOrderChatButtonRows(42, 'order_42_chat');

        $this->assertSame('Открыть заказ №42', $rows[0][0]->text);
        $this->assertSame('https://t.me/obedov_bot?startapp=order_42_chat', $rows[0][0]->url);
        $this->assertNull($rows[0][0]->webAppUrl);
    }

    /** Без bot_username — web_app на TELEGRAM_MINI_APP_URL с order_id/view. */
    public function test_build_order_chat_button_rows_uses_web_app_when_bot_username_empty(): void
    {
        Config::set('telegram.mini_app_url', 'https://94-228-117-27.sslip.io/tg-app');
        Config::set('telegram.bot_username', '');

        $factory = $this->app->make(TelegramWebAppButtonFactory::class);
        $rows = $factory->buildOrderChatButtonRows(42, 'order_42_chat');

        $this->assertNull($rows[0][0]->url);
        $this->assertSame(
            'https://94-228-117-27.sslip.io/tg-app?order_id=42&view=chat',
            $rows[0][0]->webAppUrl,
        );
    }

    /** Без mini_app_url — пустые ряды. */
    public function test_build_order_chat_button_rows_empty_without_mini_app_url(): void
    {
        Config::set('telegram.mini_app_url', '');
        Config::set('telegram.bot_username', 'obedov_bot');

        $factory = new TelegramWebAppButtonFactory(
            $this->app->make(ApplicationConfigInterface::class),
            $this->app->make(FoodOrderChatMaxMessageBuilderInterface::class),
        );

        $this->assertSame([], $factory->buildOrderChatButtonRows(1, 'order_1_chat'));
    }
}

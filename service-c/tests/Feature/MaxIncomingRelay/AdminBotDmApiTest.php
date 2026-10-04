<?php

declare(strict_types=1);

namespace Tests\Feature\MaxIncomingRelay;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;
use App\Modules\MaxIncomingRelay\Models\MaxBotDirectMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature-тесты Admin Bot DM API (users / list / send).
 */
class AdminBotDmApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Bot DM требует аутентификацию. */
    public function test_bot_dm_requires_authentication(): void
    {
        $this->getJson('/api/food/admin/bot-dm/users')
            ->assertUnauthorized();
    }

    /** Без роли max_manager возвращается 403. */
    public function test_bot_dm_forbidden_without_max_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/bot-dm/users', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');
    }

    /** max_manager может получить список пользователей. */
    public function test_max_manager_can_list_users(): void
    {
        $manager = $this->maxManagerAuth();
        MaxUser::query()->create([
            'max_user_id' => 66_001,
            'first_name' => 'Alice',
            'username' => 'alice_bot_dm',
        ]);

        $this->getJson('/api/food/admin/bot-dm/users', $manager['headers'])
            ->assertOk()
            ->assertJsonFragment([
                'max_user_id' => 66_001,
                'first_name' => 'Alice',
                'username' => 'alice_bot_dm',
            ]);
    }

    /** Неизвестный пользователь → 404 на list и send. */
    public function test_unknown_user_returns_not_found(): void
    {
        $manager = $this->maxManagerAuth();

        $this->getJson('/api/food/admin/bot-dm/999999/messages', $manager['headers'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Пользователь не найден.');

        $this->postJson('/api/food/admin/bot-dm/999999/messages', [
            'body' => 'Привет',
        ], $manager['headers'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Пользователь не найден.');
    }

    /** Пустой body после trim отклоняется валидацией. */
    public function test_send_rejects_blank_body(): void
    {
        $manager = $this->maxManagerAuth();
        MaxUser::query()->create([
            'max_user_id' => 66_010,
            'first_name' => 'Customer',
        ]);

        $this->postJson('/api/food/admin/bot-dm/66010/messages', [
            'body' => '   ',
        ], $manager['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    /** body длиннее 2000 символов отклоняется валидацией. */
    public function test_send_rejects_body_longer_than_2000(): void
    {
        $manager = $this->maxManagerAuth();
        MaxUser::query()->create([
            'max_user_id' => 66_011,
            'first_name' => 'Customer',
        ]);

        $this->postJson('/api/food/admin/bot-dm/66011/messages', [
            'body' => str_repeat('a', 2001),
        ], $manager['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    /** Успешная отправка пишет в БД и возвращает сообщение. */
    public function test_max_manager_can_send_and_list_messages(): void
    {
        $manager = $this->maxManagerAuth(10_701);
        MaxUser::query()->create([
            'max_user_id' => 66_020,
            'first_name' => 'Customer',
            'last_name' => 'Dm',
            'username' => 'customer_dm',
        ]);

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(static function (MaxMessageDto $message): bool {
                return $message->userId === 66_020
                    && $message->text === 'Ответ админа'
                    && $message->chatId === null;
            }));
        $this->app->instance(MaxMessengerClientInterface::class, $client);

        $this->postJson('/api/food/admin/bot-dm/66020/messages', [
            'body' => 'Ответ админа',
        ], $manager['headers'])
            ->assertCreated()
            ->assertJsonPath('message.max_user_id', 66_020)
            ->assertJsonPath('message.sender_max_user_id', 10_701)
            ->assertJsonPath('message.author_type', BotDmAuthorType::Admin->value)
            ->assertJsonPath('message.body', 'Ответ админа');

        $this->assertDatabaseHas('max_bot_direct_messages', [
            'max_user_id' => 66_020,
            'sender_max_user_id' => 10_701,
            'author_type' => BotDmAuthorType::Admin->value,
            'body' => 'Ответ админа',
        ]);

        MaxBotDirectMessage::query()->create([
            'max_user_id' => 66_020,
            'sender_max_user_id' => 66_020,
            'author_type' => BotDmAuthorType::Customer,
            'body' => 'Вопрос клиента',
        ]);

        $this->getJson('/api/food/admin/bot-dm/66020/messages', $manager['headers'])
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.author_type', BotDmAuthorType::Admin->value)
            ->assertJsonPath('messages.1.author_type', BotDmAuthorType::Customer->value);

        $firstId = (int) MaxBotDirectMessage::query()
            ->where('max_user_id', 66_020)
            ->orderBy('id')
            ->value('id');

        $this->getJson('/api/food/admin/bot-dm/66020/messages?after_id='.$firstId, $manager['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.author_type', BotDmAuthorType::Customer->value)
            ->assertJsonPath('messages.0.body', 'Вопрос клиента');
    }

    /** Ошибка MAX API → 502 и без записи в БД. */
    public function test_send_does_not_persist_when_max_api_fails(): void
    {
        $manager = $this->maxManagerAuth();
        MaxUser::query()->create([
            'max_user_id' => 66_030,
            'first_name' => 'Customer',
        ]);

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->willThrowException(new MaxMessengerRequestException('upstream failed'));
        $this->app->instance(MaxMessengerClientInterface::class, $client);

        $this->postJson('/api/food/admin/bot-dm/66030/messages', [
            'body' => 'Не уйдёт',
        ], $manager['headers'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Не удалось отправить сообщение в MAX.');

        $this->assertDatabaseCount('max_bot_direct_messages', 0);
    }

    /** POST messages защищён throttle:30,1. */
    public function test_store_message_route_has_throttle_middleware(): void
    {
        $route = collect(Route::getRoutes())->first(
            static function ($route): bool {
                return $route->uri() === 'api/food/admin/bot-dm/{maxUserId}/messages'
                    && in_array('POST', $route->methods(), true);
            },
        );

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('throttle:30,1', $middleware);
        $this->assertContains('max.miniapp.auth', $middleware);
        $this->assertContains('food.order.admin:max_manager', $middleware);
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerAuth(int $maxUserId = 10_700): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MaxManager',
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }
}

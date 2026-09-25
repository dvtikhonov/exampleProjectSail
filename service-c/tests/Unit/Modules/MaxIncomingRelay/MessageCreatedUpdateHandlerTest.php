<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MaxIncomingRelay;

use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
use App\Modules\MaxIncomingRelay\Contracts\IncomingMessageRelayServiceInterface;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;
use App\Modules\MaxIncomingRelay\Handlers\MessageCreatedUpdateHandler;
use Tests\Support\MaxIncomingRelayPayloadFactory;
use Tests\TestCase;

/**
 * Unit: MessageCreatedUpdateHandler + маршрутизация message_created.
 */
final class MessageCreatedUpdateHandlerTest extends TestCase
{
    /** Handler передаёт DTO в relay для эталонного payload. */
    public function test_handler_relays_parsed_dto(): void
    {
        $relay = $this->createMock(IncomingMessageRelayServiceInterface::class);
        $relay->expects($this->once())
            ->method('relay')
            ->with($this->callback(static function (IncomingBotMessageDto $dto): bool {
                return $dto->userId === MaxIncomingRelayPayloadFactory::DEFAULT_USER_ID
                    && $dto->firstName === MaxIncomingRelayPayloadFactory::DEFAULT_FIRST_NAME
                    && $dto->lastName === MaxIncomingRelayPayloadFactory::DEFAULT_LAST_NAME
                    && $dto->text === MaxIncomingRelayPayloadFactory::DEFAULT_TEXT
                    && $dto->timestampMs === MaxIncomingRelayPayloadFactory::DEFAULT_TIMESTAMP_MS
                    && $dto->chatId === MaxIncomingRelayPayloadFactory::DEFAULT_CHAT_ID;
            }));

        $handler = new MessageCreatedUpdateHandler($relay);
        $handler->handle(MaxIncomingRelayPayloadFactory::textDialog());
    }

    /** Невалидный payload — relay не вызывается. */
    public function test_handler_skips_when_dto_null(): void
    {
        $relay = $this->createMock(IncomingMessageRelayServiceInterface::class);
        $relay->expects($this->never())->method('relay');

        $handler = new MessageCreatedUpdateHandler($relay);
        $handler->handle(MaxIncomingRelayPayloadFactory::textDialog(['is_bot' => true]));
    }

    /** Роутер вызывает handler модуля для update_type message_created. */
    public function test_router_invokes_module_handler_for_message_created(): void
    {
        $relay = $this->createMock(IncomingMessageRelayServiceInterface::class);
        $relay->expects($this->once())->method('relay');

        $this->app->instance(IncomingMessageRelayServiceInterface::class, $relay);

        $this->app->make(MaxWebhookUpdateRouterInterface::class)
            ->handle(MaxIncomingRelayPayloadFactory::textDialog());
    }
}

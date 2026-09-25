<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Contracts;

use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;

/**
 * Пересылка входящего текстового сообщения боту в Home_chat и max_log.
 */
interface IncomingMessageRelayServiceInterface
{
    /**
     * Собирает уведомление, пишет в max_log и рассылает в MAX_UI_STAND_CHAT_IDS.
     */
    public function relay(IncomingBotMessageDto $message): void;
}

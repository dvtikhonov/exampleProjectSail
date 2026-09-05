<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Max\MaxAdminBotTestSenderInterface;
use App\DTO\Max\MaxAdminBotTestSendResultDto;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * DEV/админ: тестовые сообщения MAX-бота (маршруты под /dishes/test-bot* сохранены для BC).
 */
class AdminMaxBotTestController extends Controller
{
    public function __construct(
        private readonly MaxAdminBotTestSenderInterface $maxAdminBotTestSender,
    ) {}

    /**
     * Отправка тестового сообщения «Тест БОТ» получателям уведомлений о заказах.
     */
    public function sendTestBot(): JsonResponse
    {
        return $this->respondTestBotSend(
            fn () => $this->maxAdminBotTestSender->sendTestMessage(),
        );
    }

    /**
     * Отправка тестового сообщения «тест бот 2» во все чаты из MAX_UI_STAND_CHAT_IDS.
     */
    public function sendTestBot2(): JsonResponse
    {
        return $this->respondTestBotSend(
            fn () => $this->maxAdminBotTestSender->sendUiStandTestMessage(),
        );
    }

    /**
     * @param  callable(): MaxAdminBotTestSendResultDto  $action
     */
    private function respondTestBotSend(callable $action): JsonResponse
    {
        $result = $action();

        return response()->json([
            'message' => 'Тестовое сообщение отправлено.',
            'sent_count' => $result->sentCount,
            'bot_username' => (string) config('max.bot_username', ''),
        ]);
    }
}

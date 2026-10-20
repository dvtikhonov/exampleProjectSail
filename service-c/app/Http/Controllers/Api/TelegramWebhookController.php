<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\MaxIncomingRelay\Handlers\TelegramMessageUpdateHandler;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Приём webhook-обновлений от Telegram Bot API.
 *
 * @see https://core.telegram.org/bots/api#update
 */
class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramMessageUpdateHandler $messageHandler,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Принимает Update и передаёт payload в обработчик входящих message.
     */
    public function __invoke(Request $request): Response
    {
        /** @var mixed $raw */
        $raw = $request->json()->all();

        if (! is_array($raw)) {
            $this->logger->info('Telegram webhook payload ignored (not an object).');

            return response('', Response::HTTP_OK);
        }

        /** @var array<string, mixed> $payload */
        $payload = $raw;

        $this->logger->info('Telegram webhook request received.', [
            'update_id' => is_int($payload['update_id'] ?? null) ? $payload['update_id'] : null,
            'has_message' => isset($payload['message']),
        ]);

        try {
            $this->messageHandler->handle($payload);
        } catch (Throwable $exception) {
            $this->logger->error('Telegram webhook handling failed', [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
                'update_id' => is_int($payload['update_id'] ?? null) ? $payload['update_id'] : null,
            ]);
            report($exception);

            return response('', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response('', Response::HTTP_OK);
    }
}

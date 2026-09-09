<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
use App\DTO\Max\MaxWebhookUpdateDto;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Приём webhook-обновлений от MAX platform API.
 */
class MaxWebhookController extends Controller
{
    public function __construct(
        private readonly MaxWebhookUpdateRouterInterface $router,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Принимает webhook и передаёт payload в маршрутизатор обновлений.
     */
    public function __invoke(Request $request): Response
    {
        /** @var mixed $raw */
        $raw = $request->json()->all();
        $dto = MaxWebhookUpdateDto::tryFrom($raw);

        if ($dto === null) {
            $this->logger->info('MAX webhook payload ignored (invalid or missing update_type).');

            return response('', Response::HTTP_OK);
        }

        $this->logger->info('MAX webhook request received.', [
            'update_type' => $dto->updateType,
        ]);

        try {
            $this->router->handle($dto->payload);
        } catch (Throwable $exception) {
            $this->logger->error('MAX webhook handling failed', [
                'error' => $exception->getMessage(),
            ]);
        }

        return response('', Response::HTTP_OK);
    }
}

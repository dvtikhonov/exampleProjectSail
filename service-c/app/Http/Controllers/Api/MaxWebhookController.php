<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Приём webhook-обновлений от MAX platform API.
 */
class MaxWebhookController extends Controller
{
    public function __construct(
        private readonly MaxWebhookUpdateRouterInterface $router,
    ) {}

    /**
     * Принимает webhook и передаёт payload в маршрутизатор обновлений.
     */
    public function __invoke(Request $request): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $updateType = (string) ($payload['update_type'] ?? 'unknown');

        Log::info('MAX webhook request received.', [
            'update_type' => $updateType,
        ]);

        try {
            $this->router->handle($payload);
        } catch (Throwable $exception) {
            Log::channel('messMax')->error('MAX webhook handling failed', [
                'error' => $exception->getMessage(),
            ]);
        }

        return response('', Response::HTTP_OK);
    }
}

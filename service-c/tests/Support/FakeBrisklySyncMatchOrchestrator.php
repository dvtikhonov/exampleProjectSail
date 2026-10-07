<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Food\BrisklySync\BrisklySyncMatchOrchestratorInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncSessionServiceInterface;
use App\DTO\Food\BrisklySync\BrisklySyncLlmCallContextDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\Exceptions\Food\FoodDomainException;
use Illuminate\Contracts\Container\Container;

/**
 * Feature-фейк: start() сразу completeQueuedMatch (синхронный «мгновенный LLM»).
 * orchestratorDown → 503 (Failed без complete/expire); orchestratorDeferComplete → только handshake.
 */
final class FakeBrisklySyncMatchOrchestrator implements BrisklySyncMatchOrchestratorInterface
{
    public function __construct(
        private readonly object $test,
        private readonly Container $app,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function start(
        ComboCatalogPromptDto $prompt,
        array $sourceLines,
        array $brisklySnapshot,
        string $sessionId,
        string $matchGeneration,
        ?BrisklySyncLlmCallContextDto $logContext = null,
    ): void {
        if (($this->test->orchestratorDown ?? false) === true) {
            throw new FoodDomainException('Orchestrator Briskly sync недоступен.', 503);
        }

        if (($this->test->orchestratorDeferComplete ?? false) === true) {
            return;
        }

        /** @var list<\App\DTO\Food\BrisklySync\MatchLineResultDto> $lines */
        $lines = $this->test->fakeMatchLines ?? [];

        $this->app->make(BrisklySyncSessionServiceInterface::class)->completeQueuedMatch(
            $sessionId,
            $matchGeneration,
            $lines,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function abort(string $sessionId, string $matchGeneration): void
    {
        if (! property_exists($this->test, 'abortedMatchRuns')) {
            return;
        }

        $this->test->abortedMatchRuns[] = [
            'session_id' => $sessionId,
            'match_generation' => $matchGeneration,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\JobDispatcherInterface;
use App\Infrastructure\Laravel\LaravelBrisklySyncMatchQueue;
use App\Jobs\Food\ExpireBrisklySyncMatchJob;
use App\Jobs\Food\RunBrisklySyncMatchJob;
use Tests\TestCase;

/**
 * Handshake vs LLM timeouts: config defaults, uniqueFor, queue retry_after.
 */
final class BrisklySyncMatchTimeoutsConfigTest extends TestCase
{
    public function test_config_splits_handshake_llm_and_match_job_timeouts(): void
    {
        $handshake = (int) config('briskly_sync.orchestrator_handshake_timeout_seconds');
        $llm = (int) config('briskly_sync.llm_timeout_seconds');
        $matchJob = (int) config('briskly_sync.match_job_timeout_seconds');

        $this->assertSame(60, $handshake);
        $this->assertSame(900, $llm);
        $this->assertSame($handshake + 30, $matchJob);
        $this->assertSame(120, (int) config('briskly_sync.orchestrator_timeout_seconds'));
        $this->assertLessThan($llm, $matchJob);
    }

    public function test_start_job_unique_for_is_timeout_plus_sixty(): void
    {
        $timeout = (int) config('briskly_sync.match_job_timeout_seconds', 90);
        $job = new RunBrisklySyncMatchJob('sess-timeouts', $timeout);

        $this->assertSame($timeout, $job->timeout);
        $this->assertSame($timeout + 60, $job->uniqueFor);
    }

    public function test_expire_job_unique_for_and_delay_follow_llm_timeout(): void
    {
        $llm = (int) config('briskly_sync.llm_timeout_seconds', 900);
        $collector = new class implements JobDispatcherInterface
        {
            public ?object $job = null;

            public function dispatch(object $job): void
            {
                $this->job = $job;
            }
        };
        $queue = new LaravelBrisklySyncMatchQueue(
            $collector,
            startTimeoutSeconds: (int) config('briskly_sync.match_job_timeout_seconds', 90),
            llmTimeoutSeconds: $llm,
        );

        $queue->dispatchExpire('sess-timeouts', '00000000-0000-4000-8000-000000000001');

        $this->assertInstanceOf(ExpireBrisklySyncMatchJob::class, $collector->job);
        $this->assertSame($llm, $collector->job->delay);
        $this->assertSame($llm + 60, $collector->job->uniqueFor);
    }

    public function test_queue_retry_after_covers_start_job_without_raising_for_llm(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after');
        $matchJob = (int) config('briskly_sync.match_job_timeout_seconds');
        $llm = (int) config('briskly_sync.llm_timeout_seconds');

        // start-job ≪ retry_after → без ложного re-release; wait LLM не в PHP-job,
        // поэтому retry_after не поднимаем под llm_timeout (остаётся 960).
        $this->assertSame(960, $retryAfter);
        $this->assertGreaterThan($matchJob, $retryAfter);
        $this->assertSame(900, $llm);
        $this->assertSame(960, (int) config('queue.connections.redis.retry_after'));
    }
}

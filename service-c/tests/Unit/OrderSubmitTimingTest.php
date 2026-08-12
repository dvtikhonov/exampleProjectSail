<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Profiling\OrderSubmitTiming;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderSubmitTimingTest extends TestCase
{
    #[Test]
    public function it_formats_server_timing_header(): void
    {
        $header = OrderSubmitTiming::toServerTimingHeader([
            't_tx_ms' => 42.56,
            't_notify_ms' => 1.2,
            't_submit_ms' => 50.01,
        ]);

        $this->assertSame('t_tx;dur=42.6, t_notify;dur=1.2, t_submit;dur=50.0', $header);
    }
}

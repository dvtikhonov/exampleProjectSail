<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Log\Events\MessageLogged;
use PHPUnit\Framework\Assert;

final class MessMaxLogTestHelper
{
    /**
     * @param  list<MessageLogged>  $captured
     */
    public static function assertSingleMessage(array $captured, string $message): MessageLogged
    {
        $matches = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === $message,
        ));

        Assert::assertCount(
            1,
            $matches,
            'Expected one ['.$message.'] log entry, got: '.json_encode(
                array_map(static fn (MessageLogged $event): string => $event->message, $captured),
                JSON_UNESCAPED_UNICODE,
            ),
        );

        return $matches[0];
    }
}

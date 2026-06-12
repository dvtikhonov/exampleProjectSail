<?php

namespace Tests\Unit;

use App\DTO\Max\MaxCallbackUpdateDto;
use InvalidArgumentException;
use Tests\TestCase;

class MaxCallbackUpdateDtoTest extends TestCase
{
    public function test_callback_update_requires_exactly_one_recipient(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MaxCallbackUpdateDto(
            callbackId: 'cb-1',
            payload: 'yes',
        );
    }
}

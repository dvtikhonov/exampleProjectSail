<?php

namespace Tests\Unit;

use InvalidArgumentException;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Tests\TestCase;

class MaxInlineKeyboardMessageDtoTest extends TestCase
{
    public function test_inline_keyboard_requires_exactly_one_recipient(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MaxInlineKeyboardMessageDto(
            text: 'test',
            buttonRows: [[new MaxInlineKeyboardButtonDto(text: 'да', payload: 'yes')]],
        );
    }

    public function test_inline_keyboard_requires_at_least_one_button_row(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MaxInlineKeyboardMessageDto(
            text: 'test',
            buttonRows: [],
            chatId: 1,
        );
    }
}

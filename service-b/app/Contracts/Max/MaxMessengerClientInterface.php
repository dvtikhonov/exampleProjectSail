<?php

namespace App\Contracts\Max;

use App\DTO\Max\MaxMessageDto;

interface MaxMessengerClientInterface
{
    public function uploadFile(string $contents, string $fileName): string;

    public function sendMessage(MaxMessageDto $message): void;
}

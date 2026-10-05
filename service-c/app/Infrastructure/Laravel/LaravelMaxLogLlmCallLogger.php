<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\LlmCallLoggerInterface;
use App\DTO\Shared\LlmCallExchangeDto;
use Psr\Log\LoggerInterface;

/**
 * Laravel-адаптер: пишет обмены с LLM в канал max_log текстом.
 */
final class LaravelMaxLogLlmCallLogger implements LlmCallLoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function logExchange(LlmCallExchangeDto $exchange): void
    {
        $context = [
            'provider' => $exchange->provider,
            'operation' => $exchange->operation,
            'actor' => $exchange->actor,
            'request' => $exchange->requestText,
        ];

        foreach ($exchange->meta as $key => $value) {
            $context[$key] = $this->scalarToText($value);
        }

        if ($exchange->responseText !== null && $exchange->responseText !== '') {
            $context['response'] = $exchange->responseText;
        }

        if ($exchange->errorText !== null && $exchange->errorText !== '') {
            $context['error'] = $exchange->errorText;
        }

        $this->logger->info(
            sprintf('LLM %s/%s', $exchange->provider, $exchange->operation),
            $context,
        );
    }

    private function scalarToText(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}

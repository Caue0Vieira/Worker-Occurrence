<?php

declare(strict_types=1);

namespace Infrastructure\Adapters;

use Domain\Shared\Repository\LoggerInterface;
use Illuminate\Support\Facades\Log;

class LaravelLoggerAdapter implements LoggerInterface
{
    private array $fixedContext = [];

    public function setContext(array $context): void
    {
        $this->fixedContext = $context;
    }

    public function info(string $message, array $context = []): void
    {
        Log::info($message, array_merge($this->fixedContext, $context));
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, array_merge($this->fixedContext, $context));
    }

    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, array_merge($this->fixedContext, $context));
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge($this->fixedContext, $context));
    }

    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, array_merge($this->fixedContext, $context));
    }
}

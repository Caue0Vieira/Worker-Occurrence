<?php

declare(strict_types=1);

namespace Infrastructure\Cache;

use Domain\Shared\Repository\LoggerInterface;
use Illuminate\Support\Facades\Redis;
use Throwable;

final readonly class OccurrenceListCacheInvalidator
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function invalidate(): void
    {
        try {
            $connection = Redis::connection(config('api.occurrences_cache.redis_connection', 'cache'));
            $prefix = (string) config('api.occurrences_cache.key_prefix', 'occurrences:list');
            $versionKey = "{$prefix}:version";

            $connection->incr($versionKey);
        } catch (Throwable $exception) {
            $this->logger->warning('⚠️ [Cache] Failed to invalidate occurrences list cache', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}



<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Audit\Repositories\AuditLoggerInterface;
use Domain\Dispatch\Repositories\DispatchRepositoryInterface;
use Domain\Dispatch\Service\DispatchService;
use Domain\Occurrence\Repositories\OccurrenceRepositoryInterface;
use Domain\Occurrence\Services\OccurrenceService;
use Domain\Shared\Repositories\IdempotencyRepositoryInterface;
use Domain\Shared\Repository\LoggerInterface;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Adapters\AuditLogger;
use Infrastructure\Adapters\LaravelLoggerAdapter;
use Infrastructure\Cache\OccurrenceListCacheInvalidator;
use Infrastructure\Console\Commands\CommandProcessor;
use Infrastructure\Persistence\Repositories\DispatchRepository;
use Infrastructure\Persistence\Repositories\IdempotencyRepository;
use Infrastructure\Persistence\Repositories\OccurrenceRepository;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Repositories
        $this->app->bind(
            OccurrenceRepositoryInterface::class,
            OccurrenceRepository::class
        );

        $this->app->bind(
            DispatchRepositoryInterface::class,
            DispatchRepository::class
        );

        $this->app->bind(
            IdempotencyRepositoryInterface::class,
            IdempotencyRepository::class
        );

        // Services
        $this->app->bind(
            AuditLoggerInterface::class,
            AuditLogger::class
        );

        // Domain Services
        $this->app->singleton(OccurrenceService::class);
        $this->app->singleton(DispatchService::class);

        // Command Processor
        $this->app->singleton(CommandProcessor::class);
        $this->app->singleton(OccurrenceListCacheInvalidator::class);

        // Logger Adapter
        $this->app->singleton(LoggerInterface::class, function ($app) {
            return new LaravelLoggerAdapter();
        });

    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}


<?php

namespace App\Providers;

use Domain\Shared\Repository\LoggerInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->runningInConsole() && app()->runningUnitTests() === false) {
            $logger = app(LoggerInterface::class);
            $logger->info('🚀 [Worker] Worker application started', [
                'environment' => app()->environment(),
                'queue_connection' => config('queue.default'),
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }
}

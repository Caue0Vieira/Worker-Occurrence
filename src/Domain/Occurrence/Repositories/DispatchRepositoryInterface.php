<?php

declare(strict_types=1);

namespace Domain\Occurrence\Repositories;

use Domain\Occurrence\Entities\Dispatch;
use Domain\Shared\ValueObjects\Uuid;

interface DispatchRepositoryInterface
{
    public function save(Dispatch $dispatch): Dispatch;

    public function findById(Uuid $id): ?Dispatch;

    public function findByOccurrenceIdAndResourceCode(Uuid $occurrenceId, string $resourceCode): ?Dispatch;
}


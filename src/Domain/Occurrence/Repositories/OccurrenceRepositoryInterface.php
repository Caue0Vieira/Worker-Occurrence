<?php

declare(strict_types=1);

namespace Domain\Occurrence\Repositories;

use Domain\Occurrence\Entities\Occurrence;
use Domain\Shared\ValueObjects\Uuid;

interface OccurrenceRepositoryInterface
{
    public function save(Occurrence $occurrence): Occurrence;

    public function findById(Uuid $id): ?Occurrence;

    public function findByIdForUpdate(Uuid $id): ?Occurrence;

    public function findByExternalId(string $externalId): ?Occurrence;

    public function existsByExternalId(string $externalId): bool;
}


<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Repositories;

use Domain\Occurrence\Entities\Occurrence;
use Domain\Shared\ValueObjects\Uuid;
use Exception;
use Illuminate\Support\Facades\DB;
use Domain\Occurrence\Repositories\OccurrenceRepositoryInterface;

class OccurrenceRepository implements OccurrenceRepositoryInterface
{
    public function create(Occurrence $occurrence): Occurrence
    {
        $data = $occurrence->toArray();

        unset(
            $data['type_name'],
            $data['type_category'],
            $data['status_name'],
            $data['status_is_final'],
            $data['dispatches']
        );

        DB::table('occurrences')->insert($data);

        return $occurrence;
    }

    public function update(Occurrence $occurrence): Occurrence
    {
        $data = $occurrence->toArray();

        unset(
            $data['id'],
            $data['created_at'],
            $data['type_name'],
            $data['type_category'],
            $data['status_name'],
            $data['status_is_final'],
            $data['dispatches']
        );

        DB::table('occurrences')
            ->where('id', $occurrence->id()->toString())
            ->update($data);

        return $occurrence;
    }

    public function save(Occurrence $occurrence): Occurrence
    {
        return $this->exists($occurrence->id())
            ? $this->update($occurrence)
            : $this->create($occurrence);
    }

    /**
     * @throws Exception
     */
    public function findById(Uuid $id): ?Occurrence
    {
        $row = DB::table('occurrences as o')
            ->select(
                'o.*',
                'ot.name as type_name',
                'ot.category as type_category',
                'os.name as status_name',
                'os.is_final as status_is_final'
            )
            ->leftJoin('occurrence_types as ot', 'o.type_code', '=', 'ot.code')
            ->leftJoin('occurrence_status as os', 'o.status_code', '=', 'os.code')
            ->where('o.id', $id->toString())
            ->first();

        return $row ? Occurrence::fromArray((array) $row) : null;
    }

    /**
     * @throws Exception
     */
    public function findByIdForUpdate(Uuid $id): ?Occurrence
    {
        // PostgreSQL não permite FOR UPDATE com LEFT JOIN
        // Primeiro fazemos o lock na tabela principal (sem JOINs)
        $locked = DB::table('occurrences')
            ->where('id', $id->toString())
            ->lockForUpdate()
            ->first();

        if (!$locked) {
            return null;
        }

        // Depois buscamos os dados completos com JOINs
        $row = DB::table('occurrences as o')
            ->select(
                'o.*',
                'ot.name as type_name',
                'ot.category as type_category',
                'os.name as status_name',
                'os.is_final as status_is_final'
            )
            ->leftJoin('occurrence_types as ot', 'o.type_code', '=', 'ot.code')
            ->leftJoin('occurrence_status as os', 'o.status_code', '=', 'os.code')
            ->where('o.id', $id->toString())
            ->first();

        return $row ? Occurrence::fromArray((array) $row) : null;
    }

    /**
     * @throws Exception
     */
    public function findByExternalId(string $externalId): ?Occurrence
    {
        $row = DB::table('occurrences as o')
            ->select(
                'o.*',
                'ot.name as type_name',
                'ot.category as type_category',
                'os.name as status_name',
                'os.is_final as status_is_final'
            )
            ->leftJoin('occurrence_types as ot', 'o.type_code', '=', 'ot.code')
            ->leftJoin('occurrence_status as os', 'o.status_code', '=', 'os.code')
            ->where('o.external_id', $externalId)
            ->first();

        return $row ? Occurrence::fromArray((array) $row) : null;
    }

    public function existsByExternalId(string $externalId): bool
    {
        return DB::table('occurrences')
            ->where('external_id', $externalId)
            ->exists();
    }

    private function exists(Uuid $id): bool
    {
        return DB::table('occurrences')
            ->where('id', $id->toString())
            ->exists();
    }
}


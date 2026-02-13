<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Repositories;

use Domain\Occurrence\Entities\Dispatch;
use Domain\Occurrence\Repositories\DispatchRepositoryInterface;
use Domain\Shared\ValueObjects\Uuid;
use Exception;
use Illuminate\Support\Facades\DB;

class DispatchRepository implements DispatchRepositoryInterface
{
    public function create(Dispatch $dispatch): Dispatch
    {
        $data = $dispatch->toArray();

        unset(
            $data['status_name'],
            $data['status_is_active']
        );

        DB::table('dispatches')->insert($data);

        return $dispatch;
    }

    public function update(Dispatch $dispatch): Dispatch
    {
        $data = $dispatch->toArray();

        unset(
            $data['id'],
            $data['created_at'],
            $data['status_name'],
            $data['status_is_active']
        );

        DB::table('dispatches')
            ->where('id', $dispatch->id()->toString())
            ->update($data);

        return $dispatch;
    }

    public function save(Dispatch $dispatch): Dispatch
    {
        return $this->exists($dispatch->id())
            ? $this->update($dispatch)
            : $this->create($dispatch);
    }

    /**
     * @throws Exception
     */
    public function findById(Uuid $id): ?Dispatch
    {
        $row = DB::table('dispatches as d')
            ->select(
                'd.*',
                'ds.name as status_name',
                'ds.is_active as status_is_active'
            )
            ->leftJoin('dispatch_status as ds', 'd.status_code', '=', 'ds.code')
            ->where('d.id', $id->toString())
            ->first();

        return $row ? Dispatch::fromArray((array)$row) : null;
    }

    private function exists(Uuid $id): bool
    {
        return DB::table('dispatches')
            ->where('id', $id->toString())
            ->exists();
    }
}


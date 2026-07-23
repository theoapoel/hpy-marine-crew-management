<?php

namespace App\Repositories;

use App\Models\Crew;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Data access for crew records. Swappable backend:
 *  - EloquentCrewRepository  -> local SQLite (placeholder/dev)
 *  - ErpnextCrewRepository   -> ERP HPY, the system of record
 *
 * Returns Crew models so the existing Blade views stay unchanged regardless of source.
 *
 * @param array{search?: string|null, status?: string|null} $filters
 */
interface CrewRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int|string $id): Crew;

    public function create(array $data): Crew;

    public function update(int|string $id, array $data): Crew;

    public function delete(int|string $id): void;
}

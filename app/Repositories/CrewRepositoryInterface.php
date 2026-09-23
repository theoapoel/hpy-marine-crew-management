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
 * @param array{search?: string|null, status?: string|null, rank?: string|null} $filters
 *        rank RANK_NONE matches crew with no rank set
 */
interface CrewRepositoryInterface
{
    /** The rank filter value (and summary key) for crew with no rank set. */
    public const RANK_NONE = 'Unspecified';

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int|string $id): Crew;

    public function create(array $data): Crew;

    public function update(int|string $id, array $data): Crew;

    public function delete(int|string $id): void;

    /**
     * Crew of the session company per rank, across every page of the list, split by
     * crew status: [rank => ['total' => n, 'Onboard' => n, 'Standby' => n, 'Sign Off' => n]].
     *
     * @return array<string, array<string, int>>
     */
    public function rankSummary(): array;
}

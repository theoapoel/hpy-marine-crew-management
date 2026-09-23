<?php

namespace App\Repositories;

use App\Models\Crew;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Local SQLite-backed crew repository (placeholder data until ERP HPY integration).
 */
class EloquentCrewRepository implements CrewRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Crew::with('vessel')->latest();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('seaman_book_no', 'like', "%{$search}%");
            });
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($rank = $filters['rank'] ?? null) {
            $rank === self::RANK_NONE
                ? $query->where(fn ($q) => $q->whereNull('rank')->orWhere('rank', ''))
                : $query->where('rank', $rank);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function rankSummary(): array
    {
        $summary = [];

        foreach (Crew::query()->get(['rank', 'status']) as $crew) {
            $rank = $crew->rank ?: self::RANK_NONE;
            $summary[$rank]['total'] = ($summary[$rank]['total'] ?? 0) + 1;
            if ($crew->status) {
                $summary[$rank][$crew->status] = ($summary[$rank][$crew->status] ?? 0) + 1;
            }
        }

        return $summary;
    }

    public function find(int|string $id): Crew
    {
        return Crew::with('vessel')->findOrFail($id);
    }

    public function create(array $data): Crew
    {
        return Crew::create($this->columnsOnly($data));
    }

    public function update(int|string $id, array $data): Crew
    {
        $crew = Crew::findOrFail($id);
        $crew->update($this->columnsOnly($data));

        return $crew;
    }

    /**
     * The crew form carries the full ERP HPY Employee field set; only the handful of
     * columns this placeholder table has can be stored.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columnsOnly(array $data): array
    {
        return array_intersect_key($data, array_flip((new Crew())->getFillable()));
    }

    public function delete(int|string $id): void
    {
        Crew::findOrFail($id)->delete();
    }
}

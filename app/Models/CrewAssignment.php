<?php

namespace App\Models;

use App\Repositories\CrewRepositoryInterface;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A berth on a vessel: who sails, on what, from when to when.
 *
 * Signing on and off writes back to the Employee record in ERP HPY (vessel and crew
 * status), so Crew Master and the Documents views stay truthful.
 */
class CrewAssignment extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = ['id'];

    /** Defaults mirrored from the table, so a fresh model reads like a stored row. */
    protected $attributes = [
        'status' => 'planned',
        'wage_currency' => 'IDR',
    ];

    protected $casts = [
        'planned_sign_on_date' => 'date',
        'sign_on_date' => 'date',
        'planned_sign_off_date' => 'date',
        'sign_off_date' => 'date',
        'wage' => 'decimal:2',
        'contract_months' => 'integer',
    ];

    public const STATUSES = ['planned', 'onboard', 'signed_off', 'cancelled'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CrewCandidate::class, 'crew_candidate_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CrewApplication::class, 'crew_application_id');
    }

    /** Waiting to board. */
    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', 'planned');
    }

    public function scopeOnboard(Builder $query): Builder
    {
        return $query->where('status', 'onboard');
    }

    /** Contracts running out within N days — the sign-off planning list. */
    public function scopeDueForSignOff(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'onboard')
            ->whereNotNull('planned_sign_off_date')
            ->where('planned_sign_off_date', '<=', now()->addDays($days));
    }

    public function getDaysOnboardAttribute(): ?int
    {
        if (! $this->sign_on_date) {
            return null;
        }

        return (int) $this->sign_on_date->diffInDays($this->sign_off_date ?? now());
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'onboard'
            && $this->planned_sign_off_date !== null
            && $this->planned_sign_off_date->isPast();
    }

    public static function nextCode(?Carbon $on = null): string
    {
        $year = ($on ?? now())->format('Y');

        $last = static::withTrashed()
            ->where('assignment_code', 'like', "ASG-{$year}-%")
            ->orderByDesc('assignment_code')
            ->value('assignment_code');

        return sprintf('ASG-%s-%05d', $year, $last ? ((int) substr($last, -5)) + 1 : 1);
    }

    /**
     * What the Employee record in ERP HPY should look like for this assignment.
     * Planned crew already carry the vessel they are booked on, so Crew Master shows
     * the posting straight after the assignment is made — not only after sign on.
     *
     * @return array<string, mixed>
     */
    public function erpSnapshot(): array
    {
        return match ($this->status) {
            'onboard' => [
                'status' => 'Onboard',
                'vessel' => (string) $this->vessel,
                'sign_on_date' => $this->sign_on_date?->toDateString(),
                'contract_end_date' => $this->planned_sign_off_date?->toDateString(),
            ],
            'planned' => [
                'status' => 'Standby',
                'vessel' => (string) $this->vessel,
            ],
            // Signed off or cancelled: the berth is free again.
            default => ['status' => 'Standby', 'vessel' => ''],
        };
    }

    /** Mirror this assignment onto the ERP HPY Employee. */
    public function syncToErp(): void
    {
        $this->pushToErp($this->erpSnapshot());
    }

    /** Board the vessel: stamp the date and tell ERP HPY the crew is onboard. */
    public function signOn(array $data = []): self
    {
        $signOn = $data['sign_on_date'] ?? now()->toDateString();
        $months = (int) ($data['contract_months'] ?? $this->contract_months ?? 0);

        $this->fill(array_filter([
            'status' => 'onboard',
            'sign_on_date' => $signOn,
            'sign_on_port' => $data['sign_on_port'] ?? $this->sign_on_port,
            'contract_months' => $months ?: null,
            'planned_sign_off_date' => $data['planned_sign_off_date']
                ?? ($months ? Carbon::parse($signOn)->addMonths($months)->toDateString() : $this->planned_sign_off_date),
        ], fn ($v) => $v !== null))->save();

        return $this;
    }

    /** Leave the vessel: stamp the date and free the crew in ERP HPY. */
    public function signOff(array $data = []): self
    {
        $this->fill(array_filter([
            'status' => 'signed_off',
            'sign_off_date' => $data['sign_off_date'] ?? now()->toDateString(),
            'sign_off_port' => $data['sign_off_port'] ?? $this->sign_off_port,
            'sign_off_reason' => $data['sign_off_reason'] ?? $this->sign_off_reason,
        ], fn ($v) => $v !== null))->save();

        $this->candidate?->forceFill(['status' => 'on_leave'])->save();

        return $this;
    }

    /**
     * Mirror the move onto the ERP HPY Employee. A failure here must not lose the
     * assignment itself, so it is reported and swallowed.
     *
     * @param  array<string, mixed>  $data
     */
    public function pushToErp(array $data): void
    {
        if (blank($this->employee_id)) {
            return;
        }

        try {
            app(CrewRepositoryInterface::class)->update($this->employee_id, $data);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

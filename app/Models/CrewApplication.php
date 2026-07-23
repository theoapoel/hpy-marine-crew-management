<?php

namespace App\Models;

use App\Support\ErpUser;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One candidate going after one seat. It walks the pipeline stages until it is hired
 * (which produces a CrewAssignment) or closed.
 */
class CrewApplication extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = ['id'];

    /** Defaults mirrored from the table, so a fresh model reads like a stored row. */
    protected $attributes = [
        'stage' => 'applied',
        'offered_salary_currency' => 'IDR',
    ];

    protected $casts = [
        'applied_date' => 'date',
        'screening_date' => 'date',
        'interview_date' => 'date',
        'mcu_date' => 'date',
        'offer_date' => 'date',
        'decision_date' => 'date',
        'offered_salary' => 'decimal:2',
        'interview_score' => 'integer',
    ];

    /** The pipeline, in order. */
    public const STAGES = ['applied', 'screening', 'interview', 'mcu', 'offer', 'hired'];

    /** Stages an application can end in without being hired. */
    public const CLOSED_STAGES = ['rejected', 'withdrawn'];

    public const MCU_RESULTS = ['pending', 'fit', 'unfit'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CrewCandidate::class, 'crew_candidate_id');
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(CrewAssignment::class);
    }

    /** Still moving through the pipeline. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', array_merge(['hired'], self::CLOSED_STAGES));
    }

    public function scopeInStage(Builder $query, ?string $stage): Builder
    {
        return $stage ? $query->where('stage', $stage) : $query;
    }

    public function getIsOpenAttribute(): bool
    {
        return ! in_array($this->stage, array_merge(['hired'], self::CLOSED_STAGES), true);
    }

    /** The stage after the current one, or null at the end of the pipeline. */
    public function getNextStageAttribute(): ?string
    {
        $position = array_search($this->stage, self::STAGES, true);

        return $position === false ? null : (self::STAGES[$position + 1] ?? null);
    }

    /** How long the application has been sitting where it is. */
    public function getDaysInPipelineAttribute(): int
    {
        return (int) $this->applied_date?->diffInDays(now());
    }

    public static function nextCode(?Carbon $on = null): string
    {
        $year = ($on ?? now())->format('Y');

        $last = static::withTrashed()
            ->where('application_code', 'like', "APP-{$year}-%")
            ->orderByDesc('application_code')
            ->value('application_code');

        return sprintf('APP-%s-%05d', $year, $last ? ((int) substr($last, -5)) + 1 : 1);
    }

    /**
     * Move to the next stage, stamping the date that belongs to it.
     * Hiring promotes the candidate and opens an assignment.
     */
    public function advance(array $data = []): self
    {
        $stage = $data['stage'] ?? $this->next_stage;

        if ($stage === null) {
            return $this;
        }

        $stamp = [
            'screening' => 'screening_date',
            'interview' => 'interview_date',
            'mcu' => 'mcu_date',
            'offer' => 'offer_date',
            'hired' => 'decision_date',
        ][$stage] ?? null;

        $this->fill(array_filter([
            'stage' => $stage,
            $stamp => $data[$stamp] ?? now()->toDateString(),
            'interview_score' => $data['interview_score'] ?? null,
            'interview_notes' => $data['interview_notes'] ?? null,
            'mcu_result' => $data['mcu_result'] ?? null,
            'offered_salary' => $data['offered_salary'] ?? null,
        ], fn ($v) => $v !== null))->save();

        if ($stage === 'hired') {
            $this->hire();
        }

        return $this;
    }

    /** Close the application without hiring. */
    public function close(string $stage, ?string $reason = null): self
    {
        $this->fill([
            'stage' => in_array($stage, self::CLOSED_STAGES, true) ? $stage : 'rejected',
            'decision_date' => now()->toDateString(),
            'rejection_reason' => $reason,
        ])->save();

        return $this;
    }

    /**
     * Hired: make sure the candidate exists as an ERP HPY Employee, then open a
     * planned assignment for the berth that was applied for.
     */
    private function hire(): void
    {
        $candidate = $this->candidate;

        if ($candidate && ! $candidate->linked_employee_id) {
            $candidate->promoteToEmployee();
        }

        if ($this->assignment()->exists()) {
            return;
        }

        $this->assignment()->create([
            'crew_candidate_id' => $candidate?->id,
            'employee_id' => $candidate?->linked_employee_id,
            'crew_name' => $candidate?->full_name ?? '—',
            'vessel' => $this->vessel,
            'rank' => $this->applied_rank,
            'company' => $this->company,
            'status' => 'planned',
            'planned_sign_on_date' => $candidate?->availability_date,
            'wage' => $this->offered_salary,
            'wage_currency' => $this->offered_salary_currency,
            'created_by' => ErpUser::id(),
            'updated_by' => ErpUser::id(),
        ]);
    }
}

<?php

namespace App\Models;

use App\Repositories\CrewRepositoryInterface;
use App\Services\Erpnext\ErpnextClient;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A seafarer in the Candidate Pool — everyone who ever applied, hired or not.
 * One candidate may apply many times, so applications/assignments hang off here.
 *
 * Employees themselves live in ERP HPY (Doctype Employee); once a candidate is
 * promoted, linked_employee_id holds that Employee's name (e.g. HR-EMP-00001).
 */
class CrewCandidate extends Model
{
    use HasFactory;
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'passport_expiry' => 'date',
        'seaman_book_expiry' => 'date',
        'last_sign_off_date' => 'date',
        'coc_expiry' => 'date',
        'mcu_expiry' => 'date',
        'endc_expiry' => 'date',
        'availability_date' => 'date',
        'cop_certificates' => 'array',
        'other_documents' => 'array',
        'expected_salary' => 'decimal:2',
        'source_cost' => 'decimal:2',
        'years_of_experience' => 'integer',
    ];

    public const GENDERS = ['male', 'female'];

    public const MARITAL_STATUSES = ['single', 'married', 'divorced', 'widowed'];

    public const COC_TYPES = [
        'ANT-I', 'ANT-II', 'ANT-III', 'ANT-IV', 'ANT-V', 'ANT-D',
        'ATT-I', 'ATT-II', 'ATT-III', 'ATT-IV', 'ATT-V', 'ATT-D',
        'Rating', 'None',
    ];

    public const SOURCES = [
        'walk_in', 'website', 'referral', 'agency', 'job_board',
        'school', 'alumni', 'union', 'head_hunter', 'other',
    ];

    /** Sources that carry a recruitment cost. */
    public const PAID_SOURCES = ['agency', 'head_hunter', 'job_board'];

    public const STATUSES = ['applicant', 'in_process', 'employed', 'on_leave', 'blacklist', 'retired'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    | Employees and suppliers live in ERP HPY rather than in a local table, so those
    | links resolve through the ERP HPY client instead of a foreign key.
    */

    /** The ERP HPY Employee who referred this candidate. */
    public function referredBy(): ?Crew
    {
        return $this->erpEmployee($this->referred_by_employee_id);
    }

    /** The manning agency (ERP HPY Supplier) the candidate came from. */
    public function sourceAgency(): ?array
    {
        if (blank($this->source_agency_id)) {
            return null;
        }

        try {
            return app(ErpnextClient::class)->get('Supplier', $this->source_agency_id);
        } catch (\Throwable) {
            return null;
        }
    }

    /** The Employee record created when this candidate was hired. */
    public function linkedEmployee(): ?Crew
    {
        return $this->erpEmployee($this->linked_employee_id);
    }

    /**
     * Applications this candidate filed against vacancies.
     * CrewApplication arrives with the recruitment pipeline module.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(CrewApplication::class);
    }

    /** Shipboard assignments, once hired. */
    public function assignments(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }

    private function erpEmployee(?string $name): ?Crew
    {
        if (blank($name)) {
            return null;
        }

        try {
            return app(CrewRepositoryInterface::class)->find($name);
        } catch (\Throwable) {
            return null; // record removed in ERP HPY
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /** Ready to sail: not hired, blacklisted or retired, and available by now. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereIn('status', ['applicant', 'in_process', 'on_leave'])
            ->where(function (Builder $q) {
                $q->whereNull('availability_date')->orWhere('availability_date', '<=', now());
            });
    }

    public function scopeByRank(Builder $query, ?string $rank): Builder
    {
        return $rank ? $query->where('applied_rank', $rank) : $query;
    }

    public function scopeByCocType(Builder $query, ?string $coc): Builder
    {
        return $coc ? $query->where('coc_type', $coc) : $query;
    }

    /** Medical check-up expiring within N days (or already expired). */
    public function scopeExpiringMcu(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('mcu_expiry')->where('mcu_expiry', '<=', now()->addDays($days));
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->address, $this->city, $this->province, $this->postal_code])
            ->filter()
            ->implode(', ');
    }

    public function getIsAvailableAttribute(): bool
    {
        if (in_array($this->status, ['blacklist', 'retired', 'employed'], true)) {
            return false;
        }

        return $this->availability_date === null || $this->availability_date->lessThanOrEqualTo(now());
    }

    /*
    |--------------------------------------------------------------------------
    | Behaviour
    |--------------------------------------------------------------------------
    */

    /** Next code in the CND-YYYY-XXXXX series. */
    public static function nextCode(?Carbon $on = null): string
    {
        $year = ($on ?? now())->format('Y');

        $last = static::withTrashed()
            ->where('candidate_code', 'like', "CND-{$year}-%")
            ->orderByDesc('candidate_code')
            ->value('candidate_code');

        $sequence = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('CND-%s-%05d', $year, $sequence);
    }

    /**
     * Hire the candidate: create the Employee in ERP HPY from what we already know
     * and remember the link. Returns the new Employee.
     */
    public function promoteToEmployee(array $overrides = []): Crew
    {
        $employee = app(CrewRepositoryInterface::class)->create(array_merge(array_filter([
            'name' => $this->full_name,
            'first_name' => $this->first_name ?: strtok($this->full_name, ' '),
            'last_name' => $this->last_name,
            'gender' => $this->gender === 'female' ? 'Female' : 'Male',
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'date_of_joining' => ($this->availability_date ?: now())->toDateString(),
            'rank' => $this->applied_rank,
            'status' => 'Standby',
            'nationality' => $this->nationality,
            'seaman_book_no' => $this->seaman_book_no,
            'seaman_book_expiry' => $this->seaman_book_expiry?->toDateString(),
            'passport_number' => $this->passport_no,
            'valid_upto' => $this->passport_expiry?->toDateString(),
            'place_of_issue' => $this->passport_issue_place,
            'cell_number' => $this->phone,
            'personal_email' => $this->email,
            'current_address' => $this->full_address,
            'person_to_be_contacted' => $this->emergency_contact_name,
            'emergency_phone_number' => $this->emergency_contact_phone,
            'relation' => $this->emergency_contact_relation,
            'marital_status' => $this->marital_status ? ucfirst($this->marital_status) : null,
            'blood_group' => $this->blood_type,
        ], fn ($value) => $value !== null && $value !== ''), $overrides));

        $this->forceFill([
            'linked_employee_id' => $employee->id,
            'status' => 'employed',
        ])->save();

        return $employee;
    }
}

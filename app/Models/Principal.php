<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A principal is the shipowner, manager or charterer whose vessels we crew.
 *
 * Vessels live in ERP HPY (Doctype Vessel) and point back here through the custom
 * "principal" Link field, so a principal's fleet is read from there rather than from
 * a local foreign key.
 */
class Principal extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = [
        'principal_type' => 'shipowner',
        'status' => 'active',
        'country' => 'Indonesia',
        'currency' => 'IDR',
        'erpnext_sync_status' => 'pending',
    ];

    protected $casts = [
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'manning_fee_amount' => 'decimal:2',
        'addresses' => 'array',
        'manning_fees' => 'array',
        'erpnext_synced_at' => 'datetime',
    ];

    public const TYPES = ['shipowner', 'ship_manager', 'charterer', 'operator'];

    public const STATUSES = ['prospect', 'active', 'on_hold', 'terminated'];

    public const CONTRACT_TYPES = ['exclusive', 'non_exclusive'];

    public const FEE_TYPES = ['per_crew', 'percentage', 'flat_monthly'];

    public const ADDRESS_TYPES = ['Head Office', 'Branch Office', 'Billing', 'Operational', 'Other'];

    /** Fields of one address row; the first row is mirrored onto the single columns. */
    public const ADDRESS_FIELDS = ['address_type', 'address', 'city', 'province', 'postal_code', 'country', 'phone', 'fax', 'email', 'wechat'];

    /** Fields of one manning fee row; the first row is mirrored onto the single columns. */
    public const FEE_FIELDS = ['fee_type', 'amount', 'currency', 'description'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function contactPersons(): HasMany
    {
        return $this->hasMany(PrincipalContactPerson::class)->orderByDesc('is_primary');
    }

    /** The local placeholder vessels table, kept for backward compatibility. */
    public function vessels(): HasMany
    {
        return $this->hasMany(Vessel::class);
    }

    /**
     * The real fleet: ERP HPY vessels pointing at this principal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function erpVessels(): array
    {
        if (blank($this->erpnext_name)) {
            return [];
        }

        try {
            return app(ErpnextClient::class)->list(
                'Vessel',
                ['name', 'vessel_name', 'imo_number', 'vessel_type', 'company'],
                [['principal', '=', $this->erpnext_name]],
                200,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('principal_type', $type) : $query;
    }

    public function scopeWithVesselCount(Builder $query): Builder
    {
        return $query->withCount('vessels');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /** How many vessels ERP HPY has under this principal. */
    public function getVesselCountAttribute(): int
    {
        return count($this->erpVessels());
    }

    public function getPrimaryContactAttribute(): ?PrincipalContactPerson
    {
        return $this->contactPersons->firstWhere('is_primary', true) ?? $this->contactPersons->first();
    }

    /**
     * Address rows, or for a principal saved before rows existed, one row built from
     * the single columns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function addressRows(): array
    {
        if (! empty($this->addresses)) {
            return array_values($this->addresses);
        }

        $row = array_filter([
            'address' => $this->head_office_address, 'city' => $this->city, 'province' => $this->province,
            'postal_code' => $this->postal_code, 'phone' => $this->phone, 'fax' => $this->fax,
            'email' => $this->email, 'wechat' => $this->wechat,
        ], 'filled');

        return $row ? [['address_type' => 'Head Office'] + $row] : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function feeRows(): array
    {
        if (! empty($this->manning_fees)) {
            return array_values($this->manning_fees);
        }

        return filled($this->manning_fee_type) || filled($this->manning_fee_amount)
            ? [['fee_type' => $this->manning_fee_type, 'amount' => $this->manning_fee_amount, 'currency' => $this->currency]]
            : [];
    }

    public function getIsContractActiveAttribute(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $started = $this->contract_start_date === null || $this->contract_start_date->isPast();
        $notEnded = $this->contract_end_date === null || $this->contract_end_date->isFuture();

        return $started && $notEnded;
    }

    /*
    |--------------------------------------------------------------------------
    | Behaviour
    |--------------------------------------------------------------------------
    */

    /** Next code in the PRN-0001 series. */
    public static function nextCode(): string
    {
        $last = static::withTrashed()
            ->where('principal_code', 'like', 'PRN-%')
            ->orderByDesc('principal_code')
            ->value('principal_code');

        return sprintf('PRN-%04d', $last ? ((int) substr($last, -4)) + 1 : 1);
    }

    /*
    |--------------------------------------------------------------------------
    | ERP HPY
    |--------------------------------------------------------------------------
    */

    public function erpDoctype(): string
    {
        return 'Principal';
    }

    /**
     * The principal as ERP HPY wants it, contact people included.
     *
     * @return array<string, mixed>
     */
    public function toErpPayload(): array
    {
        return array_filter([
            'principal_code' => $this->principal_code,
            'principal_name' => $this->principal_name,
            'legal_entity_name' => $this->legal_entity_name,
            'principal_type' => $this->label($this->principal_type),
            'country' => $this->country,
            'status' => $this->label($this->status),
            'head_office_address' => $this->head_office_address,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'wechat' => $this->wechat,
            'email' => $this->email,
            'website' => $this->website,
            'contract_start_date' => $this->contract_start_date?->toDateString(),
            'contract_end_date' => $this->contract_end_date?->toDateString(),
            'contract_type' => $this->label($this->contract_type),
            'manning_fee_type' => $this->label($this->manning_fee_type),
            'manning_fee_amount' => $this->manning_fee_amount,
            'currency' => $this->currency,
            'payment_terms' => $this->payment_terms,
            'p_and_i_club' => $this->p_and_i_club,
            'wage_scale_reference' => $this->wage_scale_reference,
            'billing_address' => $this->billing_address,
            'tax_id' => $this->tax_id,
            'bank_details' => $this->bank_details,
            'notes' => $this->notes,
            'addresses' => collect($this->addressRows())->map(fn ($row) => array_filter($row, 'filled'))->values()->all(),
            'manning_fees' => collect($this->feeRows())->map(fn ($row) => array_filter([
                'fee_type' => $this->label($row['fee_type'] ?? null),
                'amount' => $row['amount'] ?? null,
                'currency' => $row['currency'] ?? null,
                'description' => $row['description'] ?? null,
            ], 'filled'))->values()->all(),
            'contact_persons' => $this->contactPersons->map(fn (PrincipalContactPerson $person) => array_filter([
                'contact_name' => $person->name,
                'position' => $person->position,
                'email' => $person->email,
                'phone' => $person->phone,
                'whatsapp' => $person->whatsapp,
                'wechat' => $person->wechat,
                'is_primary' => $person->is_primary ? 1 : 0,
                'notes' => $person->notes,
            ], fn ($value) => $value !== null && $value !== ''))->values()->all(),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /** Fingerprint of what was last pushed, so unchanged records can be skipped. */
    public function erpHash(): string
    {
        return md5((string) json_encode($this->toErpPayload()));
    }

    /**
     * Push this principal into ERP HPY, creating or updating as needed. The outcome is
     * recorded on the row so the list can show what is still pending.
     */
    public function syncToErp(): bool
    {
        $client = app(ErpnextClient::class);
        $payload = $this->toErpPayload();

        try {
            $name = $this->erpnext_name && $client->exists($this->erpDoctype(), $this->erpnext_name)
                ? $client->update($this->erpDoctype(), $this->erpnext_name, $payload)['name']
                : $client->create($this->erpDoctype(), $payload)['name'];

            $this->forceFill([
                'erpnext_name' => $name,
                'erpnext_synced_at' => now(),
                'erpnext_sync_status' => 'synced',
                'erpnext_sync_error' => null,
                'erpnext_hash' => $this->erpHash(),
            ])->saveQuietly();

            return true;
        } catch (\Throwable $e) {
            report($e);

            $this->forceFill([
                'erpnext_sync_status' => 'failed',
                'erpnext_sync_error' => mb_substr($e->getMessage(), 0, 500),
            ])->saveQuietly();

            return false;
        }
    }

    /** ERP HPY selects are title case; ours are snake case. */
    private function label(?string $value): ?string
    {
        return $value === null ? null : ucwords(str_replace('_', ' ', $value));
    }
}

<?php

namespace App\Support;

use App\Console\Commands\SyncCrewFields;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Support\Facades\Cache;

/**
 * Crew document types: the records of the ERP HPY "Crew Certificate Type" master,
 * behind the Type dropdown of every crew certificate row. Managed from the Document
 * Types page or ERP HPY itself. Until erp:sync-crew-fields has created the master,
 * the list the app shipped with is offered and nothing can be added.
 */
class CrewDocumentTypes
{
    public const DOCTYPE = SyncCrewFields::CERT_TYPE_DOCTYPE;

    public const CATEGORIES = ['Identity', 'Competency', 'Proficiency', 'Medical', 'Training', 'Other'];

    private const KEY = 'crew_document_types';

    private const TTL = 600;

    public function __construct(private readonly ErpnextClient $client) {}

    /** Whether the master exists in ERP HPY, i.e. types can be added. */
    public function editable(): bool
    {
        return rescue(fn () => Cache::remember(self::KEY.':exists', self::TTL, fn () => $this->client->exists('DocType', self::DOCTYPE)), false);
    }

    /**
     * Every record, inactive ones included, for the management page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        if (! $this->editable()) {
            return [];
        }

        return Cache::remember(self::KEY.':rows', self::TTL, fn () => $this->client->list(
            self::DOCTYPE, ['name', 'certificate_name', 'category', 'validity_months', 'is_active'], [], 500, 0, ['order_by' => 'certificate_name asc'],
        ));
    }

    /**
     * Names offered in the dropdowns: active records, or the shipped list before
     * the master exists (or while ERP HPY cannot be reached).
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        $names = rescue(fn () => collect($this->rows())->where('is_active', 1)->pluck('name')->all(), []);

        return $names ?: SyncCrewFields::CERTIFICATE_TYPES;
    }

    /** @param array<string, mixed> $data */
    public function create(string $name, array $data = []): void
    {
        if (! $this->client->exists(self::DOCTYPE, $name)) {
            $this->client->create(self::DOCTYPE, ['certificate_name' => $name, 'is_active' => 1] + array_filter($data, 'filled'));
        }

        $this->forget();
    }

    /** @param array<string, mixed> $data */
    public function update(string $name, array $data): void
    {
        $this->client->update(self::DOCTYPE, $name, $data);
        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::KEY.':exists');
        Cache::forget(self::KEY.':rows');
    }
}

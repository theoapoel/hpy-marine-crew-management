<?php

namespace App\Services\Erpnext;

use Illuminate\Support\Facades\Cache;

/**
 * Values the crew form offers in its dropdowns, straight from ERP HPY (Rank, Vessel,
 * Department, Branch, Gender...). Cached briefly: these lists change rarely but the
 * form is opened often.
 */
class ErpnextOptions
{
    private const TTL = 300; // seconds

    public function __construct(private readonly ErpnextClient $client)
    {
    }

    /** @return array<int, string> */
    public function ranks(): array
    {
        return $this->names('Rank', 'rank_level asc');
    }

    /** Fleet of the company the session is working in. */
    public function vessels(): array
    {
        return $this->names('Vessel', null, $this->companyFilter());
    }

    /** @return array<int, string> */
    public function genders(): array
    {
        return $this->names('Gender');
    }

    /** @return array<int, string> */
    public function salutations(): array
    {
        return $this->names('Salutation');
    }

    /** @return array<int, string> */
    public function countries(): array
    {
        return $this->names('Country');
    }

    /**
     * The choices behind a field, whichever way ERP HPY stores them: the records of the
     * linked doctype for a Link, or the option list for a Select. Reading the field
     * definition means the app never drifts from what ERP HPY will accept — and a
     * Select that later becomes a Link needs no change here.
     *
     * @return array<int, string>
     */
    public function fieldOptions(string $doctype, string $fieldname): array
    {
        $field = $this->definition($doctype, $fieldname);

        if ($field['fieldtype'] === 'Link' && filled($field['options'])) {
            return $this->names($field['options']);
        }

        return collect(explode("\n", (string) $field['options']))
            ->map(fn ($option) => trim($option))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The master doctype a Link field points at, or null while it is still a Select —
     * i.e. whether new choices can be added as records rather than by editing a doctype.
     */
    public function linkTarget(string $doctype, string $fieldname): ?string
    {
        $field = $this->definition($doctype, $fieldname);

        return $field['fieldtype'] === 'Link' && filled($field['options']) ? $field['options'] : null;
    }

    /** Drop the cached records of a doctype, after one was added from the app. */
    public function forget(string $doctype): void
    {
        Cache::forget($this->namesKey($doctype, null, []));
    }

    /** Currencies enabled in ERP HPY. */
    public function currencies(): array
    {
        return $this->names('Currency', null, [['enabled', '=', 1]]);
    }

    /** @return array{fieldtype: ?string, options: ?string} */
    private function definition(string $doctype, string $fieldname): array
    {
        return Cache::remember("erpnext_field:{$doctype}:{$fieldname}", self::TTL, function () use ($doctype, $fieldname) {
            $definition = collect($this->client->get('DocType', $doctype)['fields'] ?? [])
                ->firstWhere('fieldname', $fieldname);

            return [
                'fieldtype' => $definition['fieldtype'] ?? null,
                'options' => $definition['options'] ?? null,
            ];
        });
    }

    /**
     * The choices behind a Select field, read from the doctype itself so the app never
     * drifts from what ERP HPY will accept.
     *
     * @return array<int, string>
     */
    public function selectOptions(string $doctype, string $fieldname): array
    {
        return Cache::remember("erpnext_select:{$doctype}:{$fieldname}", self::TTL, function () use ($doctype, $fieldname) {
            $field = collect($this->client->get('DocType', $doctype)['fields'] ?? [])
                ->firstWhere('fieldname', $fieldname);

            return collect(explode("\n", (string) ($field['options'] ?? '')))
                ->map(fn ($option) => trim($option))
                ->filter()
                ->values()
                ->all();
        });
    }

    /** Departments and branches are company-scoped in ERP HPY. */
    public function departments(): array
    {
        return $this->names('Department', null, $this->companyFilter());
    }

    public function branches(): array
    {
        return $this->names('Branch');
    }

    /** @return array<int, string> */
    public function designations(): array
    {
        return $this->names('Designation');
    }

    /**
     * @param  array<int, array<int, string>>  $filters
     * @return array<int, string>
     */
    private function names(string $doctype, ?string $orderBy = null, array $filters = []): array
    {
        return Cache::remember($this->namesKey($doctype, $orderBy, $filters), self::TTL, function () use ($doctype, $filters) {
            return array_column($this->client->list($doctype, ['name'], $filters, 500), 'name');
        });
    }

    /** @param array<int, array<int, mixed>> $filters */
    private function namesKey(string $doctype, ?string $orderBy, array $filters): string
    {
        return 'erpnext_options:' . $doctype . ':' . md5(json_encode([$orderBy, $filters, $this->client->company()]));
    }

    /** @return array<int, array<int, string>> */
    private function companyFilter(): array
    {
        $company = $this->client->company();

        return $company ? [['company', '=', $company]] : [];
    }
}

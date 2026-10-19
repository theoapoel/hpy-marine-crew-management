<?php

namespace App\Services;

use App\Console\Commands\SyncCrewFields;
use App\Models\Crew;
use App\Services\Erpnext\ErpnextClient;
use App\Support\CrewDocumentTypes;
use Illuminate\Support\Collection;

/**
 * The crew member's CV in the agency's own layout (the "Template CV" form):
 * personal data, certificates of competency, certificates of proficiency and the
 * sea service record. Everything comes from the Crew Master record in ERP HPY and
 * the sea service record kept here; nothing is typed in twice.
 */
class CrewCv
{
    /**
     * The proficiency certificates the form always lists, in its order. A crew member's
     * matching certificate fills the row; the rest stay blank, as on the paper form.
     */
    public const PROFICIENCY_ROWS = [
        'Basic Safety Training',
        'Proficiency in Survival Craft And Rescue Boat',
        'Advanced Fire Fighting',
        'Medical First Aid',
        'Medical Care On Board',
        'Basic Oil-Chemical Training (BOCT)',
        'Oil Tanker Specialized Training Program',
        'Chemical Tanker Specialized Training Program',
        'Bridge Resource management (BRM)',
        'Ship Security Officer',
        'ECDIS Generic Training',
        'ECDIS Specific Training',
        'ECDIS Specific Refreshing',
        'Ship Handling and Maneuvering',
        'Shipboard Safety Officer Training',
    ];

    /** Our certificate type names that stand for a row on the form, where the wording differs. */
    private const ALIASES = [
        'Survival Craft & Rescue Boat' => 'Proficiency in Survival Craft And Rescue Boat',
    ];

    /** At least this many sea service rows are printed, blank ones included. */
    public const SEA_SERVICE_ROWS = 5;

    public function __construct(
        private readonly ErpnextClient $erpnext,
        private readonly CrewDocumentTypes $types,
        private readonly SeaServiceRecord $seaService,
    ) {
    }

    /**
     * Everything the CV view prints.
     *
     * @return array<string, mixed>
     */
    public function data(Crew $crew): array
    {
        $certificates = collect($crew->certificates ?? [])->map(fn ($c) => (array) $c);
        $categories = $this->categories();
        $categoryOf = fn (array $c) => $categories[$c['certificate_type'] ?? ''] ?? 'Other';

        $byType = $certificates->keyBy(fn ($c) => $c['certificate_type'] ?? '');

        return [
            'crew' => $crew,
            'photo' => $this->photo($crew->image),
            'passport' => $byType->get('Passport', []),
            'seamanBook' => $byType->get('Seaman Book', []),
            'competency' => $certificates->filter(fn ($c) => $categoryOf($c) === 'Competency')->values()->all(),
            'proficiency' => $this->proficiencyRows($certificates->filter(fn ($c) => in_array($categoryOf($c), ['Proficiency', 'Training'], true))),
            'seaService' => $this->seaService->for((string) $crew->id),
        ];
    }

    /**
     * The form's fixed proficiency rows, filled from the crew's certificates, then any
     * proficiency or training certificate the form has no row for.
     *
     * @param  Collection<int, array<string, mixed>>  $certificates
     * @return array<int, array{name: string, certificate: ?array<string, mixed>}>
     */
    private function proficiencyRows(Collection $certificates): array
    {
        $byRow = $certificates->keyBy(fn ($c) => self::ALIASES[$c['certificate_type']] ?? $c['certificate_type']);

        $rows = collect(self::PROFICIENCY_ROWS)
            ->map(fn ($name) => ['name' => $name, 'certificate' => $byRow->get($name)]);

        $extra = $byRow->except(self::PROFICIENCY_ROWS)
            ->map(fn ($c, $name) => ['name' => $name, 'certificate' => $c])->values();

        return $rows->concat($extra)->all();
    }

    /**
     * Certificate type → category, from the master in ERP HPY, or the shipped list
     * when the master is not there (or not reachable).
     *
     * @return array<string, string>
     */
    private function categories(): array
    {
        $rows = rescue(fn () => $this->types->rows(), [], report: false);

        $fromMaster = collect($rows)->pluck('category', 'name')->filter()->all();

        return $fromMaster + SyncCrewFields::CERTIFICATE_CATEGORIES;
    }

    /** The photo as a data URI the PDF renderer can embed, or null when there is none to show. */
    private function photo(?string $fileUrl): ?string
    {
        if (blank($fileUrl)) {
            return null;
        }

        try {
            $file = $this->erpnext->download($fileUrl);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if ($file['contents'] === '' || ! str_starts_with($file['content_type'], 'image/')) {
            return null;
        }

        return 'data:'.$file['content_type'].';base64,'.base64_encode($file['contents']);
    }
}

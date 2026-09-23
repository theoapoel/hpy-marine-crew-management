<?php

namespace App\Support;

use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextOptions;

/**
 * The COC types a candidate can hold. They live in ERP HPY as records of the
 * "COC Type" doctype (Crew Candidate.coc_type links to it), so a new one is added
 * there or from the candidate form — not by editing code. Until that doctype exists
 * (erp:sync-candidate-doctype not run yet) or while ERP HPY cannot be reached, the
 * list the app shipped with stands in.
 */
class CocTypes
{
    public const DOCTYPE = 'COC Type';

    private const PARENT = 'Crew Candidate';

    public function __construct(private readonly ErpnextOptions $options)
    {
    }

    /** @return array<int, string> */
    public function all(): array
    {
        $types = rescue(fn () => $this->options->fieldOptions(self::PARENT, 'coc_type'), [], report: false);

        return $types ?: CrewCandidate::COC_TYPES;
    }

    /** Whether new types can be added as records (the field is a Link to the master). */
    public function editable(): bool
    {
        return rescue(fn () => $this->options->linkTarget(self::PARENT, 'coc_type') === self::DOCTYPE, false, report: false);
    }
}

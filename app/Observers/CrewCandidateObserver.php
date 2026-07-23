<?php

namespace App\Observers;

use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Support\Str;

/**
 * Fills the bookkeeping a form should not have to carry: uuid, candidate code, and who
 * touched the record (the ERP HPY user of the session — there is no local user table).
 */
class CrewCandidateObserver
{
    public function creating(CrewCandidate $candidate): void
    {
        $candidate->uuid ??= (string) Str::uuid();
        $candidate->candidate_code ??= CrewCandidate::nextCode();
        $candidate->company ??= app(ErpnextClient::class)->company();
        $candidate->created_by ??= ErpUser::id();
        $candidate->updated_by ??= ErpUser::id();
    }

    public function updating(CrewCandidate $candidate): void
    {
        $candidate->updated_by = ErpUser::id() ?: $candidate->updated_by;
    }
}

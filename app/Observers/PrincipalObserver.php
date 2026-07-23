<?php

namespace App\Observers;

use App\Models\Principal;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Support\Str;

/** uuid, running code, owning company and who touched it — none of it the form's job. */
class PrincipalObserver
{
    public function creating(Principal $principal): void
    {
        $principal->uuid ??= (string) Str::uuid();
        $principal->principal_code ??= Principal::nextCode();
        $principal->company ??= app(ErpnextClient::class)->company();
        $principal->created_by ??= ErpUser::id();
        $principal->updated_by ??= ErpUser::id();
    }

    public function updating(Principal $principal): void
    {
        $principal->updated_by = ErpUser::id() ?: $principal->updated_by;

        // Any edit puts the ERP HPY copy out of date until it is pushed again.
        if ($principal->isDirty() && ! $principal->isDirty(['erpnext_sync_status', 'erpnext_synced_at', 'erpnext_name', 'erpnext_hash', 'erpnext_sync_error'])) {
            $principal->erpnext_sync_status = 'pending';
        }
    }
}

<?php

namespace App\Observers;

use App\Models\CrewApplication;
use App\Models\CrewAssignment;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Same bookkeeping as the Candidate Pool: uuid, running code and who touched it.
 * Serves both recruitment models — they only differ in the code series.
 */
class RecruitmentObserver
{
    public function creating(Model $model): void
    {
        $model->uuid ??= (string) Str::uuid();
        $model->company ??= app(ErpnextClient::class)->company();
        $model->created_by ??= ErpUser::id();
        $model->updated_by ??= ErpUser::id();

        if ($model instanceof CrewApplication) {
            $model->application_code ??= CrewApplication::nextCode();
            $model->applied_date ??= now()->toDateString();
        }

        if ($model instanceof CrewAssignment) {
            $model->assignment_code ??= CrewAssignment::nextCode();
        }
    }

    public function updating(Model $model): void
    {
        $model->updated_by = ErpUser::id() ?: $model->updated_by;
    }

    /**
     * A posting only means something once ERP HPY knows about it, so any change to the
     * crew, ship or state of an assignment is mirrored onto the Employee record —
     * whether it came from the form, the sign on desk or a hire.
     */
    public function saved(Model $model): void
    {
        if ($model instanceof CrewAssignment && $model->wasChanged(['status', 'vessel', 'employee_id', 'sign_on_date', 'planned_sign_off_date'])) {
            $model->syncToErp();
        }
    }

    public function created(Model $model): void
    {
        if ($model instanceof CrewAssignment) {
            $model->syncToErp();
        }
    }
}

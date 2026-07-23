<?php

namespace App\Policies;

use App\Support\ErpUser;

/**
 * Who may touch the Candidate Pool, decided by the ERP HPY roles of the session.
 *
 * Admin / HR: everything.
 * Crewing Officer: create, view and update — but never delete.
 *
 * Registered as gates in AppServiceProvider, since this app has no local user model:
 * the "user" is the ERP HPY session (see App\Support\ErpUser).
 */
class CrewCandidatePolicy
{
    /** Roles that may do anything, including deleting. */
    public const MANAGER_ROLES = ['System Manager', 'HR Manager', 'Administrator'];

    /** Roles that may work the pool day to day. */
    public const OFFICER_ROLES = ['HR User', 'Crewing Officer', 'Employee'];

    public function viewAny(): bool
    {
        return $this->isManager() || $this->isOfficer();
    }

    public function view(): bool
    {
        return $this->viewAny();
    }

    public function create(): bool
    {
        return $this->isManager() || $this->isOfficer();
    }

    public function update(): bool
    {
        return $this->isManager() || $this->isOfficer();
    }

    public function delete(): bool
    {
        return $this->isManager();
    }

    protected function isManager(): bool
    {
        // The ERP HPY Administrator carries no role rows but may do everything.
        return ErpUser::id() === 'Administrator' || ErpUser::hasAnyRole(...self::MANAGER_ROLES);
    }

    protected function isOfficer(): bool
    {
        return ErpUser::hasAnyRole(...self::OFFICER_ROLES);
    }
}

<?php

namespace App\Exports;

use App\Models\CrewAssignment;

/**
 * Crew Assignment as an Excel workbook: one row per berth, with the planned and
 * actual sign on / sign off dates side by side.
 */
class CrewAssignmentExport extends XlsxExport
{
    protected function columns(): array
    {
        return [
            'Code' => 'assignment_code',
            'Crew' => 'crew_name',
            'Employee ID' => 'employee_id',
            'Candidate Code' => 'candidate_code',
            'Vessel' => 'vessel',
            'Rank' => 'rank',
            'Status' => 'status_label',
            'Planned Sign On' => 'planned_sign_on_date',
            'Sign On Date' => 'sign_on_date',
            'Sign On Port' => 'sign_on_port',
            'Contract Months' => 'contract_months',
            'Planned Sign Off' => 'planned_sign_off_date',
            'Sign Off Date' => 'sign_off_date',
            'Sign Off Port' => 'sign_off_port',
            'Sign Off Reason' => 'sign_off_reason',
            'Days Onboard' => 'days_onboard',
            'Wage' => 'wage',
            'Currency' => 'wage_currency',
            'Notes' => 'notes',
            'Company' => 'company',
            'Created' => 'created_at',
        ];
    }

    protected function dates(): array
    {
        return ['planned_sign_on_date', 'sign_on_date', 'planned_sign_off_date', 'sign_off_date', 'created_at'];
    }

    protected function numbers(): array
    {
        return ['contract_months', 'days_onboard', 'wage'];
    }

    protected function widths(): array
    {
        return [1 => 16, 2 => 28, 5 => 22, 19 => 40]; // Code, Crew, Vessel, Notes
    }

    protected function sheetName(): string
    {
        return 'Assignments';
    }

    protected function value(object $record, string $attribute): mixed
    {
        /** @var CrewAssignment $record */
        return match ($attribute) {
            'status_label' => ucwords(str_replace('_', ' ', (string) $record->status)),
            'candidate_code' => $record->candidate?->candidate_code,
            default => parent::value($record, $attribute),
        };
    }
}

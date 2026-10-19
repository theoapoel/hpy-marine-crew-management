<?php

namespace App\Exports;

/**
 * The crew database as an Excel workbook: one row per crew member, the same columns
 * whichever backend (ERP HPY or the local placeholder) supplied them.
 */
class CrewExport extends XlsxExport
{
    protected function columns(): array
    {
        return [
            'Employee ID' => 'id',
            'Name' => 'name',
            'Rank' => 'rank',
            'Department' => 'department',
            'Crew Status' => 'status',
            'Vessel' => 'vessel',
            'Sign On Date' => 'sign_on_date',
            'Contract End' => 'contract_end_date',
            'Nationality' => 'nationality',
            'Gender' => 'gender',
            'Date of Birth' => 'date_of_birth',
            'Seaman Book No' => 'seaman_book_no',
            'Seaman Book Expiry' => 'seaman_book_expiry',
            'Passport No' => 'passport_number',
            'Passport Expiry' => 'valid_upto',
            'Phone' => 'cell_number',
            'Email' => 'personal_email',
            'Employee Number' => 'employee_number',
            'Date of Joining' => 'date_of_joining',
            'Last Salary' => 'last_salary',
            'Currency' => 'last_salary_currency',
            'Employee Status' => 'employee_status',
            'Company' => 'company',
        ];
    }

    protected function dates(): array
    {
        return [
            'sign_on_date', 'contract_end_date', 'date_of_birth', 'seaman_book_expiry',
            'valid_upto', 'date_of_joining',
        ];
    }

    protected function numbers(): array
    {
        return ['last_salary'];
    }

    protected function widths(): array
    {
        return [2 => 28, 6 => 22]; // Name, Vessel
    }

    protected function sheetName(): string
    {
        return 'Crew';
    }

    protected function value(object $record, string $attribute): mixed
    {
        $value = parent::value($record, $attribute);

        // The local placeholder links a Vessel row; ERP HPY gives the vessel's name.
        if ($attribute === 'vessel' && is_object($value)) {
            return $value->name ?? null;
        }

        return $value;
    }
}

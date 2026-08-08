<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vessel Profitability
    |--------------------------------------------------------------------------
    |
    | A vessel earns and spends through Projects in ERP HPY — one Project per
    | contract, charter or voyage, tied to the ship by the custom Project.vessel
    | field (php artisan erp:sync-project-fields).
    |
    | The figures are not read off Project's own costing fields: they are added up
    | from the documents themselves, so every number can be opened and traced back
    | to the invoice or journal entry behind it.
    |
    |   revenue  Sales Invoice Item     grouped by item group
    |   cost     Purchase Invoice Item  grouped by item group
    |   cost     Journal Entry Account  grouped by account — this is where crew
    |                                   cost is allocated for now
    |
    | Only submitted (docstatus 1) documents count, and amounts are taken in the
    | company currency so a USD charter and an IDR repair bill can be added up.
    |
    */

    /**
     * Item group on an invoice line -> component. Matched case-insensitively; a
     * key also matches when it appears inside the group name ("Bunker Fuel" -> Bunker).
     */
    'item_groups' => [
        'charter hire' => 'Charter Hire',
        'freight' => 'Charter Hire',
        'manning' => 'Manning Fee',
        'management fee' => 'Manning Fee',
        'bunker' => 'Bunker',
        'fuel' => 'Bunker',
        'lubricant' => 'Bunker',
        'docking' => 'Docking & Repair',
        'repair' => 'Docking & Repair',
        'spare' => 'Docking & Repair',
        'maintenance' => 'Docking & Repair',
        'port' => 'Port Charges',
        'agency' => 'Port Charges',
        'insurance' => 'Insurance',
        'provision' => 'Provision & Stores',
        'stores' => 'Provision & Stores',
        'crew' => 'Crew Cost',
    ],

    /**
     * Account name on a Journal Entry line -> component. Matched the same way, on
     * the account name minus its company abbreviation.
     *
     * Order matters and the specific keywords come first: "Crew Travel" has to reach
     * Crew Travel, not be swallowed by the bare "crew" that catches everything else.
     */
    'accounts' => [
        'travel' => 'Crew Travel',
        'repatriation' => 'Crew Travel',
        'ticket' => 'Crew Travel',
        'medical' => 'Crew Medical',
        'training' => 'Crew Training',
        'insurance' => 'Insurance',
        'bunker' => 'Bunker',
        'fuel' => 'Bunker',
        'docking' => 'Docking & Repair',
        'repair' => 'Docking & Repair',
        'salary' => 'Crew Cost',
        'salaries' => 'Crew Cost',
        'wages' => 'Crew Cost',
        'payroll' => 'Crew Cost',
        'allowance' => 'Crew Cost',
        'overtime' => 'Crew Cost',
        'crew' => 'Crew Cost',
    ],

    /** Where a line lands when nothing above matches. */
    'defaults' => [
        'revenue' => 'Other Revenue',
        'cost' => 'Other Cost',
        // Journal entries are the crew cost channel today, so an unmapped account
        // is far more likely to be crew than anything else.
        'journal' => 'Crew Cost',
    ],

    /** Components listed in this order; anything else follows, biggest first. */
    'order' => [
        'Charter Hire', 'Manning Fee', 'Other Revenue',
        'Crew Cost', 'Crew Travel', 'Crew Medical', 'Crew Training',
        'Bunker', 'Port Charges', 'Docking & Repair', 'Provision & Stores',
        'Insurance', 'Other Cost',
    ],

];

# ERP HPY Integration

The app authenticates against ERP HPY (`ERPNEXT_URL`) and treats it as the system of
record for crew, vessels and their documents. Modules that carry our own commercial
data — the Candidate Pool, recruitment, assignments, principals — live in the local
database and are mirrored into ERP HPY where a link is useful.

Everything is scoped to the company picked in the header, both locally (a `company`
column plus the `ofCompany()` scope) and in ERP HPY (a `["company","=",…]` filter).

## Custom doctypes this app creates

All of them are created in the **HR** module, where the instance already keeps its
marine doctypes (Vessel, Rank, Vessel Certificate). Each command is idempotent.

| Command | Creates |
| --- | --- |
| `php artisan erp:sync-crew-fields` | `Employee Certificate` child table + the marine custom fields on `Employee` |
| `php artisan erp:sync-ranks` | Seafarer ranks in the `Rank` doctype |
| `php artisan erp:sync-candidate-doctype` | `Crew Candidate` + `Crew Candidate COP` |
| `php artisan erp:sync-vessel-types` | `Vessel Type` + `Vessel Certificate Type`, and re-points the fields at them |
| `php artisan erp:sync-principal-doctype` | `Principal` + `Principal Contact Person` + `Vessel.principal` |
| `php artisan erp:sync-project-fields` | `Project.vessel` — the link Vessel Profitability reads through |

Sample data: `erp:seed-vessels`, `erp:seed-crew-documents`, `erp:seed-vessel-profitability`.

## Vessel Profitability

A vessel earns and spends through **Projects** — one per contract, charter or voyage,
so one ship has many projects — tied to the ship by the custom `Project.vessel` link.

The figures are not read off Project's own costing fields. They are added up from the
documents, so every number opens down to the invoice or journal entry behind it:

| Source | Side | Component from |
| --- | --- | --- |
| `Sales Invoice Item` | revenue | item group |
| `Purchase Invoice Item` | cost | item group |
| `Journal Entry Account` | cost | account — **crew cost is allocated this way for now** |

Two rules keep the totals honest: only submitted documents count (`docstatus = 1`), and
amounts are read in company currency (`base_net_amount`, `debit`/`credit`) so a USD
charter and an IDR repair bill can be added together.

The keyword → component mapping lives in `config/profitability.php`; changing how a
yard bill is classified is a config edit, not a code change.

Sample data, for a demo or a fresh instance:

```bash
php artisan erp:sync-project-fields
php artisan erp:seed-vessel-profitability          # 2 vessels, 2 charters each
php artisan erp:seed-vessel-profitability --reset  # cancel the samples, then re-post
```

It posts **submitted** accounting documents, which reach the general ledger and can
only be cancelled, never cleanly deleted — which is why it is a command you run on
purpose. It also adds crew expense accounts when the chart of accounts has none.

## Import Principal Module

The doctypes are created directly by the command above. The same definitions are also
written as importable JSON, for review or for standing up another instance by hand:

| File | What it is |
| --- | --- |
| `storage/app/erpnext/doctypes/principal_contact_person.json` | child table (import **first**) |
| `storage/app/erpnext/doctypes/principal.json` | the Principal doctype |
| `storage/app/erpnext/custom_fields/vessel_principal_field.json` | the `principal` Link on Vessel |

Steps:

1. Import **Principal Contact Person** — the parent references it, so it has to exist first.
2. Import **Principal**.
3. Import the **Custom Field** `vessel_principal_field`.
4. Push the local records: `php artisan erpnext:sync Principal --pending`

To regenerate the JSON without touching the live instance:

```bash
php artisan erp:sync-principal-doctype --json-only
```

## How syncing works

Principals are pushed on every save. The row records the outcome
(`erpnext_sync_status`, `erpnext_name`, `erpnext_synced_at`, `erpnext_sync_error`,
`erpnext_hash`), which the list and detail pages show through
`<x-erpnext-sync-badge :model="$principal" />`. Anything that failed — ERP HPY down,
validation refused — stays `pending`/`failed` and is picked up by:

```bash
php artisan erpnext:sync Principal --pending
php artisan erpnext:sync Principal --code=PRN-0001   # one record
```

Crew, vessels and documents are not queued at all: they are read from and written to
ERP HPY directly, so there is nothing to catch up on.

## Accounting

General Ledger, Trial Balance, Balance Sheet and Profit & Loss are **ERP HPY's own
query reports**, run through `frappe.desk.query_report.run` and rendered as they came
back. Nothing is recalculated here: a ledger this app worked out for itself could
disagree with the ERP desk, and when two systems disagree about money neither is
trusted.

`App\Services\Erpnext\FinancialReports` owns the awkward part of that bargain. A query
report answers in whichever shape its author chose — columns as dicts or as
`"Label:Currency:120"` strings, rows as dicts or as bare lists, total rows marked by
wrapping the label in single quotes — and it normalises all of that into one shape
before the view sees it. Adding a fifth report is an entry in `FinancialReports::REPORTS`
plus, if it needs unusual filters, a branch in `filters()`.

## Files in ERP HPY

Uploads land in ERP HPY's private folder, which only answers to a logged-in ERP HPY
session. The browser has no such cookie, so every file is served through
`route('erp.file', ['path' => $fileUrl])`, which fetches it with the session's own
credentials.

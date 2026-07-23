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

Sample data: `erp:seed-vessels`, `erp:seed-crew-documents`.

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

## Files in ERP HPY

Uploads land in ERP HPY's private folder, which only answers to a logged-in ERP HPY
session. The browser has no such cookie, so every file is served through
`route('erp.file', ['path' => $fileUrl])`, which fetches it with the session's own
credentials.

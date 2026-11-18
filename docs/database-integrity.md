# Database Integrity and Retention

Phase 3 adds an additive integrity layer for attendance, leave, schedules, and
academic reference data. It is designed to be run in two schema stages:

1. Deploy the staging migration:

   ```bash
   php artisan migrate --path=database/migrations/2026_09_02_000000_add_integrity_staging_fields.php --force
   ```

2. Generate and review an external report:

   ```bash
   php artisan integrity:report --format=json --output=/secure/iqams/integrity-report.json
   php artisan integrity:reconcile --dry-run
   ```

3. Create a reviewed manifest from the report. The manifest contains only
   database IDs and decisions; it must not contain passwords, tokens, QR
   values, or secret material.

4. Apply the reviewed decisions:

   ```bash
   php artisan integrity:reconcile --apply --manifest=/secure/iqams/integrity-manifest.json
   ```

5. Confirm the report is clean, then enable the final constraints migration:

   ```bash
   php artisan migrate --path=database/migrations/2026_09_02_000001_add_integrity_constraints.php --force
   ```

The reconciliation apply gate refuses to proceed while invalid schedule times,
orphaned relationships, or unresolved placement decisions remain. The final
migration refuses to run when canonical attendance rows, active sections, or
active schedules have not been backfilled; unique and foreign-key creation will
also fail safely if the reviewed preflight state has changed in the meantime.

## Manifest shape

The following is the supported shape. Empty arrays are valid when that category
has no exceptions:

```json
{
  "attendance": [
    {
      "ids": [101, 102],
      "canonical_id": 102,
      "superseded_ids": [101]
    }
  ],
  "leave": [
    {
      "ids": [201, 202],
      "keep_id": 201,
      "resolve_ids": [202]
    }
  ],
  "placements": [
    {
      "student_id": 301,
      "section_id": null
    }
  ],
  "sections": [
    {
      "ids": [401, 402],
      "canonical_id": 401,
      "archive_ids": [402]
    }
  ],
  "schedules": [
    {
      "ids": [501, 502],
      "canonical_id": 501,
      "archive_ids": [502]
    }
  ]
}
```

Attendance duplicates retain every row. The latest correction is canonical,
with latest `updated_at`, then latest `scan_time`, then lowest ID as tie-breaker.
Leave conflicts retain all rows; non-retained pending requests become rejected
and non-retained approved requests become cancelled only through the reviewed
manifest.

## Retention behavior

Accounts are deactivated. Departments, courses, sections, subjects, schedules,
and school events are archived or cancelled. Attendance, leave, QR history,
scan audits, and audit logs are never physically deleted by the application.
No automated purge job is installed. Any future retention purge requires a
separate approved institutional policy and migration.

General audit logs reject Eloquent instance and common bulk modifications,
but direct query-builder/SQL access, privileged database operations, foreign-key
actions and migration rollback can still alter history. The hosting review,
privilege separation, attribution limitations and recovery procedure are in
`docs/audit-database-protection.md`; model guards do not establish database-level
immutability. Production grants and hosting support remain unverified.

Scan audits include the request IP address, user agent, terminal location and
other event details. Treat these as potentially identifying security records:
restrict access to authorized personnel, include their collection and security
purpose in the institutional privacy notice, and protect exports and backups.
The repository does not specify an approved retention period for these fields;
they currently remain with the scan audit, including in backups, until an
institutional schedule and a reviewed disposal procedure are approved. Do not
apply the backup rotation schedule as a retention period for live audit data.

Scan audits are not currently protected by model-level append-only guards or
verified database-level immutability. Restrict database write access and do
not describe them as immutable. IP addresses can be shared or affected by
proxies and are not conclusive proof of identity or location; configure trusted
proxies for the actual deployment and verify the recorded address.

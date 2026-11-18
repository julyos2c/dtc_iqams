# Audit database protection and operations

The general `audit_logs` table is written by `AuditLogger` and read by the
audit, QR management and scanner-security screens. The application has no
audit cleanup command or update/delete route. Model hooks reject instance
updates and deletes; `AuditLogBuilder` rejects common Eloquent bulk mutation
methods. These are application safeguards, not a database security boundary:
`DB::table`, raw SQL, `toBase()`, event suppression, foreign-key actions,
schema operations and privileged users can bypass them. They do not prove
tamper resistance or protect the separate `attendance_scan_audits` table.

## Attribution

Normal account removal deactivates the user, and the User model rejects
instance deletion. Existing actor references therefore remain available.
`audit_logs.actor_id` is nullable with `ON DELETE SET NULL`: a direct or bulk
user deletion can leave an audit event without attribution, and a null actor
is displayed as System / Console even if it was originally a deleted user.
Deactivation is the current preservation strategy. Do not hard-delete users
through SQL, bulk Eloquent or other bypasses. A future removal requirement
needs an approved minimal attribution design and an assessment of existing
records and foreign keys before any migration. Historical null actor IDs cannot
be reliably reconstructed from the audit row alone.

## Hosting administrator procedure

Confirm the hosting provider's MySQL/MariaDB version, control-panel support,
effective grants, role inheritance and available administrative accounts.
Do not assume local privileges match production. Account/privilege changes
are performed only by the hosting administrator under an approved change.

1. Take and verify an encrypted backup; rehearse the process on an isolated
   production-like database. Identify every app process and database account,
   including web, queues, scheduler and migration jobs.
2. Where supported, give the runtime account only `SELECT` and `INSERT` on
   `audit_logs`, while retaining only the required privileges on other tables.
   Database- or global-level grants and inherited roles are additive: a
   table-level restriction does not override a broader `UPDATE`, `DELETE`,
   `DROP` or `ALTER` grant. Review all effective grants, including roles and
   wildcard database grants. Consider other modification capabilities such
   as `TRIGGER` and privileges that permit changing grants or using powerful
   routines. Do not rely on a table-only REVOKE to negate broader privileges.
3. Use a separate, controlled migration/maintenance identity for schema work,
   not runtime credentials. Provision it outside the repository and restrict
   its use to approved change windows. The current Laravel configuration uses
   the default connection for both runtime and migrations; supplying a
   separate identity is an operations responsibility, not yet automated.
4. In the isolated environment, verify actual effective privileges using the
   runtime identity: audit insert and select succeed; update, delete, truncate,
   alter and drop fail. Exercise web, queue, scheduler, QR and audit screens,
   and verify migrations with the separate identity. Test account deletion and
   foreign-key behavior separately: server-driven `SET NULL` may change rows
   despite restricted direct privileges. Never run mutation probes against
   production records. Record server version, identity, grants and results.
5. Obtain approval, apply the provider-specific configuration and independently
   review effective privileges and application health. Do not put credentials,
   SQL grant scripts containing credentials, or production data in the repo.

`tests/Integration/AuditDatabasePrivilegesTest.php` is an opt-in privilege
probe, excluded from the default suite. It connects only to
`127.0.0.1:13318/iqams_audit_privilege_test` and intentionally attempts
destructive statements, so that endpoint must be a disposable server and
database containing only synthetic data. The test does not create users, grant
privileges or provision the schema. An administrator must create the test
schema and a restricted test identity in that disposable environment first.
Set `IQAMS_AUDIT_PRIVILEGE_TEST=1`, `IQAMS_AUDIT_TEST_USER`, and
`IQAMS_AUDIT_TEST_PASSWORD` in the test process, then run the integration file
explicitly. The probe writes a synthetic row and cannot clean it up with the
restricted identity; discard the disposable server afterward. It is not a
substitute for reviewing actual production grants or provider behavior.

Some shared hosts grant database-wide privileges and do not support a separate
restricted account. If effective table restrictions are unavailable, record
the limitation and use a separately administered, access-controlled remote
append-only audit sink or independently protected audit exports/backups, with
monitoring and periodic restore verification. A copied log is not automatically
immutable or complete; establish delivery, access, retention and reconciliation
controls before relying on it. No external sink is implemented here.

## Migration, rollback and recovery

The historical audit migration's `down()` drops `audit_logs`. Do not run
`migrate:rollback`, `migrate:reset`, `migrate:refresh`, `migrate:fresh`, or
equivalent destructive schema commands against production. Review migration
status and SQL/change scope in staging before deploying additive migrations.
Use a dedicated migration account only in an approved window and remove its
access from routine runtime processes. Rehearse rollback of code/configuration
without reverting audit schema; take verified backups before schema changes.

For an incident or approved maintenance, pause relevant writes, preserve an
encrypted snapshot and evidence, record authorization and operator, and use
the controlled maintenance identity under a documented change. Restore only
into an isolated database first, check audit row counts and attribution, and
reconcile any events created after the backup before considering production
recovery. A restore can otherwise erase later audit history. Record the outcome
and independently verify it. No audit purge or retention cleanup is authorized
by this procedure; retention remains subject to an institutional policy.

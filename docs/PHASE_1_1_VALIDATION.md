# Phase 1.1 — Validation and Go-Live Runbook

## Status and scope

This runbook is for a staging copy of the SPMB platform. **Passing automated
tests is necessary, but not sufficient, for production approval.** Never run a
destructive development reset or staff credential rotation against production.

The business decision for Virtual Accounts is **manual verification of uploaded
proof of transfer by authorized Admin Unit / TU staff**. No automatic bank
confirmation, bank API integration, or callback is required in Phase 1.

## Read-only deployment preflight

After deploying the exact commit into a **separate staging environment**, run:

```bash
php artisan spmb:release:preflight --profile=staging
php artisan spmb:release:preflight --profile=staging --json
```

For a planned production deployment, use `--profile=production` on the
intended production host **before activating the release**. The command
performs read-only checks: application key and debug configuration, HTTPS,
private applicant storage, secure sessions, background queue driver, mail
configuration, migration status, database connectivity, production reset
lockout, and upload scanner settings. It prints no credentials or personal
data and cannot send a test email, start a worker, or verify backup integrity.

A nonzero exit code **blocks approval**, but a zero exit code means only
that automatic checks passed. The manual checklist below remains mandatory.
Operational values (email delivery, queue supervision, backup restoration,
UAT with actual roles and manual transfer-proof verification) still require
human evidence.

## CI staging rehearsal versus real staging

The `mysql-integration` CI job additionally runs the read-only staging
preflight against its **ephemeral, synthetic MySQL database** and stores the
JSON output as the `staging-preflight-synthetic` GitHub Actions artifact
(retained for 14 days). The rehearsal uses synthetic HTTPS, SMTP and scanner
settings. It verifies application configuration behavior and migration status
only; it **does not** verify real SMTP delivery, ClamAV execution, private-file
backup restoration, actual queue workers, an internet-facing HTTPS endpoint,
or user acceptance tests. A green CI artifact is not a staging go-live
approval.

Keep the CI artifact and the **separate real staging preflight output**
attached to the release acceptance record. Never paste environment secrets
or identifiable applicant data into GitHub issues or CI artifacts.

## Queue and scheduler operator checks

The application uses named `emails` and `notifications` queues in addition
to the default queue. A worker consuming only the default queue does **not**
process those named queues. Verify the supervisor-managed worker processes
the configured names, for example:

```bash
# Example only: verify your deployment's queue driver and retry_after.
php artisan queue:work --queue=emails,notifications,default \
  --sleep=3 --tries=3 --timeout=60 --max-time=3600

# Read-only operational inspection:
php artisan queue:failed
php artisan schedule:list
```

Use an external process supervisor to restart workers; do not run a second
unmanaged worker alongside production. Worker `--timeout` must be **shorter**
than the queue connection's `retry_after` (90 seconds by default) to reduce
duplicate processing. Verify the deployment has a scheduler trigger (typically
`* * * * * php artisan schedule:run` under the correct application user),
and inspect its real scheduler logs. The preflight command cannot certify that
a worker or cron is actually alive.

## Automated gates

- [ ] GitHub Actions on the exact release commit is green (Laravel suite and MySQL 8 integration).
- [ ] The repository uses committed `composer.lock` and CI has read-only permissions.
- [ ] The branch and commit deployed to staging match the tested commit.
- [ ] Staging uses its own database, file store, queues, sessions, email destination, and credentials.
- [ ] Realistic MySQL/MariaDB migrations and rollback tests pass with foreign keys enabled.

## Permission / tenant isolation acceptance

Create two separate operational units (Unit A and Unit B) and staff roles
super_admin, admin_unit(A), tu(A), and admin_unit(B).

- [ ] Admin Unit A cannot read/edit/export/verify Unit B registrations, documents, payments, selection decisions, tests, or offers, including direct URLs.
- [ ] TU A has operational permissions for Unit A only and cannot change registration configuration for either unit.
- [ ] Admin Unit A can configure Unit A but not Unit B; Super Admin can configure both subject to permissions.
- [ ] Bulk actions use record-level authorization and do not affect unauthorized or ineligible records.
- [ ] An inactive unit or disabled staff account is rejected by authenticated routes and service operations.
- [ ] Unauthorized access leaves record status, files, and audit events unchanged.

## Applicant and payment user acceptance

- [ ] Applicant registers, selects an opening/pathway, completes consents, and uploads documents.
- [ ] Staff validates registration and assigns an available VA for the correct unit/program.
- [ ] Applicant uploads proof of transfer; file type/signature, access and privacy checks succeed.
- [ ] Authorized Unit A staff manually compares the uploaded transfer proof and the expected VA/nominal and either approves or rejects with a reason.
- [ ] Rejection returns the applicant to the correct stage; approval issues registration number/receipt exactly once.
- [ ] Second staff member cannot double-verify or mutate a finalized payment unexpectedly.
- [ ] Test scheduling, selection, announcement, offer, and re-registration remain functional.
- [ ] No payment API/webhook or automatic bank confirmation is assumed.

## Historical audit privacy

Preview only (safe on the selected environment):

```bash
php artisan spmb:audit:review
```

**A review is not automatic permission to change historical evidence.** After
legal/retention review and a tested backup, an authorized operator may apply
JSON payload redaction with an interactive confirmation:

```bash
php artisan spmb:audit:review --execute --backup-confirmed
```

- [ ] Confirm log identifiers, actor, timestamps, and event types survive.
- [ ] Confirm masked JSON fields no longer disclose participant identifiers.
- [ ] Investigate historical free-text descriptions, request paths, IP addresses, user agents, backups, and exported reports **separately**.
- [ ] Record approval, ticket, backup ID and operator in the change record.
- [ ] Keep a secure immutable backup for restoration requirements and policy compliance.

## Development data reset validation

**Use an isolated database and storage copy with synthetic data only.** The
reset must not be enabled by changing `APP_ENV` on a production-connected
deployment. First inspect the connection and data counts:

```bash
php artisan spmb:reset-operational
```

- [ ] The operator verifies DB host/name and isolated private/public file roots, queues, and backup restore.
- [ ] Assigned/paid VA causes reset to refuse execution; never force-clear VA status.
- [ ] Run the success case only on test data with no assigned/paid VA, after a restored backup is verified.
- [ ] Compare unit, openings, pathways, study programs, versioned config, form/test definitions, schedules, quotas, staff accounts, roles, permissions, and VA pool before/after.
- [ ] Operational registrations, applicant accounts, documents, payment/test/selection activity and abandoned scoped uploads are gone.
- [ ] Unrelated files and accounts are untouched.
- [ ] Snapshot mismatch or unexpected FK prevents partial DB commits.
- [ ] Review the local reset manifest and verify that file cleanup finished; test a retry from the manifest.
- [ ] Run `spmb:audit:review` on remaining historical audit logs and apply the approved retention policy.
- [ ] Ensure background queue workers are stopped/isolated during reset so old applicant jobs do not run afterward.

## Staff session and recovery

- [ ] Confirm staff email/password recovery works before password rotation.
- [ ] Password hardening preview makes no changes.
- [ ] A rotation on synthetic staff credentials invalidates old password, remember tokens, and old session generation.
- [ ] Test sessions backed by the deployment's actual session driver (Redis/file/database).
- [ ] No replacement passwords are printed, emailed in cleartext, or logged.

## Deployment approval (manual)

Record an explicit go/no-go decision containing release SHA, successful CI run,
staging URL, environment profile, UAT operator, verification date, database and
file-backup restore evidence, unresolved findings, and rollback owner.

**Automation does not run this staging acceptance process.** The checklist
remains open until an operator validates it on the real deployment topology.

# Instructor registration and approved enrollment

The registrar remains outside IQAMS. Admins prepare academic records and assignments. Instructors open **My Teaching Schedule** and select **Manage Students** on the assigned subject card. They add existing students or register new ones, verify that subject's roster, and select **Submit to Admin**. **View Attendance** stays alongside Manage Students on the same card. Each student–offering pair receives a separate admin decision, and all weekly meetings share one subject roster.

There is no separate class-list creation step. **Copy students from another subject** is optional: preview the instructor's other subject roster, select students, and copy them into the target draft. Copying never submits requests, transfers approval, creates accounts, or changes the source. The target roster must be verified and submitted independently.

The existing `class_lists` table stores private subject drafts internally, with one nullable recurring-offering reference and a unique instructor/offering constraint. GET requests never create drafts. Old unbound lists and their submitted history are retained; their students are available under **Previously saved students** in the copy selector. Old list and My Subjects bookmarks redirect to My Teaching Schedule; old list mutation endpoints no longer accept submissions. Request tracking and status filters appear inside each subject's Manage Students page, not in the teaching schedule or attendance view. Identity, review, QR, and attendance services are unchanged.

For an existing installation, apply `2026_11_07_010000_bind_draft_rosters_to_subject_offerings` before opening a subject roster. It adds the offering reference and uniqueness constraint without deleting or assigning old saved lists. The subject-first tests cover direct registration/submission, draft-only copying, per-subject approval, duplicate prevention, private sources, forged selections, and preservation of old students and approved enrollments.

The subject-first revision passed 79 focused PHP tests (440 assertions), all 16 JavaScript tests, and the production build. The local subject-roster migration was applied; approval cutover was not changed.

## Deployment and cutover

1. Back up the database and rehearse against a staging copy. Deploy the application and run `php artisan migrate --force` followed by `php artisan permission:cache-reset` and `npm run build`.
2. Run `php artisan enrollment:enable` for a read-only audit. It reports current regular roster relationships, missing recurring groups, orphaned enrollments, normalized student-number collisions, and mixed offering groups. Resolve all reported integrity issues before applying.
3. Pause web enrollment/academic writes and scheduler/scanner attendance writes. Capture existing regular roster counts and irregular enrollment counts. Run the audit again, then `php artisan enrollment:enable --apply`.
4. The command inserts missing regular-student relationships, preserves existing irregular rows, verifies the backfill, and enables approval-only eligibility in the same transaction. It does not replace QR credentials or change attendance records. Legacy enrollments have no effective-date lower bound. Running it again never enrolls students into subsequently created offerings.
5. Check preserved roster counts, QR identity, historical summaries, instructor submissions, admin approval, and an actual scan before resuming writes. Monitor review failures, absence writer commands, and the existing operations health checks.

The migration deliberately leaves eligibility in legacy mode until cutover. Instructor drafts and submissions are available, but approvals are blocked before cutover. Admin student creation is unavailable in all modes: the creation form is removed and direct creation requests return 403. Student accounts are created only through approval of instructor submissions. Admin profile edits never assign or remove enrollments after cutover. Do not use `migrate:rollback` for this feature; restore the reviewed backup before reopening writes if cutover must be abandoned.

## Decisions and behavior

- First approved enrollment approves registration, creates an active account and initial QR atomically, and displays the existing `Student@{student_no}` temporary password only to the approving admin. First login requires a password change. No email setup link is sent. Refreshing a completed review does not expose the password again; use existing admin password reset if required.
- Student numbers are matched after trimming and uppercasing. Existing students are reused without password/QR changes. Conflicting identity details or an email/username owned by another account block approval; reject with a correction reason. Instructors cannot overwrite canonical profiles. Rejected requests can be corrected and resubmitted as new linked decisions.
- Approvals take effect at the approval timestamp. A student approved during a session may scan immediately, but absence liability begins with the next session. Dated rosters include session-start members and actual recorded attendees. Historical summaries use persisted canonical records, independently of present membership.
- Section and schedule events use explicit relationships after cutover. School-wide events retain their existing all-active-students behavior; accounts created after an event starts are excluded from its absence roster.
- Timetable splits preserve enrollment effective dates. Changing the subject or section of an offering with enrollment/request history is blocked; create a new offering instead. Assignment changes invalidate pending approval from the former instructor.
- Individual and bulk reviews require `review-enrollment-requests` and recent password confirmation. Every successful row commits and audits separately; invalid rows remain pending with a visible error. The review permission is independently revocable for admins.
- No registrar access, spreadsheet import, enrollment removal workflow, or timetable-conflict enforcement is introduced.

## Verification

Run `php vendor/phpunit/phpunit/phpunit --do-not-cache-result --debug tests/Feature/InstructorEnrollmentWorkflowTest.php` and the affected attendance, scanner, event, account, schedule, profile, authorization, and performance suites. `--debug` avoids a terminal-detection stall observed in this Windows environment.

Before production cutover, also run concurrent approvals against a disposable database using the production engine. SQLite feature tests verify idempotency and unique constraints, but cannot establish MySQL row-lock behavior. Never run concurrency tests or destructive test migrations against production.

`tests/Integration/EnrollmentApprovalConcurrencyTest.php` is an opt-in three-worker race covering competing registrations and repeated approval. It requires `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=iqams_enrollment_test`, `DB_HOST=127.0.0.1`, `DB_PORT=13317`, an empty `DB_URL`, and the disposable server's credentials. Supply these variables to both PHPUnit and its subprocess environment. Run the file explicitly with `--do-not-cache-result --debug`; it is excluded from the default SQLite suite and resets only that guarded database.

## Validation evidence

The implementation was tested on 6 November 2026 using SQLite feature tests and an isolated XAMPP MariaDB instance on port 13317. The final focused PHP run passed 44 tests with 203 assertions. The three-worker approval race passed (one account, one QR, two enrollments, two review audit events; 18 assertions). All 16 JavaScript tests and the Vite production build passed. The disposable database server was shut down after validation.

A complete feature/unit run executed 354 tests: 343 passed, 10 failed, and one errored. All 11 unsuccessful cases also reproduced against an isolated `HEAD` baseline, using the original application, views, migrations, and tests. They concern scanner-origin fixtures in `AdminDashboardPerformanceTest`, obsolete personnel dashboard ID-card markup, notification query-count expectations, an optional-avatar expectation, scanner audit table markup, staff dashboard markup, and the obsolete `instructor.history` route in `StaffAttendancePagesTest`. These unrelated baseline issues were not changed by the enrollment workflow. Targeted workflow, recurring-schedule, and authorization checks passed independently.

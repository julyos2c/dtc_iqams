# IQAMS admin workflow

The editable draw.io diagram contains seven pages. Access to modules depends on the listed route permissions. Decision side boxes describe exceptions or alternate paths; the main vertical path continues after applicable checks.

## 01 Access and navigation

- Start
- Open login; enter user ID and password
- Rate limit reached (5 attempts)? ? Yes: wait until lockout expires, then retry
- Credentials valid and account active? ? No: show error; inactive account must contact administrator
- Regenerate session; record successful login
- Password change required? ? Yes: change password before continuing
- Route user by role
- Admin role and view-reports permission? ? No: redirect by role or deny access
- Admin dashboard: view dashboard data and analytics
- Choose a permitted admin module
- Complete work; return to dashboard or another module
- Log out: record logout; invalidate session and regenerate token

## 02 Users and academic records

- Dashboard ? user or academic management
- Required permission granted? ? No: access denied
- Students: search and manage existing profiles, status and assigned course or section
- New student needed? ? Yes: instructor submits registration; admin reviews it on page 03
- Instructors / staff: create, update or deactivate records
- Manage accounts: change active status, reset password, view QR or ID card
- Password reset: eligible active student, instructor or staff receives temporary password; next login requires change
- Academic structure: create, update or archive departments, courses, subjects and sections
- Schedules: create, update or archive class schedules
- Office units and role assignments: manage with respective permissions
- Validate input; confirm password for protected changes; save and record applicable audit
- Return to module or dashboard

## 03 Request reviews

- Dashboard ? requests
- Choose enrollment, leave or class cancellation
- Required review permission granted? ? No: access denied
- Filter and open request; inspect details and available attachment
- Enrollment: select up to 100 requests; choose approve or reject
- Enrollment rejection requires review notes; confirm password
- For approval: check pending state, enrollment workflow, offering and instructor; process each request
- Review result: approved, rejected or unchanged with a reason; resolve conflicts and retry if needed
- Leave: select approve or reject, optionally add notes; confirm password
- Class cancellation: select approve or reject, optionally add notes; confirm password
- Service validates transition and records the decision; show result
- Return to request list

## 04 Attendance and scanner

- Dashboard ? attendance scanner
- Operate-scanner permission granted? ? No: access denied
- Select an active scanner terminal
- Scan QR code
- QR valid and not revoked? ? No: reject and record security audit
- Resolve user and check attendance eligibility
- Attendance already recorded? ? Yes: show existing result; record duplicate audit
- Eligible and active account? ? No: reject and record reason
- Record attendance; update terminal last-used time; record scan audit
- Show person, status, attendance type and recorded time
- Scan another QR or return to dashboard
- Attendance logs: with manage-attendance permission, view, add, update or delete; protected changes require password confirmation

## 05 Reports and security

- Dashboard ? reports or security
- Required permission granted? ? No: access denied
- Daily personnel report: choose date and filters; view results
- Request PDF or XLSX export; generation is queued
- Check export status; owner downloads completed, unexpired file
- Student absences: filter report, inspect student details or export
- Scanner security: inspect terminals, scan audits and security flags
- Register or update terminals; regenerate QR or queue missing-credential batch
- Review flags as reviewed, confirmed or dismissed
- Confirm password for protected security changes; applicable actions are audited
- Audit logs: inspect with view-audit-logs permission
- Return to dashboard

## 06 School events and profile

- Dashboard ? school events or profile
- School events: manage-school-events permission required
- Create event with dates, attendance mode and target scope; saved as draft
- Publish draft before start? ? No: keep draft or edit
- Confirm password; validate targets and attendance conflicts
- Publish event; record audit
- Cancel published event before start and before attendance exists, or archive through delete action
- Profile: view or update account details; confirm password for update or deletion
- Change own password through password settings
- Return to dashboard or log out

Sources: `routes/web.php`, `routes/auth.php`, admin controllers, authentication controllers, and enrollment, leave and cancellation services.

## 07 Student account email

- Instructor submits registration and enrollment; admin approves after the checks pass.
- For a newly created student account, the system creates an active account and QR credential, then attempts to email the registered address.
- The email contains the user ID, temporary password and a link to the login page. The student signs in and must change the password.
- If sending fails, the approval remains in place; the admin sees a warning and arranges a password reset. Existing students do not receive a new-account credentials email for each enrollment approval.

Sources: `app/Services/EnrollmentReviewService.php`, `app/Services/AccountCredentialsSender.php`, and `app/Notifications/AccountCredentials.php`.

# Test Plan — The Gym Laravel Web

## Objective

Verify the user-visible workflows in the agreed one-gym scope, and the added architecture/security/UI criteria: Repository Pattern, Tailwind CSS, responsive browser layouts, Laravel policies, and authenticated sessions.

## Test environments

- Automated: PHPUnit through `php artisan test --compact`; `phpunit.xml` points to the dedicated MySQL database `qly_phonggym_test`, which migrations refresh during tests. Never point it at the populated local preview database.
- Local preview: Laravel serves on `http://127.0.0.1:8000`; `.env` uses the Laragon MySQL database. Never point `RefreshDatabase` tests at the seeded preview database.
- Frontend build: `npm run build` runs Vite and compiles Tailwind CSS 4.
- Manual responsive review: 360px phone, 768px tablet, and 1440px desktop. Check navigation, cards, tables, form labels, validation, focus visibility, and horizontal overflow.

## Verification results — 14 October 2026

- `php artisan test --compact --filter='^(?!.*PasswordRecoveryTest).*$'`: **passed**, 67 tests and 357 assertions. Password recovery tests were excluded at the user's request. PHPUnit uses the dedicated MySQL database `qly_phonggym_test` configured in `phpunit.xml`.
- `npm run build`: **passed**; Vite compiled the Tailwind CSS and application assets.
- `vendor/bin/pint --dirty --format agent`: **passed** for modified PHP files.
- `php artisan migrate:status --no-interaction`: **passed**; all 12 MySQL migrations are marked `Ran`.
- Previous MySQL migration and HTTP smoke checks passed on 4 October 2026. The local preview uses MySQL; do not run destructive refresh commands against it.
- Previous direct browser runs cover key guest, manager, employee, and customer workflows; see the dated cases below. The latest direct QA run is recorded separately. Standard viewport, keyboard/focus, and reduced-motion checks remain.

## Automated feature test cases

| ID | Role / setup | Action | Expected result | Existing coverage |
| --- | --- | --- | --- | --- |
| AUTH-01 | Guest | Open customer-only page | Redirect to sign in | Covered |
| AUTH-02 | New visitor | Register with valid unique identity | Customer is created, authenticated, and redirected; no verification step | Covered |
| AUTH-03 | Customer | Sign in with username/password | Session is authenticated and regenerated | Covered |
| AUTH-04 | Guest with invalid credentials | Exceed the login attempt limit | HTTP 429; no authenticated session | Covered; passed |
| AUTH-05 | Customer | Open staff or manager pages | HTTP 403 | Covered |
| AUTH-06 | Manager | Sign in with a customer-only URL as the intended destination | Redirect to manager dashboard, not a 403 customer page | Covered; passed |
| AUTH-07 | Employee | Sign in with a customer-only URL as the intended destination | Redirect to staff orders | Covered; passed |
| AUTH-08 | Guest | Request reset links for known and unknown emails | Same confirmation is shown; only a known account receives a notification | Covered; passed |
| AUTH-09 | Customer | Use a password-reset link | Password changes once; expired or reused tokens are rejected; reset does not sign the user in | Covered; passed |
| PROF-01 | Customer | Update allowed profile fields | Name, phone, birth date, gender persist; login/email stay fixed | Covered |
| PROF-02 | Customer A and B | Customer A requests B's order | HTTP 404; B's data is not rendered | Covered |
| PKG-01 | Guest | Browse package list and detail | Only active packages are visible | Covered |
| PKG-02 | Manager | Create and edit a package | Valid fields persist; invalid price/duration are rejected; new packages start open for sale | Covered; passed |
| PKG-03 | Manager | Stop or resume package sales | Inactive packages leave the public list; existing order history remains | Covered; passed |
| EMP-01 | Manager | Open the employee account list | Only employee accounts are listed; search and pagination preserve filters | Covered; passed |
| EMP-02 | Manager | Create an employee account, including a forged manager role field | Account is created with employee role and a hashed password | Covered; passed |
| EMP-03 | Manager | Edit employee details with blank or new password | Details update; blank password preserves the old hash; role cannot change | Covered; passed |
| EMP-04 | Employee, customer, guest; manager editing a customer | Attempt to open or mutate employee accounts | Guest is redirected; non-managers and wrong-role records are forbidden | Covered; passed |
| ORD-01 | Customer | Place an order | Pending order snapshots current package price and chosen start date; no membership yet | Covered |
| ORD-02 | Customer with paid period | Place an overlapping order | Validation error; no order is added | Covered |
| ORD-03 | Customer | Request cancellation of pending order | Request timestamp is stored; order and recorded payments remain visible | Covered |
| PAY-01 | Staff | Record partial payment | Payment persists; order stays pending; no membership is created | Covered |
| PAY-02 | Staff | Confirm before full payment | Confirmation is rejected; no membership is created | Covered |
| PAY-03 | Staff | Confirm fully paid order twice | One paid order and exactly one membership | Covered |
| PAY-04 | Customer | Submit a staff payment/confirmation action | HTTP 403; payment and membership are unchanged | Covered |
| DATE-01 | Staff | Confirm an expired or overlapping start date | Validation error; order remains pending | Covered |
| DATE-02 | Customer | Buy one month starting 31 January | Exclusive expiry boundary is 1 March at 00:00; last training date is 28 February | Covered; passed |
| CHECK-01 | Staff | Check in unpaid, future, or expired customer | Validation error; no visit is created | Covered |
| CHECK-02 | Staff | Check in active customer twice without checkout | First visit is created; second is rejected | Covered |
| CHECK-03 | Staff | Check out a visit | Checkout timestamp and staff identity are saved | Covered |
| CHECK-04 | Staff | Manually close a forgotten visit without a reason | Validation error; visit remains open | Covered |
| CHECK-05 | Customer | Mutate attendance record | HTTP 403; visit is unchanged | Covered |
| TRIAL-01 | Guest | Submit a free trial request | Request is stored without creating an account | Covered |
| TRIAL-02 | Employee or manager | Review a trial request and mark it contacted | Inbox renders; staff member and handling time are stored; duplicate processing is rejected | Covered; passed |
| TRIAL-03 | Customer | Open or process the staff trial inbox | HTTP 403; guest is redirected to sign in | Covered; passed |
| CHAT-01 | Visitor | Ask a RAG question through the chat endpoint | Message is forwarded to the configured RAG `/chat` endpoint and the answer and source names are returned | Covered; passed with a mocked RAG response |
| CHAT-02 | Visitor | Submit a blank or overlong chat message | Validation rejects it before making an RAG request | Covered; passed |
| CHAT-03 | Visitor | Ask while RAG is unavailable | A generic service-unavailable response is returned without exposing internal errors | Covered; passed with mocked failure responses |
| POL-01 | Owner, another customer, employee, manager | Ask each order policy ability | Only the permitted role/owner is allowed | Covered; passed |
| POL-02 | Customer, employee, manager | Ask each check-in policy ability | Only employee/manager abilities are allowed | Covered; passed |
| REPO-01 | Feature tests across package, order, membership, check-in | Exercise repository-backed reads and writes | Existing workflow results remain correct | Covered; passed |
| VIEW-01 | Customer, staff, manager | Open owned/list/dashboard pages | Blade pages render without missing Vite assets or runtime errors | Covered; passed after Tailwind build |

## Manual UI cases

### Direct browser run — 4 October 2026

The in-app browser successfully completed these user-visible flows using the seeded manager/employee accounts and a temporary QA customer:

- **Guest:** opened the home page, package list and package details; submitted a free-trial request and saw the success message.
- **Manager:** signed in and opened the dashboard; created a temporary package, edited it, stopped its sale, and verified it no longer appeared in the public package list. The manager reviewed the trial request and marked it contacted; the inbox showed the new status and handler.
- **Customer:** registered a temporary account, updated the profile and saw the saved success state; created one-month and twelve-month orders; opened an order detail; submitted a cancellation request and saw it recorded. The account page later showed a paid active membership, the canceled order, and the check-in history. Attempting to open the manager package page as a customer returned **403 Forbidden**.
- **Employee:** signed in; saw the customer’s cancellation request and canceled that unpaid order; recorded the full amount on the other order and confirmed it, creating one active membership. A check-in for an unknown phone number was rejected; the valid customer was checked in; a duplicate open check-in was rejected; check-out succeeded.
- **Password recovery:** requested a reset for the temporary account. The app showed its generic confirmation, and the local log-mail link opened the reset form. The password-change form was **not submitted through the browser**; reset/change, expiry, and reuse behavior remain verified by the automated feature tests. Actual external email delivery remains unverified because the local environment uses the `log` mailer.
- **Cleanup:** removed only the temporary QA account, its two test orders/payment/membership/check-in, the temporary trial request and inactive package. The pre-existing `Manual Test Customer` pending orders were left unchanged.

The direct browser checks above confirm successful application responses for the actions listed. They do not replace viewport/accessibility testing. Tablet and desktop breakpoints, keyboard-only/focus review, reduced-motion behavior, form error rendering for each workflow, and the reset-password submission remain pending direct verification. The browser was operated at its current in-app viewport; standardized 360px, 768px, and 1440px runs have not been completed.

### Employee account screen — 14 October 2026

- Signed in as the seeded manager account at the local preview and opened **Quản lý → Tài khoản nhân viên**.
- Created a temporary QA employee through the form; the success message appeared and the account showed in the list with its employee role.
- Searched by its username and verified only the matching account appeared.
- Submitted a mismatched new-password confirmation and saw the validation error; then edited the employee name with both password fields blank and saw the success message.
- Verified in the local QA database that the role remained `employee` and the original password still matched its hash. Removed the temporary QA account and confirmed the filtered list returned no match.
- Opening the manager route in an employee session returned **403 Forbidden**, as required by the access policy.

### Isolated QA run — 14 October 2026

- Used a separate preview at `http://127.0.0.1:8003` with the MySQL database `qly_phonggym_manualqa_20261014`; the regular preview ports and application database were left untouched.
- **Guest:** opened the home page, package list and package details, then submitted a synthetic free-trial request and saw the success message.
- **Manager:** signed in and viewed the dashboard; created a synthetic two-month package and edited its price. Both save confirmations appeared.
- The attempt to stop selling the synthetic package was not verified: browser control stopped after it could no longer confirm the current URL. The synthetic package and trial request may remain in the isolated QA database.
- The chatbot launcher was obscured by a Malwarebytes notification. The endpoint and safe unavailable-service behavior passed automated tests with mocked RAG responses; a live RAG conversation was not verified.
- No password-recovery page or action was used in this run. Customer self-service, employee order/payment/check-in screens, and employee-account screens were not manually retested in this run; their workflow coverage is automated or recorded in the earlier dated browser runs.

1. At 360px, verify the menu opens by keyboard/touch, cards stack, forms fit, tables can be read without the whole page overflowing, and browser zoom remains enabled.
2. At 768px, verify navigation and two-column sections have no overlap or clipped controls.
3. At 1440px, verify the approved C#-based brand style and intended page hierarchy remain intact.
4. Tab through each form and navigation control; every control should show focus, and every input should have a visible or screen-reader label.
5. Submit invalid forms and verify errors are announced and shown with a next step; confirm successful actions expose a status message.
6. Check reduced-motion preference and verify the hero carousel does not force motion for users who request reduced motion.

## Commands and completion criteria

```powershell
php artisan test --compact
vendor/bin/pint --format agent
npm run build
php artisan migrate:status --no-interaction
```

The latest automated run excludes `PasswordRecoveryTest` per the user's request. Remaining acceptance work includes standardized viewport and accessibility checks and a live RAG chat check with its service running. Password recovery and external mail delivery were outside the latest test request.

## Product gaps and scope boundaries

- Trial requests can be reviewed and marked contacted by employees/managers. Call outcomes and staff assignment are not tracked beyond the person who marks contact.
- Managers can create, edit, activate, and deactivate packages; package records are retained when they have order history.
- Password recovery uses a single-use email reset link; this local environment currently uses the `log` mailer, so real mailbox delivery requires SMTP settings.
- Managers can search, create, and edit employee accounts. Creation always assigns the employee role; changing passwords during edits is optional. There is no account deletion or activation toggle.
- The RAG chatbot depends on a separate service configured with `RAG_API_URL`; automated coverage stubs this service, so it does not prove that the live RAG process or its document index is available.
- Staff orders and trial requests support filtering/pagination; staff check-in history is capped at the latest 50 visits, while customer attendance is paginated.
- Staff check-in looks up a member by identifier or phone, then shows the member and active package for staff to compare before confirming entry.
- Inventory/product sales, branches, PT, classes, online payment processing, and web-based refunds are outside the one-gym membership scope.

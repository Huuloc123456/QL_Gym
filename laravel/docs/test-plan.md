# Test Plan — The Gym Laravel Web

## Objective

Verify the user-visible workflows in the agreed one-gym scope, and the added architecture/security/UI criteria: Repository Pattern, Tailwind CSS, responsive browser layouts, Laravel policies, and authenticated sessions.

## Test environments

- Automated: PHPUnit through `php artisan test --compact`; `phpunit.xml` points to the dedicated MySQL database `qly_phonggym_test`, which migrations refresh during tests. Never point it at the populated local preview database.
- Local preview: Laravel serves on `http://127.0.0.1:8000`; `.env` uses the Laragon MySQL database. Never point `RefreshDatabase` tests at the seeded preview database.
- Frontend build: `npm run build` runs Vite and compiles Tailwind CSS 4.
- Manual responsive review: 360px phone, 768px tablet, and 1440px desktop. Check navigation, cards, tables, form labels, validation, focus visibility, and horizontal overflow.

### Database and browser inspection before the final code batch — 27 October 2026

- `php artisan test --compact`: **236 tests, 235 passed, 1 skipped, 1,621 assertions**. The skipped test requires a separate isolated MySQL account-provisioning server on port 3318.
- `node --test tests/js/carousel.test.js`: **4 passed**. `npm run build`, `vendor/bin/pint --dirty --format agent`, and `git diff --check`: **passed**.
- `php artisan migrate:status --no-interaction`: every migration, including the three added on 27 October, reports `Ran`. The local MySQL database was backed up before applying them; no refresh/reset was run against it.
- **Manager browser:** signed in through ChatGPT; dashboard, customer list, reports, gym settings, and chatbot-document management loaded. The reports page showed date filters and Excel export. Settings surfaced the scheduler warning and existing data-reconciliation items.
- **Employee browser:** signed in through ChatGPT; operations dashboard, staff product list, trial inbox, unified invoice list, and check-in page loaded. A non-existent phone was rejected without creating a visit. Direct access to `/quan-ly` returned **403 Forbidden**.
- **Guest browser:** public package/product content loaded; `/dang-ky?trial=1` showed account creation and the trial return path; requesting `/my/don-hang` redirected to sign-in. The registration form was not submitted, because a new password must be entered and submitted by the user in the browser. Customer registration, ownership, and order workflows are covered by automated feature tests; authenticated customer UI was not re-run in this browser pass.
- **RAG:** manager page displayed the 18 knowledge records stored in MySQL, but reported that its configured RAG API was unavailable. Nothing was listening on port 8090; no live chat answer or index rebuild is claimed for this pass.
- **Scheduler:** `schedule:list` shows `gym:process --mail` every minute, while Manager → Settings warns that the scheduler has not run recently. Start `php artisan schedule:work` locally or call `php artisan schedule:run` every minute on the deployment host before relying on automatic expiry/reminders.
- No new order, paid receipt, refund, external VNPay sandbox/IPN, or test customer was created in the populated application database. The invalid check-in probe used a synthetic, nonexistent phone and was rejected. Old invoice reconciliation, missing product images, and current class setup still require verified business data.
- Database and upload backup: `C:\Users\LeTranPhu\AppData\Local\Temp\thegym-pre-migration-20261027-190854` (outside the repository). No RAG credentials or DB secrets are recorded here.

### Final role-based workflow pass — 28 October 2026

- Automated: `php artisan test --compact` — **259 tests, 258 passed, 1 skipped, 1,788 assertions** on the dedicated MySQL test database. The skipped case requires an isolated MySQL account-provisioning server on port 3318. `node --test tests/js/carousel.test.js` — **4 passed**; `npm run build`, Pint, and `git diff --check` passed.
- Manual browser checks ran against a separate QA database clone and Laravel server on port 8003. The populated application database and port 8002 server were not used for test writes.
- **Manager:** signed in; dashboard, report summary/date filters, customer list, employee list and permission view, gym settings, banner manager, and unified order/invoice list loaded. The report workbook structure and permissions are covered by automated tests; an actual external VNPay/IPN settlement was not run.
- **Employee:** direct access to `/quan-ly` returned 403. Created a QA product, verified a missing inventory-change reason is rejected in Vietnamese without changing stock, then saved the corrected quantity. Reviewed/approved a trial request, verified duplicate check-in is rejected, and checked out the visit.
- **Member:** signed in, viewed an active package and training history, created a combined package/product invoice, saw the order in the unified invoice list, and used two partial cash receipts in QA. Confirming before full payment was rejected; confirming after full payment activated the package, and in-store pickup completed. The product quantity decreased by the ordered amount. Member order history showed the pending transfer QR with the invoice amount/reference; no money was transferred.
- **Catalog:** the QA copy displayed 58 products including its QA fixture; page 4 showed results 46–58 of 58, with no extra item beyond the count.
- **Fix during this pass:** inventory updates now show localized Vietnamese validation for a missing/invalid reason. The focused regression test and full suite passed afterward.
- **Not verified against live services:** the RAG endpoint was unavailable on port 8090; no VNPay sandbox credentials/IPN callback or real SMTP delivery were available. RAG sync, online settlement, and real email delivery therefore remain environment-dependent, not claimed as manually passed. Registration/password-reset behavior remains covered by feature tests; browser UI password reset was not exercised on a newly created account.
- The application database still has two pending additive migrations (`2026_10_27_224753_add_legacy_membership_source_and_trial_revocation_actor` and `2026_10_27_230000_add_revision_tracking_to_rag_documents`). They were applied only to the QA clone for this pass. Back up the application database, then run `php artisan migrate --force` before exercising these latest workflows on port 8002.
- The QA copy and its temporary test records are local and are not included in the Git commit. No payment, email, password, or database secrets are recorded here.

### Historical verification results — 14 October 2026

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
| AUTH-11 | Customer | Change password from either account menu | Current password is checked; password and legacy SQL mirror update together; other database sessions are removed and remember token rotates | Covered; passed |
| AUTH-12 | Guest with an existing account session | Complete a valid password reset | Password changes once, every previous database session is removed, remember token rotates, and the user remains signed out | Covered; passed |
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
| ORD-05 | Staff | Browse pending, history, and all orders; search by order ID, name, phone, or email | Correct status/search results; 20 per page; filters persist across pages | Covered; passed |
| ORD-06 | Staff with 101 matching orders | Open page 6 | The oldest result remains reachable and count is 101 | Covered; passed |
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
| CHECK-06 | Staff with more than 50 visits | Search member and browse history | Old visits remain searchable; open/history/all lists paginate 20 at a time | Covered; passed |
| CHECK-07 | Staff | Filter check-ins by member, date, and open/history status | Filters combine and remain selected across pages; closed visits have no checkout actions | Covered; passed |
| CUST-01 | Customer with many orders | Filter pending/paid/cancelled orders and open later pages | Only the authenticated customer's records appear; 20 per page | Covered; passed |
| CUST-02 | Customer with current/future/expired plans | Open membership page and history page 2 | Plans are grouped by dates; expired membership history is paginated | Covered; passed |
| RPT-01 | Manager | Select a date range on reports | Created-order counts, paid-at revenue, and received-at receipts are separate and use the gym timezone | Covered; passed |
| RPT-02 | Manager | Download report workbook | Three valid XLSX sheets contain summary, relevant orders, and receipt records; string values cannot become formulas | Covered; passed |
| RPT-03 | Employee/customer | Open or export manager report | HTTP 403 | Covered; passed |
| RPT-04 | Manager | Confirm a previously paid legacy package order linked to an invoice | Its original receipts remain visible exactly once in the invoice, report, and XLSX; revenue is assigned to the real confirmation date | Covered; passed |
| RPT-05 | Manager | Review a partially received order after a full or partial refund, including a VNPay payment awaiting reconciliation | Remaining balance subtracts only retained confirmed payments; unresolved VNPay amount stays separate in reconciliation totals | Covered; passed |
| TRIAL-01 | Guest | Submit a free trial request | Request is stored without creating an account | Covered |
| TRIAL-02 | Employee or manager | Review a trial request and mark it contacted | Inbox renders; staff member and handling time are stored; duplicate processing is rejected | Covered; passed |
| TRIAL-03 | Customer | Open or process the staff trial inbox | HTTP 403; guest is redirected to sign in | Covered; passed |
| CHAT-01 | Visitor | Ask a RAG question through the chat endpoint | Message is forwarded to the configured RAG `/chat` endpoint and the answer and source names are returned | Covered; passed with a mocked RAG response |
| CHAT-02 | Visitor | Submit a blank or overlong chat message | Validation rejects it before making an RAG request | Covered; passed |
| CHAT-03 | Visitor | Ask while RAG is unavailable | A generic service-unavailable response is returned without exposing internal errors | Covered; passed with mocked failure responses |
| RAG-05 | Manager | Search the knowledge list or edit FAQ/documents | Main list hides IDs and Markdown while preserving FAQ edit and status actions | Covered; passed |
| RAG-06 | Manager | Add or edit FAQ with topic, question, and answer fields | RAG document is generated automatically without requiring Markdown | Covered; passed |
| RAG-07 | Manager | Download the Excel template, preview a valid batch, and confirm | Preview shows add/update counts; one authenticated batch request updates documents and rebuilds the index once | Covered; passed |
| RAG-08 | Manager | Upload an invalid spreadsheet (duplicate IDs, bad headers, missing values, too many rows) | Per-row errors are shown; confirmation is blocked and no partial batch is sent | Covered; passed for duplicate IDs; other validations implemented |
| RAG-09 | RAG service test | Simulate index rebuild failure during a batch | Every prior document and index is restored; no new partial document remains | Covered; passed |
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

The latest full automated run includes password-recovery tests; one environment-dependent test is skipped because it requires the isolated MySQL account-provisioning server on port 3318. Standardized viewport/accessibility review and live RAG checks remain separate acceptance tasks. Automated password-reset tests do not prove delivery to an external mailbox.

## Product gaps and scope boundaries

- PT đã được gỡ khỏi trang khách, giỏ hàng, thanh toán, lịch, nhắc lịch, cấu hình và báo cáo hiện hành. Các bảng và chứng từ PT cũ vẫn được giữ để tra cứu; hóa đơn cũ có dịch vụ đã ngừng không thể nhận/chốt thanh toán mới và cần quản lý đối chiếu.
- Trial requests can be reviewed and marked contacted by employees/managers. Call outcomes and staff assignment are not tracked beyond the person who marks contact.
- Managers can create, edit, activate, and deactivate packages; package records are retained when they have order history.
- Password recovery uses a single-use email reset link; this local environment currently uses the `log` mailer, so real mailbox delivery requires SMTP settings.
- Managers can search, create, edit, lock, and unlock employee accounts. Locking records its time and manager, blocks future login, and ends an existing session on its next web request. Account deletion remains out of scope.
- Authenticated customers, employees, and managers can change their own password after confirming the current one. Password recovery and external mail delivery are separate flows.
- Managers can edit public contact/intro/payment details and hero banners. Chatbot knowledge has a separate FAQ form, long-guide form, search, single-document actions, and Excel import preview/confirmation. The RAG process remains a separate service configured with `RAG_API_URL` and a shared `RAG_ADMIN_TOKEN`; automated tests mock expensive FAISS rebuilds, so they do not prove a live Groq answer or a real FAISS reindex on the recipient's machine.
- Staff orders and trial requests support filtering/pagination. Staff check-in history is searchable and paginated without a latest-50 cap; customer order and attendance histories are paginated.
- Staff check-in looks up a member by identifier or phone, then shows the member and active package for staff to compare before confirming entry.
- Staff product creation/editing, real stock adjustment, product image management, and VNPay checkout for membership orders and invoices are implemented. Managers retain product deletion rights. Automated VNPay tests use signed local callback requests; a real sandbox transaction still requires credentials and a publicly reachable HTTPS IPN endpoint.
- Branches, automatic refunds, and multi-step account verification remain outside the one-gym project scope.

## Added capability test cases — 21 October 2026

| ID | Role / setup | Action | Expected result |
| --- | --- | --- | --- |
| SITE-01 | Manager | Save gym contact details, intro, bank details, and FAQ pairs | Values persist and public home/order pages show only configured information |
| SITE-02 | Manager | Upload a valid JPEG/PNG/WebP hero image | Image is stored on the public disk and appears in the home carousel |
| SITE-03 | Manager | Upload SVG or an oversized image | Server validation rejects it and does not create a banner |
| SITE-04 | Employee/customer | Open or submit manager content forms | Server returns 403 and data is unchanged |
| EMP-05 | Manager | Lock and unlock an employee | Status, lock time, and manager are stored; reactivation clears lock audit fields |
| EMP-06 | Locked employee | Sign in or request any web page with an existing session | Login is denied and an existing session is logged out |
| AUTH-10 | Authenticated user | Change password with a wrong/current password and a valid one | Wrong current password is rejected; valid update stores a hash and keeps the session usable |
| RAG-01 | Manager | Add, edit, pause, re-enable, and delete a Markdown document | Laravel sends authenticated admin requests; status and document content refresh |
| RAG-02 | Employee/customer/guest | Open or submit the manager document screens | Access is denied or redirected before any admin request is sent |
| RAG-03 | Manager; RAG service unavailable or rebuild fails | Save a document while the RAG service is offline or indexing errors | UI shows a clear error and does not claim success; document/index rollback is attempted |
| RAG-04 | Manager | Open the floating website chatbot after changing knowledge | The existing widget remains the single place to try a question and displays answer sources |

The Laravel feature tests use the dedicated MySQL test database configured in `phpunit.xml`. The RAG unit tests use temporary directories and mock embedding/index rebuild work; live Groq delivery, full FAISS replacement, local browser layout review, and database backup restoration require a separate manual run with the RAG service and local preview running.

### Automated verification — 21 October 2026

- Laravel: `php artisan test --compact --filter='^(?!.*PasswordRecoveryTest).*$'` — **95 tests passed, 509 assertions**. Password recovery was excluded as requested; the other authentication and account-management cases ran.
- RAG service: `python -m unittest discover -s tests -v` — **18 tests passed**. Embedding/index rebuilds are mocked in unit tests.
- PHP formatting: `vendor/bin/pint --dirty --format agent` — passed.
- Frontend production build: `npm run build` — passed. Vite reported that optional `fontaine` is unavailable, but completed the build.
- Laravel manager and account route lists were checked. Tests used the dedicated test database; no migration was applied to the regular local preview database.
- Still needs a live acceptance run on the target machine: apply migrations, run Laravel and the separate RAG service with matching `RAG_ADMIN_TOKEN`, then exercise image uploads, document reindexing/Groq responses, backup/restore, and browser layouts. No live RAG provider call or real FAISS rebuild is claimed by the test results above.

### Regression fixes — 27 October 2026

- Added permanent regression cases for refunded order balances, retaining legacy package receipts when they are linked to invoices, assigning legacy revenue to the confirmation date, keeping unresolved VNPay payments out of available balances, and revoking old sessions after password change/reset.
- Red checks reproduced the old behavior before implementation. The scoped Laravel run `php artisan test --compact --filter='ManagerReportsTest|ManualRefundWorkflowTest|ManagerEmployeeAccessTest|PasswordRecoveryTest|GymPortalCompletionTest'` passed **37 tests and 298 assertions** against `qly_phonggym_test`.
- `vendor/bin/pint --dirty --format agent` passed. The full Laravel suite and manual browser acceptance have not been rerun after these changes; do not treat this scoped run as final release verification.

### Search, reporting, customer history, and FAQ import — 21 October 2026

- Laravel: `php artisan test --compact --filter='^(?!.*PasswordRecoveryTest).*$'` — **114 tests passed, 674 assertions**. The acceptance includes 101 matching orders, 55+ check-ins, manager-only reports, report period boundaries, customer ownership, valid XLSX download/import, invalid duplicate IDs, non-manager access, and the 100-row import limit. Password recovery tests remain excluded per the user's request.
- RAG: `python -m unittest discover -s tests -v` — **20 tests passed**, including batch writes, one index rebuild on success, and restoration after a failed rebuild.
- `vendor/bin/pint --dirty --format agent` — passed.
- `npm run build` — passed. Vite emitted its optional `fontaine` optimization notice and completed successfully.
- Excel processing uses PhpSpreadsheet and PHP's `zip` extension; the local Laragon PHP configuration has `extension=zip` enabled. A recipient must enable the same extension in their PHP runtime.
- The local application MySQL database was not reset. Laravel tests ran against `qly_phonggym_test`; no direct browser, live Groq request, or actual FAISS embedding rebuild was performed during this implementation pass.

### Direct browser acceptance — 21 October 2026

- Ran the new manager workflows against `qly_phonggym_test`, not the regular application database. Saved a temporary gym introduction, confirmed it appeared on the public home page, then restored the field.
- Uploaded two project WebP banners, edited one title and alt text, replaced its image, changed their order, and confirmed the public carousel showed the new first banner. Toggled banners off/on, deleted both QA banners, and confirmed the manager page returned to its empty state with the default hero on the home page.
- Locked the seeded QA employee, confirmed login was rejected with the generic login error, then unlocked the account and confirmed its active status and lock audit details in the manager list.
- Connected Laravel to the local RAG API with an in-memory QA-only token because the local Laravel `.env` has no `RAG_ADMIN_TOKEN`. Added, edited, paused, re-enabled, and deleted a temporary Markdown document; each change rebuilt the real FAISS index. A Groq-backed test question returned the edited marker and listed the QA document as a source. After deletion/reindexing, the chatbot said it had no information about that marker. The original 12-document corpus was restored, and no secret was written to either `.env` file.
- Opened the change-password screen; no credential was entered or changed in the browser. The automated feature tests cover its validation and update behavior.
- Stopped the temporary Laravel and RAG servers and reset only the dedicated `qly_phonggym_test` schema to a clean migrated state. The normal application database was not migrated or modified.

### Baseline before integrating `newrepo/main` — 21 October 2026

- `php artisan test --compact` — **118 tests passed, 701 assertions**, using only `qly_phonggym_test`.
- Backed up `qly_phonggym_laravel` outside the repository and restored it into the separate `qly_phonggym_restore_20261021b` database. Restore verification found 17 tables; the source database also has no stored routines or triggers.
- No migration from `newrepo/main` has been applied to either the application database or the restored copy.

### SQL integration and current MySQL verification — 21 October 2026

- Created a fresh backup of `qly_phonggym_laravel` at `C:\laragon\tmp\qly_phonggym_laravel-pre-integration-20261021.sql` before applying schema changes.
- Confirmed Laravel connects with `DB_CONNECTION=mysql` to `qly_phonggym_laravel` on Laragon MySQL 8.4.3, port 3306. Applied all three pending migrations with `php artisan migrate --force`; existing Laravel tables and rows were not reset.
- Verified all 22 source SQL tables, 11 stored procedures, one function, one trigger, 57 products, and 20 class records are present. `php artisan migrate:status` reports all migrations as ran.
- `php artisan test --no-coverage` — **170 passed, 1 skipped, 1,167 assertions**. The skipped test deliberately creates and locks a MySQL account and only runs when the dedicated isolated MySQL test server is on port 3318; it was not run against the application database.
- Verified 131 route method/URI pairs with no duplicates. Prior public preview smoke checks loaded the home page, product search, classes, and trainer directory. Imported class dates all end in 2025 and are historical; the manager must create current classes. Source SQL PT records have no open enrollment/specialty detail, so the public PT page asks visitors to contact the front desk.
- The app is running at `http://127.0.0.1:8002`. Codex browser policy blocked reopening that loopback URL in the final browser pass, so this verification does not claim a post-migration browser review against the application database. Earlier manager actions are covered by feature tests and the direct browser acceptance recorded above.

### Direct role-based browser run — order, payment, and attendance — 21 October 2026

This run used a temporary Laravel server and the isolated `qly_phonggym_test` database. A dedicated QA customer and a test invoice were created. The 399,000 VND payment was a test record only; it was not a real-world payment. The regular application database was not used.

- **Passed:** manager login; guest trial submission; employee marked the trial request as contacted; QA customer registration and login; a one-month package purchase generated invoice `#183`; employee recorded the full cash amount and confirmed the invoice; the membership became active for 21 October–20 November 2026; employee check-in and check-out completed; customer attendance history showed both timestamps; manager dashboard revenue and active-member totals increased by the expected amounts.
- **Issue — staff order discovery:** searching for invoice `#183` in `/staff/don-hang?status=all` returned no result. The invoice was available at `/hoa-don?q=183`, so invoice-backed purchases are separate from the staff membership-order list and are easy to miss from the usual navigation.
- **Issue — customer payment history:** the confirmed invoice appeared in `/my/don-hang`, but the mirrored order showed “Đã ghi nhận 0 VNĐ” even though the employee had recorded 399,000 VND. The commerce confirmation path creates the customer-facing order/membership mirror without a matching `OrderPayment` row.
- **Issue — manager receipt report:** the selected report showed 399,000 VND paid revenue but 0 VND actual receipts and 0 VND for both cash and bank transfer. The invoice payment is stored in `gym_receipts`, while the report's receipt totals read `order_payments`.
- **Cleanup/status:** the temporary Laravel server was stopped. Test data is isolated in `qly_phonggym_test`; no production or regular local application data was changed. No code fix was included in this documentation update.

These findings describe the build as tested on 21 October 2026. Invoice detail/customer history now calculate collected amounts from counter receipts and successful online payments, and manager reports aggregate invoice receipts plus successful VNPay receipts. Invoice-backed purchases continue to use `/hoa-don`, separate from the membership-only `/staff/don-hang` screen; employees can search and paginate invoice history in the invoice workflow. The newest automated tests below cover updated reporting and payment paths.

### Full regression and browser verification — 21 October 2026

- `php artisan test --no-coverage` — **171 passed, 1 skipped, 1,170 assertions**. Tests use `qly_phonggym_test`; the skipped MySQL account-provisioning test requires the isolated MySQL test server on port 3318 and was not run against the application database.
- `vendor/bin/pint --dirty --format agent` — passed.
- RAG service: `python -m unittest discover -s tests -v` — **20 tests passed**.
- Started the local RAG API with the project's existing environment configuration. Health returned ready; a live browser question about one-month membership validity received an answer with two knowledge-base sources. No credentials or OTPs were sent.
- Browser smoke checks on `http://127.0.0.1:8002`: home and membership dates, package list, store search, class filtering, PT directory, and chatbot. Search for “Protein” returned two items; “Yoga” returned three classes. The chatbot answered from indexed sources.
- Found and fixed imported role data that assigned sample employee `Admin` to PT. The SQL seed now assigns manager role ID 3, and migration `2026_10_21_210000_correct_admin_employee_role` corrects only that exact imported row when it still has PT role ID 2. A regression test confirms the public PT directory no longer lists `Admin`; the live page now shows only PT employees.
- Remaining source-data limitations: imported class schedules ended in 2025, and the remaining PT profile lacks specialty and online booking details. Create current schedules and complete PT profiles before public operation.

### Staff product management and VNPay — 22 October 2026

#### Test cases

| ID | Role / setup | Action | Expected result |
| --- | --- | --- | --- |
| PROD-01 | Employee with other module permissions disabled | Open `/staff/san-pham`, create a product and edit its details/stock | Product is saved to MySQL; employee can manage products independently of trial, order, or check-in permissions |
| PROD-02 | Employee / manager | Attempt to delete a product as employee, then manager | Employee receives 403; manager can delete |
| PROD-03 | Employee; product has stock reserved by a pending order | Upload an allowed product image and try reducing stock below reserved quantity | Image is stored; stock validation rejects a value below reserved quantity and preserves current stock |
| PAYV-01 | Customer; configured test signing keys | Start membership payment and send a correctly signed successful IPN twice | First valid IPN confirms payment and creates one membership; duplicate callback is idempotent |
| PAYV-02 | Customer; pending payment | Open return URL without IPN, then send an invalid signature or wrong amount | Return page cannot confirm payment; invalid callback is rejected and order remains pending |
| PAYV-03 | Customer requests cancellation before a successful callback | Send signed success IPN and inspect employee order screen | Payment is held for manual review with a reason; no membership is activated automatically |
| PAYV-04 | Customer checks out a product invoice through VNPay | Send the same valid IPN twice | Invoice is paid, reserved stock is deducted once, and one set of invoice details is retained |
| PAYV-05 | Manager; test receipts across payment methods | Open a report and export XLSX | Confirmed receipts include counter receipts and successful VNPay for memberships/invoices, grouped by method |
| PAYV-06 | No VNPay keys / both test keys set | Open checkout in each configuration | VNPay is hidden without both keys and offered after both are configured |

#### Latest verification

- `php artisan test --compact` — **181 passed, 1 skipped, 1,251 assertions** (182 tests discovered). The skipped case provisions and locks a MySQL account and requires the isolated test server on port 3318.
- `vendor/bin/pint --dirty --format agent` — passed. `git diff --check` — passed.
- Local database connection is MySQL (`qly_phonggym_laravel`); the migrations for online payment history and review reasons report `Ran`. Automated tests use the separate `qly_phonggym_test` database.
- Browser role checks opened the manager view and employee `/staff/san-pham`, product create/edit and image screens, verified the employee view has no delete action, and opened staff invoice history. These checks did not submit a real product mutation or payment.
- Automated VNPay tests generate signed IPN requests locally, including duplicate, invalid-signature, wrong-amount, cancellation-review, membership, invoice, and stock-deduction cases. No transaction was sent to the external VNPay sandbox.
- The local `.env` has empty `VNPAY_TMN_CODE` and `VNPAY_HASH_SECRET`, and the local site is not exposed at a public HTTPS address. Live sandbox checkout/IPN delivery therefore remains to be configured and verified. Public endpoint setup, real bank authorization, and refunds are not claimed.
- Manual visual checks at 360px/768px/1440px, a full accessibility audit, backup restore, and a live RAG provider conversation were not repeated in this feature run; see the earlier dated acceptance entries for prior RAG checks.

### Full role-based browser retest — 22 October 2026

- Logged in through the ChatGPT in-app browser as the seeded manager and employee, then registered and signed in as a new QA member. The QA member remains in the local application database for follow-up testing; no test password is recorded in this repository.
- **Manager:** opened the dashboard, customer management, reports, package and employee management, product catalog, banners, gym settings, and chatbot knowledge screens. An employee request to open `/quan-ly` returned **403 Forbidden**.
- **Employee:** opened the operations dashboard, product inventory, unified invoice history, check-in, and trial-request screens. The product table showed reserved quantity separately from physical stock.
- **Member — package:** registered a one-month package, opened its invoice, and verified the dynamic VietQR amount and invoice-specific transfer reference. The member submitted a cancellation request; an employee canceled unpaid invoice `#188`.
- **Member — store:** ordered one product and opened its invoice/QR. While pending, inventory showed 1 unit held and 9 available from stock 10. After the member requested cancellation, an employee canceled unpaid invoice `#189`; inventory returned to 0 held and 10 available. No money was transferred or recorded.
- **Chatbot:** asked “Bạn có những sản phẩm gì?” and received a catalog answer with product images and detail links. Enter submitted the next question, and an unrelated coding request received an out-of-scope refusal.
- **Automated regression:** `php artisan test --compact` — **198 passed, 12 failed, 1 error, 1 skipped; 1,406 assertions** across 212 tests. The focused RAG/product and staff-membership subset reproduced failures around the knowledge-list content/redirect expectation, the product-catalog request/fixture, and several staff-order redirect expectations. These failures are unresolved and the suite is not green. The skipped case needs a separate MySQL account-provisioning server on port 3318.
- **Not verified end-to-end:** a real VNPay sandbox payment/IPN (local `.env` has no VNPay keys and the site has no public HTTPS callback); check-in with an active, paid membership; standardized 360px/768px/1440px review; and a complete accessibility audit. Earlier RAG live acceptance is recorded above, but that was not repeated in this run.
- **Local QA data:** invoices `#188` and `#189` remain as canceled test records, and the new QA member account remains available. Neither invoice was paid; inventory reservations were released.

### RAG and inventory regression verification — 27 October 2026

- `php artisan test --compact` on the dedicated `qly_phonggym_test` MySQL database — **256 tests, 255 passed, 1 skipped, 1,755 assertions** at this checkpoint. The final result after adding Eloquent/report tests is recorded below. The skipped test requires the separate MySQL account-provisioning server on port 3318.
- RAG/FAQ/chatbot-focused subset — **34 passed, 181 assertions**. Coverage includes MySQL-side search and pagination, GET requests making no RAG calls, explicit sync, a second sync respecting the lock, edits during an active sync staying pending, retry failure retention, package prices and estimates, public gym hours/contact settings, and keeping bank details out of public facts.
- `python -m unittest discover -s tests -v` in the available system runtime (Python 3.14.6) — **37 passed, 3 skipped**. The skipped tests require a real Groq call. No separate virtualenv was created.
- `vendor/bin/pint --dirty --format agent` and `git diff --check` — passed.
- The manager knowledge page now reads and paginates MySQL only. A POST action performs RAG sync; sync writes its acknowledgment only if the document revision still matches the version sent. Failures leave the MySQL content pending and record an error for later retry.
- Package names/prices, configured public hours/contact/address/trial duration, and product catalog questions use current website data. Bank account fields are excluded from the public settings answer path. Other gym policies still use the RAG documents.
- The new `rag_documents` revision/error migration was exercised by test database migrations only. It was not applied to the application database.
- **Not verified in this run:** live `/health`, a real FAISS rebuild, a Groq-backed conversation after these changes, synchronization with a temporary QA document and its cleanup, or browser layout. The PHP/Python mocks do not prove the external service accepted the current revision.

### Final code regression and report alignment — 27 October 2026

- `php artisan test --compact` on `qly_phonggym_test` — **258 tests, 257 passed, 1 skipped, 1,785 assertions**. The skipped test needs the isolated MySQL account-provisioning server on port 3318; it was not redirected to the application database.
- Added Eloquent mappings and model relationship coverage for legacy `SANPHAM` and `LOAISANPHAM`, retained the current transaction/locking and trigger behavior, and moved the manager dashboard summary to aggregate queries rather than loading full report details.
- `npm run build` — passed with Vite 8.3.5. `vendor/bin/pint --dirty --format agent` and `git diff --check` — passed.
- Python RAG suite — **37 passed, 3 skipped** because the skipped cases require a real Groq request. The local RAG health endpoint on port 8090 refused connection; no live question, indexing rebuild, or document sync is claimed.
- No interactive browser role walkthrough was run in this final code batch. No VNPay sandbox transaction, external email, or application-database write was performed. Existing dated browser acceptances above remain historical evidence and do not certify the final code batch's visual layout.
- DOCX candidate now records the final test totals, technology versions, current trial/account flow, product permission boundary, and Eloquent work. Native Word pagination still requires a renderer that is not installed in this environment; the HTML preview is only a content/layout aid, not final pagination evidence.

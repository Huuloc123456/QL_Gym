# Platform Requirements Implementation Plan

**Status:** Implemented and verified on 4 October 2026. See [the test plan](../test-plan.md) for case coverage, results, and remaining manual checks.

**Goal:** Extend the Laravel gym site to meet the project checklist for Repository Pattern, Tailwind, responsive use on multiple screen sizes, policies, authentication, and documented tests.

**Architecture:** Keep the existing Laravel session-authenticated, single-gym membership workflow and MySQL runtime. Move repeated Eloquent query access into small domain repositories, enforce ownership and staff actions through Laravel policies, use route throttling for login, and render the approved C#-based interface with the already-installed Tailwind 4 pipeline.

**Tech Stack:** Laravel 13, PHP 8.3, MySQL, PHPUnit, Tailwind CSS 4, Vite.

---

### Task 1: Record the requirements and test plan

**Files:**
- Create: `docs/test-plan.md`
- Create: `docs/plans/2026-10-04-platform-requirements.md`

**Steps:**
1. Map existing feature tests to the account, membership, payment, check-in, and manager workflows.
2. Record missing automated cases for policy authorization and login throttling.
3. Record manual responsive checks at 360px, 768px, and 1440px.
4. Keep the already-approved C# visual direction and the existing single-gym business scope.

**Verification:** Review both documents against `docs/nghiep-vu-goi-hoi-vien.md` if present, the current routes, and existing tests.

### Task 2: Add small domain repositories

**Files:**
- Create: `app/Repositories/GymPackageRepository.php`
- Create: `app/Repositories/MembershipOrderRepository.php`
- Create: `app/Repositories/MembershipRepository.php`
- Create: `app/Repositories/CheckInRepository.php`
- Modify: package, member, order, check-in, and manager controllers/services
- Test: `tests/Feature/PortalCoverageTest.php`, existing membership and check-in feature tests

**Steps:**
1. Move recurring reads and persistence access used by controllers/services into domain repositories.
2. Keep business rules and transactions in the existing services.
3. Inject repositories through Laravel's container; do not add an interface for a single implementation.
4. Run focused feature tests and confirm they still use real database records.

### Task 3: Enforce policy authorization

**Files:**
- Create: `app/Policies/MembershipOrderPolicy.php`
- Create: `app/Policies/CheckInPolicy.php`
- Modify: `routes/web.php`, `app/Providers/AppServiceProvider.php` only if explicit policy registration is required
- Create: `tests/Feature/AuthorizationPolicyTest.php`
- Modify: `tests/Feature/CustomerAccountTest.php`, `tests/Feature/StaffMembershipTest.php`, `tests/Feature/StaffCheckInTest.php`

**Steps:**
1. Write policy matrix tests for owner, other customer, employee, and manager.
2. Run the new tests and confirm the expected failures before adding policies.
3. Add policies for private order viewing/cancellation and staff payment, confirmation, cancellation, check-in, check-out, and manual close.
4. Wire protected routes to policy abilities while preserving 404 for another customer's order.
5. Run focused policy/feature tests, then the full suite.

### Task 4: Protect the existing authentication flow

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/CustomerAccountTest.php`

**Steps:**
1. Add a failing test that repeated invalid login attempts receive HTTP 429.
2. Apply Laravel's built-in route throttle to login POST requests.
3. Confirm registration still logs the new member in, login regenerates the session, logout invalidates it, and role-protected pages remain inaccessible to customers.
4. Run focused authentication tests and the full suite.

### Task 5: Use Tailwind for the approved responsive UI

**Files:**
- Modify: `resources/css/app.css`, `resources/js/app.js`, `resources/views/layouts/app.blade.php`
- Modify: `resources/views/home.blade.php`, `resources/views/auth/*.blade.php`, `resources/views/packages/*.blade.php`, `resources/views/memberships/*.blade.php`, `resources/views/orders/*.blade.php`, `resources/views/profile/*.blade.php`, `resources/views/staff/**/*.blade.php`, `resources/views/manager/*.blade.php`
- Modify: `vite.config.js` only if the existing font configuration needs adjustment

**Steps:**
1. Use the existing Tailwind 4 and Vite dependencies; do not add a second CSS framework dependency.
2. Keep the C# approved brand assets, blue/black/white palette, gym photography, labels, copy, and business flow.
3. Convert application templates to Tailwind utilities and remove Bootstrap-only dependencies from the Laravel layout.
4. Add responsive navigation, form, card, and table layouts for mobile, tablet, and desktop.
5. Preserve semantic labels, keyboard focus indicators, reduced motion, and useful alt text.
6. Run `npm install` only to restore the existing declared frontend dependencies if needed, then `npm run build`.
7. Render application pages through feature tests; perform manual viewport checks after the site is opened locally.

### Task 6: Execute verification and report remaining gaps

**Files:**
- Modify: `docs/test-plan.md` with results and any deferred items

**Steps:**
1. Run targeted PHPUnit tests after each TDD change.
2. Run `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, and `npm run build`.
3. Check MySQL migrations without running destructive tests against the seeded local database.
4. Keep the local server available and report the URL, demo login accounts, verification results, and remaining product gaps.

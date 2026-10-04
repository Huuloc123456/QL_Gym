# Laravel conversion implementation plan

**Goal:** Rebuild the customer and gym membership workflow in `docs/nghiep-vu-goi-hoi-vien.md` as a Laravel application using MySQL, keeping the C# project as reference.

**Architecture:** Put the new Laravel app in `laravel/`. Use Laravel's session authentication, Eloquent models and migrations, a small service for payment confirmation/membership dates, and a small service for check-in. Keep one web app and one MySQL database; do not port shop, PT, classes or Oracle-specific security features.

**Tech stack:** PHP 8.3, Laravel 13, MySQL 8.4, Blade, PHPUnit. UX/UI visual implementation waits for user approval of a preview based on the two requested design skills.

## Work items

1. **Scaffold and configure.** Create `laravel/` with Composer. Point `.env` at a dedicated local MySQL database; keep credentials out of Git. Confirm `php artisan about` and migration commands run.
2. **Data model.** Add migrations and Eloquent models for customers/staff (`users` with a role), gym plans, pending orders, paid memberships, check-ins and trial requests. Use integer VND amounts. Add foreign keys, uniqueness for order-to-membership, and seeded demo plans.
3. **Membership rules.** Write failing tests for end-of-month expiry, delayed manual payment, overlapping paid periods, repeated confirmation and insufficient payment. Implement the smallest Laravel service/controller logic that passes. Recheck overlap and create paid membership within one database transaction.
4. **Account and staff rules.** Implement registration/login/logout with Laravel hashing/session auth, uniqueness validation, and customer/staff authorization. Write feature tests for customer data isolation and staff-only payment confirmation.
5. **Attendance rules.** Write failing tests for unpaid/future/expired membership, one open visit, checkout and manual close after a forgotten checkout. Implement check-in/check-out service and staff endpoints.
6. **Backend verification.** Run migrations and feature tests on MySQL. Verify a complete demo path from account to payment to check-in. Document setup and legacy field mapping in `laravel/README.md`.
7. **UX/UI preview gate.** Read the requested anti-ai-slop-ui and Anthropic frontend-design skills. Produce a design brief, tokens and a visual preview for the public package page, customer membership view and staff check-in view. Show the preview to the user and wait for explicit acceptance **before writing final Blade/CSS UI**.
8. **After approval.** Implement responsive Blade screens from the approved direction, connect them to backend actions, review keyboard/mobile states and run the full tests again.

## Done criteria

- MySQL migrations and seeds run from a clean database.
- Tests cover the paid-only membership rule, date boundaries, overlap, repeat confirmation, customer data isolation and check-in.
- No customer action can mark an order paid or alter another customer's records.
- The C# source remains intact; new work lives under `laravel/` and documentation.
- UI is implemented only after the preview is accepted.

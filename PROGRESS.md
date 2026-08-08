# SplitEven — Progress Tracker

> **AGENT**: Read this file FIRST at the start of every session. Update it after completing each step. This is your memory between sessions.

---

## Current Status

**Last completed phase**: Phase 12 (ALL PHASES COMPLETE ✅)
**Next phase to work on**: None — app is feature-complete
**Last updated**: Aug 6, 2026
**Current model**: _(not yet)_
**Blockers**: None

---

## Phase Progress

### Phase 0: Project Setup & Dependencies
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Verified Laravel project structure (Laravel 13.24.0)
  - [x] shadcn-vue already initialized (`npx shadcn-vue@latest init` completed)
  - [x] Installed shadcn-vue components (button, input, label, card, dialog, sheet, select, tabs, avatar, badge, separator, toast, dropdown-menu, alert)
  - [x] Installed lucide-vue-next
  - [x] .env configured with database credentials (MySQL, localhost:3306, database=spliteven)
  - [x] Database `spliteven` exists and is accessible
  - [x] `npm run build` succeeds (built in 1.05s)
  - [x] `php artisan migrate` runs default migrations successfully
- **Notes**: Project is a Laravel starter kit with Inertia + Vue, all Phase 0 prerequisites verified and working. Ready to proceed to Phase 1 (Database Migrations).

### Phase 1: Database Migrations
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Created migration: add_username_to_users_table (adds username VARCHAR(30) UNIQUE and currency VARCHAR(3) DEFAULT 'CAD')
  - [x] Created migration: create_friendships_table (requester_id, addressee_id, status ENUM with unique constraint)
  - [x] Created migration: create_groups_table (name, created_by FK to users)
  - [x] Created migration: create_group_members_table (group_id, user_id pivot table with unique constraint)
  - [x] Created migration: create_expenses_table (group_id nullable, description, amount DECIMAL(12,2), paid_by, split_type, expense_date)
  - [x] Created migration: create_expense_participants_table (expense_id, user_id, share_value DECIMAL(12,4) nullable, owed_amount DECIMAL(12,2))
  - [x] Created migration: create_settlements_table (payer_id, payee_id, group_id nullable, amount, note, settled_at)
  - [x] All migrations ran successfully (`php artisan migrate` completed in 0.631s)
  - [x] Verified table columns match schema spec
- **Notes**: All 7 custom migrations created and executed successfully. Database schema now supports full SplitEven functionality including circles, groups, expenses with 4 split types, and settlements. Next phase: Phase 2 (Models & Relationships).

### Phase 2: Models & Relationships
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Created Friendship model with requester() and addressee() relationships
  - [x] Created Group model with creator(), members(), and expenses() relationships
  - [x] Created Expense model with group(), payer(), creator(), and participants() relationships
  - [x] Created ExpenseParticipant model with expense() and user() relationships
  - [x] Created Settlement model with payer(), payee(), and group() relationships
  - [x] Modified User model: added username and currency to fillable, added friendshipsSent(), friendshipsReceived(), friends(), groups(), expensesPaid(), expenseParticipations(), settlementsPaid(), settlementsReceived() methods
  - [x] Updated UserFactory: added username (fake()->userName()) and currency (random USD/CAD/EUR)
  - [x] Created FriendshipFactory with accepted(), pending(), and rejected() states
  - [x] Verified with tinker: can create users with username, friendships work, models load correctly
- **Notes**: All Eloquent models created with proper relationships. User model has both fillable fields (username, currency) and relationship methods. Factories configured for testing. Next phase: Phase 3 (Authentication Enhancement - username field).

### Phase 1: Database Migrations
- **Status**: ⬜ NOT STARTED
- **Started at**: —
- **Completed at**: —
- **Steps**:
  - [ ] Created migration: add_username_to_users_table
  - [ ] Created migration: create_friendships_table
  - [ ] Created migration: create_groups_table
  - [ ] Created migration: create_group_members_table
  - [ ] Created migration: create_expenses_table
  - [ ] Created migration: create_expense_participants_table
  - [ ] Created migration: create_settlements_table
  - [ ] All migrations ran successfully (`php artisan migrate`)
  - [ ] Verified table columns match schema spec
- **Notes**: —

### Phase 2: Models & Relationships
- **Status**: ⬜ NOT STARTED
- **Started at**: —
- **Completed at**: —
- **Steps**:
  - [ ] Created Friendship model
  - [ ] Created Group model
  - [ ] Created Expense model
  - [ ] Created ExpenseParticipant model
  - [ ] Created Settlement model
  - [ ] Modified User model (added relationships + fillable)
  - [ ] Modified UserFactory (added username + currency)
  - [ ] Created FriendshipFactory
  - [ ] Verified with tinker: can create users, friendships, and query friends()
- **Notes**: —

### Phase 3: Authentication Enhancement
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Added username validation to registration (usernameRules() in ProfileValidationRules trait, wired into CreateNewUser)
  - [x] Added username to User::create() in registration (lowercased before storing)
  - [x] Added username field to Register.vue (between name and email, tabindex updated)
  - [x] Added client-side validation hint: "lowercase letters, numbers, underscores only"
  - [x] Added real-time availability check (debounced 500ms POST to /check-username via Inertia useHttp)
  - [x] Added /check-username availability route (outside auth middleware)
  - [x] Registration works with username (verified in browser: user created with username jane_doe)
  - [x] Login still works with email (verified in browser: redirects to dashboard)
  - [x] Duplicate username rejected (verified: server error "The username has already been taken." + real-time "taken" indicator)
  - [x] Invalid format rejected (verified: "The username field format is invalid.")
  - [x] 10 new tests: registration with username, username required/min/max/format, duplicate rejection, uppercase rejected, login with email, check-username available/unavailable/case-insensitive/validation
- **Notes**: This starter kit uses Fortify (not Breeze), so the "registration controller" is `app/Actions/Fortify/CreateNewUser.php`. The shared `ProfileValidationRules` trait got a new `usernameRules()` method but it is NOT wired into profileRules() yet — profile updates don't have a username field (planned for a later phase). IMPORTANT PITFALL: `php artisan wayfinder:generate` CLI drops the `.form` route variants that pages use — always run it with `--with-form` to match the Vite plugin's `formVariants: true` config.

### Phase 4: Circle (Friends) System
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Created CircleController (index, search, invite, accept, reject, remove)
  - [x] Added circle routes to web.php (circle.index, circle.search, circle.invite, circle.accept, circle.reject, circle.remove)
  - [x] Created Pages/Circle/Index.vue (search button, pending received/sent sections, friends list, empty state)
  - [x] Created Components/SearchSheet.vue (debounced 300ms search via fetch, invite buttons, friendship status)
  - [x] Created Components/UserAvatar.vue (colored initials circle, deterministic color by name)
  - [x] Created Components/EmptyState.vue (icon + title + description)
  - [x] 10 tests: invite, accept, reject, remove, duplicate invite, self-invite, authorization, index data, search, search excludes self
- **Notes**: Friends list includes friendship_id (needed for remove route which takes Friendship model binding). Circle tests all pass. 60 tests total passing.
- **Files**: `app/Http/Controllers/CircleController.php`, `routes/web.php` (modified), `resources/js/pages/Circle/Index.vue`, `resources/js/components/SearchSheet.vue`, `resources/js/components/UserAvatar.vue`, `resources/js/components/EmptyState.vue`, `tests/Feature/CircleTest.php`

### Phase 5: Groups
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Created GroupController (index, create, store, show, addMember, removeMember)
  - [x] Added group routes (resource + members.add/members.remove)
  - [x] Created Pages/Groups/Index.vue (group cards grid, member avatars, create button)
  - [x] Created Pages/Groups/Create.vue (name + friend multi-select from Circle)
  - [x] Created Pages/Groups/Show.vue (members, balances, simplified debts, expenses, add-member dialog)
  - [x] 9 tests: create with friends, require member, reject non-circle, list, authorization, add member, creator-only, remove member
- **Notes**: `User::friends()` returns an ARRAY (not Eloquent collection) — must wrap with `collect()` before calling `->map()`/`->pluck()`. Group show passes `addableFriends` (creator's circle minus existing members) for the add-member dialog.
- **Files**: `app/Http/Controllers/GroupController.php`, `routes/web.php` (modified), `resources/js/pages/Groups/Index.vue`, `resources/js/pages/Groups/Create.vue`, `resources/js/pages/Groups/Show.vue`, `tests/Feature/GroupTest.php`

### Phase 6: Expense Backend
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Copied SplitCalculator.php, BalanceCalculator.php, DebtSimplifier.php to app/Services/ (exact code from plan Section 5)
  - [x] Created StoreExpenseRequest (validation for all 4 split types)
  - [x] Created ExpenseController (create, store, show, destroy)
  - [x] Added expense routes to web.php (resource, except edit)
  - [x] Verified SplitCalculator in tinker: equal [33.33, 33.33, 33.34], shares [50, 25, 25], percentage [100, 60, 40], exact [60, 40] — all match plan expectations
  - [x] 8 tests: create equal split, unauthenticated blocked, shares split, percentage split, exact validation, payer must be participant, group membership check, view authz, delete authz
- **Notes**: Built the expense backend early (before Group page finished) because Groups/Show.vue imports the expenses route. `match` expression used for split dispatch. All splits computed via bcmath in SplitCalculator (no float drift).
- **Files**: `app/Services/SplitCalculator.php`, `app/Services/BalanceCalculator.php`, `app/Services/DebtSimplifier.php`, `app/Http/Requests/StoreExpenseRequest.php`, `app/Http/Controllers/ExpenseController.php`, `routes/web.php` (modified), `tests/Feature/ExpenseFlowTest.php`

### Phase 7: Expense Frontend
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Created Pages/Expenses/Create.vue (description, amount, date, context selector friend/group, paid-by pills, split type selector)
  - [x] Implemented reactive split calculations (computed: equal/shares/percentage/exact with last-person remainder)
  - [x] Implemented form submission with data transform (share_value/amount per split type)
  - [x] Created Pages/Expenses/Show.vue (detail, payer, split breakdown, delete for creator)
  - [x] Tested in browser: $100 equal split with friend → "Split equally among 2 people — CA$50.00 each" → submitted → DB shows 50/50
- **Notes**: reka-ui Select returns STRING values — must Number() them when building participants (Map uses number keys). `useForm` used directly (not Form component) because participants array is dynamic. Share/amount inputs use string refs (never null at runtime) to satisfy Input v-model typing.
- **Files**: `resources/js/pages/Expenses/Create.vue`, `resources/js/pages/Expenses/Show.vue`

### Phase 8: Settlements
- **Status**: ⬜ NOT STARTED
- **Started at**: —
- **Completed at**: —
- **Steps**:
  - [ ] Created SettlementController
  - [ ] Implemented index(), create(), store()
  - [ ] Added settlement routes to web.php
  - [ ] Created Pages/Settlements/Create.vue
  - [ ] Tested: record payment, verify in DB
- **Notes**: —

### Phase 9: Dashboard & Balances
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Created DashboardController (balance aggregation via BalanceCalculator, totals, sorting)
  - [x] Replaced dashboard Inertia placeholder with controller route
  - [x] Created Pages/Dashboard.vue (summary card with owe/owed/net, balances list, empty state)
  - [x] Created Components/BalanceCard.vue (avatar, name, AmountDisplay, links to settle-up pre-filled)
  - [x] Created Components/AmountDisplay.vue (red owe / green owes-you / neutral settled)
  - [x] 8 tests: pairwise balance, settlement reduces, full settlement zeroes, group balances, all-balances aggregation, dashboard states
- **Notes**: Sign conventions: positive balance = you owe them (red), negative = they owe you (green). Dashboard test replaced the old starter placeholder test.
- **Files**: `app/Http/Controllers/DashboardController.php`, `routes/web.php` (modified), `resources/js/pages/Dashboard.vue` (rewritten), `resources/js/components/BalanceCard.vue`, `resources/js/components/AmountDisplay.vue`, `tests/Feature/BalanceCalculatorTest.php`, `tests/Feature/DashboardTest.php` (rewritten)

### Phase 10: Activity Feed
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Created ActivityController (expenses + settlements merged, sorted desc, take 50)
  - [x] Added activity route to web.php
  - [x] Created Pages/Activity/Index.vue (chronological feed, receipt icon for expenses, coins for settlements, group badges, expense items link to detail)
  - [x] 4 tests: expense shown to payer, expense shown to participant, settlement shown, empty for unrelated user
- **Notes**: PITFALL: `->get()->map()` returns an Eloquent Collection — `->merge()` with a base Collection throws "Call to a member function getKey() on array". Fix: wrap both in `collect()` before merging.
- **Files**: `app/Http/Controllers/ActivityController.php`, `routes/web.php` (modified), `resources/js/pages/Activity/Index.vue`, `tests/Feature/ActivityTest.php`

### Phase 11: Layout, Navigation & UI Polish
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Created Components/BottomNav.vue (mobile bottom nav: Home, Circle, prominent Add button, Groups, Activity)
  - [x] Wired BottomNav into AppSidebarLayout (hidden md:+, content gets pb-20 on mobile)
  - [x] Updated AppSidebar nav items (Dashboard, Circle, Add Expense, Groups, Settlements, Activity)
  - [x] Created Components/UserAvatar.vue (already in Phase 4 — deterministic color by name)
  - [x] Created Components/EmptyState.vue (already in Phase 4 — used on all empty pages)
  - [x] Fixed ISO date display on Groups/Show and Expenses/Show (formatDate helper)
  - [x] Verified in browser: desktop (1280px) shows sidebar + hides bottom nav; mobile CSS classes correct; sidebar shows all 6 nav items; no console errors
- **Notes**: Starter kit's sidebar was already `hidden md:block` — only needed to add nav items + BottomNav for mobile. Center Add button is raised/colored per plan.
- **Files**: `resources/js/components/BottomNav.vue`, `resources/js/components/AppSidebar.vue` (modified), `resources/js/layouts/app/AppSidebarLayout.vue` (modified), `resources/js/pages/Groups/Show.vue` + `resources/js/pages/Expenses/Show.vue` (date fix)

### Phase 12: Testing Suite
- **Status**: ✅ COMPLETED
- **Started at**: Aug 6, 2026
- **Completed at**: Aug 6, 2026
- **Steps**:
  - [x] Created tests/Unit/SplitCalculatorTest.php (9 tests: equal/shares/percentage/exact + rounding + rejections)
  - [x] Created tests/Unit/DebtSimplifierTest.php (5 tests: basic, 3-person, empty, settled, minimization)
  - [x] Created tests/Feature/BalanceCalculatorTest.php (5 tests: pairwise, settlement reduces, full zeroes, group balances, aggregation)
  - [x] All prior feature tests passing: CircleTest (10), GroupTest (9), ExpenseFlowTest (9), SettlementTest (4), ActivityTest (4), DashboardTest (3), auth tests
  - [x] ALL tests pass: 105 tests, 378 assertions (`php artisan test --compact`)
  - [x] pint, eslint, vue-tsc, npm run build all clean
- **Notes**: Pest (not PHPUnit) — the plan's PHPUnit examples were converted to Pest `test()` syntax. Pest's `toBeWithDelta`/`toBeLessThanOrEqualTo` don't exist in this version — use `round()` + `toBe()`, and `toBeLessThan()`.
- **Files**: `tests/Unit/SplitCalculatorTest.php`, `tests/Unit/DebtSimplifierTest.php`, `tests/Feature/BalanceCalculatorTest.php`

---

## 🎉 PROJECT COMPLETE

All 13 phases (0-12) implemented. SplitEven is feature-complete per the master plan:
- Email + username registration with availability check
- Circle friend system (search/invite/accept/reject/remove)
- Groups with members, balances, simplified debts
- Expenses with 4 split types (equal/shares/percentage/exact)
- Settlements (payments between users)
- Dashboard with net balances
- Activity feed
- Mobile bottom nav + desktop sidebar
- 105 tests passing, all lint/type/build checks green

---

## Issues Log

> Record any issues, workarounds, or deviations from the plan here.

| # | Phase | Description | Resolution | Status |
|---|---|---|---|---|
| — | — | — | — | — |

---

## Architecture Decisions Log

> Record any decisions made during implementation that deviate from the plan.

| # | Decision | Reason | Phase |
|---|---|---|---|
| — | — | — | — |

---

## Files Modified/Created Log

> Track every file created or modified per phase so context is clear on resume.

### Phase 0
- _Project setup verified, all dependencies installed_

### Phase 1
- `database/migrations/2026_08_06_223021_add_username_to_users_table.php`
- `database/migrations/2026_08_06_223103_create_friendships_table.php`
- `database/migrations/2026_08_06_223110_create_groups_table.php`
- `database/migrations/2026_08_06_223118_create_group_members_table.php`
- `database/migrations/2026_08_06_223125_create_expenses_table.php`
- `database/migrations/2026_08_06_223133_create_expense_participants_table.php`
- `database/migrations/2026_08_06_223140_create_settlements_table.php`

All 7 migrations executed successfully via `php artisan migrate --force`.

### Phase 2
- `app/Models/Friendship.php`
- `app/Models/Group.php`
- `app/Models/Expense.php`
- `app/Models/ExpenseParticipant.php`
- `app/Models/Settlement.php`
- `app/Models/User.php` (modified - added username, currency to fillable + 8 relationship methods)
- `database/factories/UserFactory.php` (modified - added username and currency)
- `database/factories/FriendshipFactory.php`

All models created with proper relationships. Factories configured for testing.

### Phase 3
- `app/Actions/Fortify/CreateNewUser.php` (modified - added username validation + User::create with username)
- `app/Concerns/ProfileValidationRules.php` (modified - added usernameRules() method)
- `routes/web.php` (modified - added POST /check-username route)
- `resources/js/Pages/Auth/Register.vue` (modified - added username field + debounced availability check)
- `resources/js/routes/*` (regenerated with `--with-form` - adds checkUsername + restores .form variants)
- `tests/Feature/Auth/RegistrationTest.php` (updated - 8 username registration tests)
- `tests/Feature/Auth/UsernameAvailabilityTest.php` (new - 4 check-username route tests)

Registration with username verified in browser. All 50 tests pass. `vue-tsc`, `eslint`, `pint`, `npm run build` all clean.

### Phase 4
- _(none yet)_

### Phase 5
- _(none yet)_

### Phase 6
- _(none yet)_

### Phase 7
- _(none yet)_

### Phase 8
- _(none yet)_

### Phase 9
- `app/Http/Controllers/DashboardController.php`
- `routes/web.php` (modified - dashboard route now uses controller)
- `resources/js/pages/Dashboard.vue` (rewritten from starter placeholder)
- `resources/js/components/BalanceCard.vue`
- `resources/js/components/AmountDisplay.vue`
- `tests/Feature/BalanceCalculatorTest.php`
- `tests/Feature/DashboardTest.php` (rewritten)

### Phase 10
- `app/Http/Controllers/ActivityController.php`
- `routes/web.php` (modified - added activity route)
- `resources/js/pages/Activity/Index.vue`
- `tests/Feature/ActivityTest.php`

### Phase 11
- `resources/js/components/BottomNav.vue`
- `resources/js/components/AppSidebar.vue` (modified - app nav items)
- `resources/js/layouts/app/AppSidebarLayout.vue` (modified - BottomNav + padding)
- `resources/js/pages/Groups/Show.vue` (date formatting)
- `resources/js/pages/Expenses/Show.vue` (date formatting)

### Phase 12
- `tests/Unit/SplitCalculatorTest.php`
- `tests/Unit/DebtSimplifierTest.php`
- `tests/Feature/BalanceCalculatorTest.php`

---

## Final Verification Summary (Aug 6, 2026)

- `php artisan test --compact` → **105 passed (378 assertions)**
- `vendor/bin/pint` → clean
- `npx eslint resources/js` → clean
- `npx vue-tsc --noEmit` → clean
- `npm run build` → succeeds
- Browser E2E verified: registration, login, dashboard balances (mathematically verified: $115 owed to Alice after dinner+hotel+settlement), group detail with balances + suggested payments ($95/$95), activity feed, desktop sidebar + mobile bottom nav, zero console errors

# Graph Report - magazyn-wms  (2026-11-08)

## Corpus Check
- 289 files · ~117,624 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 35 file(s) not represented in the graph (top: (none) 19, .csv 10, .example 1)

## Summary
- 2322 nodes · 3986 edges · 233 communities (138 shown, 95 thin omitted)
- Extraction: 95% EXTRACTED · 5% INFERRED · 0% AMBIGUOUS · INFERRED: 204 edges (avg confidence: 0.87)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- .addProduct
- App\Models\User
- WebhookDispatcher
- package.json
- Illuminate\Foundation\Testing\RefreshDatabase
- scanner.js
- environment
- ScannerDevice
- RackService
- UserFactory.php
- services
- Documents/Show.php
- Rack
- Plan
- WarehouseException
- .displayNumber
- Illuminate\Database\Eloquent\Model
- Ability
- Cloud CLI
- ScannerService
- app.js
- Controller
- Index
- App\Models\Document
- init
- Documents/Index.php
- WithPerPage
- Product
- ScanEvent
- db
- .createDraft
- Unit
- Illuminate\Database\Migrations\Migration
- Cloud CLI
- Livewire\Component
- Document
- Devices.php
- Livewire development
- RackService.php
- Contractors/Index.php
- ScannerApiTest
- Illuminate\Http\Request
- 2026_11_07_090000_create_roles_and_permissions_tables.php
- documents/show.blade.php
- bootstrap/app.php
- App\Livewire\Layout\Plan
- Livewire development
- Regał WMS
- plan.blade.php
- App\Models\Product
- composer.json
- require
- scripts
- Database performance best practices
- Index
- require-dev
- StockExportController.php
- Index
- DocumentType
- init
- services
- config
- What's Possible
- What's Possible
- RacksTest.php
- m0-test-suites.mjs
- Illuminate\Database\Schema\Blueprint
- m1-scan-latency.mjs
- Role
- m4-offline-queue.mjs
- .make
- psr-4
- 2026_11_08_100000_create_racks_tables.php
- layouts/app.blade.php
- webhooks/index.blade.php
- AGENTS.md
- CLAUDE.md
- products/index.blade.php
- pagination.blade.php
- autoload-dev
- extra
- log
- contractors/index.blade.php
- roles/index.blade.php
- users/index.blade.php
- warehouses/index.blade.php
- clearFilters
- clearTarget
- entrypoint.sh
- 403.blade.php
- guest.blade.php
- devices.blade.php
- Index
- 0001_01_01_000000_create_users_table.php
- Illuminate\Support\Facades\Schema
- 0001_01_01_000002_create_jobs_table.php
- 2026_11_07_100100_create_documents_tables.php
- common.mjs
- m2-live-propagation.mjs
- SendWebhook
- Queue and job best practices
- Process
- Process
- .junie/skills/testing-best-practices/SKILL.md
- m3-concurrent-approve.mjs
- Architecture best practices
- Security best practices
- Tailwind CSS development
- Architecture best practices
- Security best practices
- Tailwind CSS development
- WebhookEvent
- Index
- Detection checklist
- Advanced query best practices
- Events and notifications best practices
- Migration best practices
- Audyt end-to-end – Regał WMS
- m3_worker.php
- Advanced query best practices
- Events and notifications best practices
- Migration best practices
- Caching best practices
- Eloquent best practices
- Queue and job best practices
- Caching best practices
- Eloquent best practices
- Blade and view best practices
- Error handling best practices
- Task scheduling best practices
- Endpoint tests
- Assertions
- Blade and view best practices
- Error handling best practices
- Task scheduling best practices
- Endpoint tests
- Index
- DocumentService
- App\Support\Live
- .claude/skills/laravel-best-practices/SKILL.md
- Collection best practices
- HTTP client best practices
- Mail best practices
- Routing and controller best practices
- Convention and style best practices
- Validation and forms best practices
- Warehouse
- Fakes, mocks, and determinism
- Test suite performance
- .junie/skills/laravel-best-practices/SKILL.md
- Collection best practices
- HTTP client best practices
- Mail best practices
- Routing and controller best practices
- Convention and style best practices
- Validation and forms best practices
- Assertions
- Fakes, mocks, and determinism
- Test suite performance
- Reviewing tests
- DocumentNumberGenerator
- Index
- Configuration best practices
- Naming and structure
- Reviewing tests
- Configuration best practices
- Naming and structure
- Factories and test data
- Testing best practices
- Testing best practices
- DocumentPayload.php
- User
- .claude/skills/testing-best-practices/SKILL.md
- Controller
- DocumentStatus
- Dashboard
- Form
- .sign
- run-all.sh

## God Nodes (most connected - your core abstractions)
1. `Document` - 60 edges
2. `DocumentType` - 51 edges
3. `User` - 40 edges
4. `DocumentService` - 39 edges
5. `Product` - 38 edges
6. `Ability` - 34 edges
7. `Warehouse` - 33 edges
8. `Show` - 32 edges
9. `environment` - 29 edges
10. `environment` - 29 edges

## Surprising Connections (you probably didn't know these)
- `Role i uprawnienia (RBAC)` --references--> `Ability`  [INFERRED]
  README.md → app/Enums/Ability.php
- `Follow project naming conventions` --references--> `User`  [INFERRED]
  .claude/skills/laravel-best-practices/rules/style.md → app/Models/User.php
- `Follow project naming conventions` --references--> `User`  [INFERRED]
  .junie/skills/laravel-best-practices/rules/style.md → app/Models/User.php
- `Zależności od środowiska i bazy danych` --references--> `DocumentNumberGenerator`  [INFERRED]
  tests/perf/README.md → app/Services/Documents/DocumentNumberGenerator.php
- `Test class and methods` --references--> `TestCase`  [INFERRED]
  .claude/skills/testing-best-practices/rules/naming.md → tests/TestCase.php

## Import Cycles
- None detected.

## Communities (233 total, 95 thin omitted)

### Community 1 - "App\Models\User"
Cohesion: 0.24
Nodes (5): {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 2 - "WebhookDispatcher"
Cohesion: 0.24
Nodes (4): WebhookDelivery, WebhookEndpoint, {closure#1}(), WebhookDispatcher

### Community 3 - "package.json"
Cohesion: 0.06
Nodes (36): dependencies, alpinejs@^3.17.5, html5-qrcode@^2.3.8, devDependencies, concurrently@^10.0.3, laravel-echo@^2.6.3, laravel-vite-plugin@^3.1, @playwright/test@^1.55.0 (+28 more)

### Community 4 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.13
Nodes (4): PagesTest, RolesAndPermissionsTest, WebhookTest, TestCase

### Community 5 - "scanner.js"
Cohesion: 0.11
Nodes (32): api(), ApiError, chooseTarget(), enqueue(), enterScanScreen(), feedback(), flushQueue(), forgetDevice() (+24 more)

### Community 6 - "environment"
Cohesion: 0.26
Nodes (34): environment, APP_DEBUG, APP_ENV, APP_KEY, APP_NAME, APP_URL, BROADCAST_CONNECTION, CACHE_STORE (+26 more)

### Community 7 - "ScannerDevice"
Cohesion: 0.08
Nodes (5): DocumentItemsChanged, LayoutChanged, ScannerPaired, ScanReceived, ScannerDevice

### Community 8 - "RackService"
Cohesion: 0.13
Nodes (9): {closure#1}(), RackService, Control mass assignment, Control mass assignment, {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}() (+1 more)

### Community 9 - "UserFactory.php"
Cohesion: 0.07
Nodes (8): ContractorType, Both, Customer, Supplier, ContractorFactory, ProductFactory, UserFactory, WarehouseFactory

### Community 10 - "services"
Cohesion: 0.11
Nodes (23): depends_on, env_file, image, restart, init, path, required, condition (+15 more)

### Community 11 - "Documents/Show.php"
Cohesion: 0.08
Nodes (18): {closure#1}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+10 more)

### Community 12 - "Rack"
Cohesion: 0.15
Nodes (5): LocationLabelsController, ProductLocation, Rack, {closure#12}(), {closure#3}()

### Community 13 - "Plan"
Cohesion: 0.13
Nodes (3): {closure#3}(), {closure#4}(), Plan

### Community 15 - ".displayNumber"
Cohesion: 0.23
Nodes (3): DocumentPdfController, AuthenticateScannerDevice, EnsureUserIsActive

### Community 17 - "Ability"
Cohesion: 0.10
Nodes (17): Ability, ContractorsManage, DocumentsApprove, DocumentsCancel, DocumentsCreate, DocumentsView, InventoryApprove, LayoutManage (+9 more)

### Community 18 - "Cloud CLI"
Cohesion: 0.06
Nodes (32): Adding a cache to an existing environment, Adding a database to an existing environment, Checklists for Multi-Step Operations, Custom domain setup, Full environment setup (app + database + cache + domain), New app from scratch, Sending email with Resend, Application Setup (+24 more)

### Community 20 - "app.js"
Cohesion: 0.13
Nodes (13): applyTheme(), choose(), chooseActive(), close(), down(), isSelected(), move(), openList() (+5 more)

### Community 21 - "Controller"
Cohesion: 0.16
Nodes (4): Controller, {closure#4}(), LabelsController, ScannerAppController

### Community 25 - "init"
Cohesion: 0.14
Nodes (18): VITE_REVERB_APP_KEY, args, context, target, build, tests, web, build (+10 more)

### Community 28 - "Product"
Cohesion: 0.10
Nodes (4): Contractor, {closure#1}(), Product, {closure#1}()

### Community 30 - "db"
Cohesion: 0.12
Nodes (17): environment, healthcheck, image, ports, restart, volumes, PGTZ, POSTGRES_DB (+9 more)

### Community 31 - ".createDraft"
Cohesion: 0.19
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), InventoryTest

### Community 33 - "Unit"
Cohesion: 0.13
Nodes (10): Unit, Kilogram, Liter, Meter, Package, Pallet, Piece, Set (+2 more)

### Community 35 - "Cloud CLI"
Cohesion: 0.06
Nodes (32): Adding a cache to an existing environment, Adding a database to an existing environment, Checklists for Multi-Step Operations, Custom domain setup, Full environment setup (app + database + cache + domain), New app from scratch, Sending email with Resend, Application Setup (+24 more)

### Community 36 - "Livewire\Component"
Cohesion: 0.12
Nodes (5): Login, InventoryBanner, {closure#1}(), QuickFind, ScannerStatus

### Community 37 - "Document"
Cohesion: 0.09
Nodes (7): {closure#1}(), {closure#10}(), Document, Stock, StockMovement, {closure#3}(), StockService

### Community 39 - "Livewire development"
Cohesion: 0.08
Nodes (24): Component-scoped interceptors, Intercept messages, Intercept requests, Interceptor system (v4), Livewire 4 JavaScript integration, Magic properties, Alpine and JavaScript, Basic usage (+16 more)

### Community 40 - "RackService.php"
Cohesion: 0.15
Nodes (5): {closure#11}(), {closure#6}(), {closure#10}(), {closure#4}(), {closure#8}()

### Community 43 - "Illuminate\Http\Request"
Cohesion: 0.34
Nodes (3): ScannerController, {closure#1}(), {closure#2}()

### Community 44 - "2026_11_07_090000_create_roles_and_permissions_tables.php"
Cohesion: 0.20
Nodes (7): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), seedDefaults(), up()

### Community 45 - "documents/show.blade.php"
Cohesion: 0.20
Nodes (9): addProduct, approve, cancelDocument, deleteDraft, openHeader, prefill, removeItem, setCounted (+1 more)

### Community 46 - "bootstrap/app.php"
Cohesion: 0.24
Nodes (3): {closure#1}(), {closure#4}(), {closure#5}()

### Community 47 - "App\Livewire\Layout\Plan"
Cohesion: 0.17
Nodes (8): {closure#1}(), {closure#10}(), {closure#2}(), {closure#5}(), {closure#9}(), {closure#14}(), {closure#2}(), {closure#2}()

### Community 48 - "Livewire development"
Cohesion: 0.08
Nodes (24): Component-scoped interceptors, Intercept messages, Intercept requests, Interceptor system (v4), Livewire 4 JavaScript integration, Magic properties, Alpine and JavaScript, Basic usage (+16 more)

### Community 49 - "Regał WMS"
Cohesion: 0.20
Nodes (10): API skanera, Funkcje, Regał WMS, Struktura, Telefon jako skaner, Testy, Uruchomienie lokalne (bez Dockera), Uruchomienie w Dockerze (zalecane) (+2 more)

### Community 50 - "plan.blade.php"
Cohesion: 0.22
Nodes (8): openConfigurator, openPlanSize, openRackForm, openSlot, place, removePlacement, rotateRack, selectRack

### Community 52 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 53 - "require"
Cohesion: 0.22
Nodes (9): require, bacon/bacon-qr-code, barryvdh/laravel-dompdf, laravel/framework, laravel/reverb, laravel/tinker, livewire/livewire, mallardduck/blade-lucide-icons (+1 more)

### Community 54 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 55 - "Database performance best practices"
Cohesion: 0.09
Nodes (17): AppServiceProvider, Add indexes for measured query patterns, Count relationships without loading them, Database performance best practices, Eager load relationships before iterating, Keep queries out of Blade templates, Prevent lazy loading in development, Process large data sets incrementally (+9 more)

### Community 57 - "require-dev"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision (+1 more)

### Community 60 - "DocumentType"
Cohesion: 0.09
Nodes (10): DocumentType, INW, MM, PW, PZ, RW, WZ, {closure#3}() (+2 more)

### Community 61 - "init"
Cohesion: 0.15
Nodes (13): condition, db, command, depends_on, image, restart, init, tests-pg (+5 more)

### Community 62 - "services"
Cohesion: 0.10
Nodes (20): environment, image, ports, profiles, restart, ADMINER_DEFAULT_SERVER, name, services (+12 more)

### Community 63 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 64 - "What's Possible"
Cohesion: 0.11
Nodes (18): Authorizing Channels, Basic Usage, Broadcasting Helpers, Broadcasting Interfaces, Channel Authorization, Client-Side Features, Client-Side Setup, Common Pitfalls (+10 more)

### Community 65 - "What's Possible"
Cohesion: 0.10
Nodes (19): Authorizing Channels, Basic Usage, Broadcasting Helpers, Broadcasting Interfaces, Channel Authorization, Channel Types, Client-Side Features, Client-Side Setup (+11 more)

### Community 68 - "m0-test-suites.mjs"
Cohesion: 0.12
Nodes (14): clockOffset(), nowMs(), RESULTS_DIR, ROOT, runStamp(), e2e, e2eOut, e2eSummary (+6 more)

### Community 69 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.19
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}() (+2 more)

### Community 70 - "m1-scan-latency.mjs"
Cohesion: 0.11
Nodes (16): dockerLoadSnapshot(), ADD, addCodes, all, file, INTERVAL, latency, loadAfter (+8 more)

### Community 71 - "Role"
Cohesion: 0.14
Nodes (3): {closure#4}(), {closure#5}(), Role

### Community 72 - "m4-offline-queue.mjs"
Cohesion: 0.12
Nodes (11): Common errors, launch(), sleep(), USERS, apiSeries(), file, K, manualScan() (+3 more)

### Community 73 - ".make"
Cohesion: 0.20
Nodes (9): B. Controllers & routing, C. Authorization, Detection checklist, E. Architecture & organization, F. Frontend & views, G. Database & migrations, H. Testing, I. Responses & API resources (+1 more)

### Community 74 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 76 - "2026_11_08_100000_create_racks_tables.php"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 77 - "layouts/app.blade.php"
Cohesion: 0.40
Nodes (4): inventory-banner, quick-find, partials.head, scanner-status

### Community 78 - "webhooks/index.blade.php"
Cohesion: 0.40
Nodes (4): ping, create, edit, retry

### Community 79 - "AGENTS.md"
Cohesion: 0.08
Nodes (24): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Deployment, Do Things the Laravel Way, Documentation Files, Foundational Context (+16 more)

### Community 80 - "CLAUDE.md"
Cohesion: 0.08
Nodes (25): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Deployment, Do Things the Laravel Way, Documentation Files, Foundational Context (+17 more)

### Community 81 - "products/index.blade.php"
Cohesion: 0.50
Nodes (3): clearSelection, selectAllFiltered, togglePage

### Community 82 - "pagination.blade.php"
Cohesion: 0.50
Nodes (3): gotoPage, nextPage, previousPage

### Community 84 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 85 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 86 - "log"
Cohesion: 0.29
Nodes (3): Channel Types, BASE_URL, log()

### Community 142 - "0001_01_01_000000_create_users_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 143 - "Illuminate\Support\Facades\Schema"
Cohesion: 0.18
Nodes (4): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}()

### Community 144 - "0001_01_01_000002_create_jobs_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 145 - "2026_11_07_100100_create_documents_tables.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 146 - "common.mjs"
Cohesion: 0.19
Nodes (11): appContainer(), copyPhpScripts(), desktopContext(), login(), mobileContext(), pairingCode(), pairPhone(), PASSWORD (+3 more)

### Community 147 - "m2-live-propagation.mjs"
Cohesion: 0.13
Nodes (12): emulateNetwork(), approvalRows, APPROVALS, file, latency, offsets, PAUSE, REPS (+4 more)

### Community 149 - "SendWebhook"
Cohesion: 0.21
Nodes (3): SendWebhook, Handle terminal failure when needed, Handle terminal failure when needed

### Community 150 - "Queue and job best practices"
Cohesion: 0.22
Nodes (8): Back off transient failures, Batch jobs for group coordination, Configure time-based retry limits deliberately, Keep reservation time longer than execution time, Queue and job best practices, Rate limit external calls, Use Horizon for Redis queue operations, Use unique jobs for dispatch deduplication

### Community 151 - "Process"
Cohesion: 0.17
Nodes (11): Edge cases, Glob mapping, Ground rules (read before you start), Infer conventions, Process, Step 0: Orient, Step 1: Predefined sweep, Step 2: Open-ended pass (+3 more)

### Community 152 - "Process"
Cohesion: 0.17
Nodes (11): Edge cases, Glob mapping, Ground rules (read before you start), Infer conventions, Process, Step 0: Orient, Step 1: Predefined sweep, Step 2: Open-ended pass (+3 more)

### Community 153 - ".junie/skills/testing-best-practices/SKILL.md"
Cohesion: 0.17
Nodes (7): Built-in Laravel assertion methods, How to find test framework features, Security tests, Data providers, Each test makes its own data, Factories and test data, Record construction

### Community 154 - "m3-concurrent-approve.mjs"
Cohesion: 0.17
Nodes (10): dockerPhp(), saveCsv(), saveJson(), file, N, out, runsA, runsB (+2 more)

### Community 155 - "Architecture best practices"
Cohesion: 0.18
Nodes (11): Architecture best practices, Depend on contracts at boundaries, Extract focused business operations, Follow framework conventions, Inject required dependencies, Specify a deterministic sort order, Use atomic locks for race conditions, Use `Concurrency::run()` for parallel execution (+3 more)

### Community 156 - "Security best practices"
Cohesion: 0.18
Nodes (10): Apply cross-site request forgery protection, Audit dependencies, Authorize protected actions, Bind query parameters, Encrypt sensitive attributes when appropriate, Escape output in its context, Keep secrets out of application code, Rate limit sensitive endpoints (+2 more)

### Community 157 - "Tailwind CSS development"
Cohesion: 0.18
Nodes (10): Basic usage, Common pitfalls, CSS-first configuration, Dark mode, Documentation, Import syntax, Replaced utilities, Spacing (+2 more)

### Community 158 - "Architecture best practices"
Cohesion: 0.18
Nodes (11): Architecture best practices, Depend on contracts at boundaries, Extract focused business operations, Follow framework conventions, Inject required dependencies, Specify a deterministic sort order, Use atomic locks for race conditions, Use `Concurrency::run()` for parallel execution (+3 more)

### Community 159 - "Security best practices"
Cohesion: 0.18
Nodes (10): Apply cross-site request forgery protection, Audit dependencies, Authorize protected actions, Bind query parameters, Encrypt sensitive attributes when appropriate, Escape output in its context, Keep secrets out of application code, Rate limit sensitive endpoints (+2 more)

### Community 160 - "Tailwind CSS development"
Cohesion: 0.18
Nodes (10): Basic usage, Common pitfalls, CSS-first configuration, Dark mode, Documentation, Import syntax, Replaced utilities, Spacing (+2 more)

### Community 161 - "WebhookEvent"
Cohesion: 0.20
Nodes (6): WebhookEvent, DocumentApproved, DocumentCancelled, Ping, ScanReceived, StockLow

### Community 163 - "Detection checklist"
Cohesion: 0.15
Nodes (13): A. Validation & HTTP input, B. Controllers & routing, C. Authorization, D. Eloquent & models, Detection checklist, E. Architecture & organization, F. Frontend & views, G. Database & migrations (+5 more)

### Community 164 - "Advanced query best practices"
Cohesion: 0.20
Nodes (9): Advanced query best practices, Combine related counts with conditional aggregates, Compare `whereHas()` with an `IN` subquery, Consider a correlated subquery for has-many ordering, Create dynamic relationships with a subquery foreign key, Design composite indexes for the query, Measure two simple queries against one complex query, Reuse loaded parent models with `setRelation()` (+1 more)

### Community 165 - "Events and notifications best practices"
Cohesion: 0.20
Nodes (9): Cache event discovery during production deployment, Dispatch queued notifications after commit, Events and notifications best practices, Implement `HasLocalePreference` on notifiable models, Queue slow notifications, Rely on event discovery, Route notification channels to dedicated queues, Use on-demand notifications for non-user recipients (+1 more)

### Community 166 - "Migration best practices"
Cohesion: 0.20
Nodes (9): Define foreign-key constraints deliberately, Design indexes for real queries, Generate migrations with Artisan, Keep migrations focused, Make rollbacks honest, Migration best practices, Mirror defaults only when unsaved models need them, Stage changes that affect existing rows (+1 more)

### Community 167 - "Audyt end-to-end – Regał WMS"
Cohesion: 0.33
Nodes (4): Audyt end-to-end – Regał WMS, Metoda, Rekomendacje na kolejny etap (poza zakresem), Rozszerzenia wprowadzone po audycie

### Community 169 - "Advanced query best practices"
Cohesion: 0.20
Nodes (9): Advanced query best practices, Combine related counts with conditional aggregates, Compare `whereHas()` with an `IN` subquery, Consider a correlated subquery for has-many ordering, Create dynamic relationships with a subquery foreign key, Design composite indexes for the query, Measure two simple queries against one complex query, Reuse loaded parent models with `setRelation()` (+1 more)

### Community 170 - "Events and notifications best practices"
Cohesion: 0.20
Nodes (9): Cache event discovery during production deployment, Dispatch queued notifications after commit, Events and notifications best practices, Implement `HasLocalePreference` on notifiable models, Queue slow notifications, Rely on event discovery, Route notification channels to dedicated queues, Use on-demand notifications for non-user recipients (+1 more)

### Community 171 - "Migration best practices"
Cohesion: 0.20
Nodes (9): Define foreign-key constraints deliberately, Design indexes for real queries, Generate migrations with Artisan, Keep migrations focused, Make rollbacks honest, Migration best practices, Mirror defaults only when unsaved models need them, Stage changes that affect existing rows (+1 more)

### Community 172 - "Caching best practices"
Cohesion: 0.22
Nodes (8): Caching best practices, Configure failover cache stores in production, Consider `Cache::flexible()` for stale-while-revalidate, Use `Cache::add()` for atomic conditional writes, Use `Cache::memo()` to avoid redundant hits within an execution, Use `Cache::remember()` for cache-aside reads, Use cache tags to invalidate related groups, Use `once()` for in-process memoization

### Community 173 - "Eloquent best practices"
Cohesion: 0.22
Nodes (8): Apply global scopes sparingly, Cast date and time attributes, Define attribute casts, Define precise relationship types, Eloquent best practices, Keep application queries model-aware, Use local scopes for reusable queries, Use `whereBelongsTo()` for relationship queries

### Community 174 - "Queue and job best practices"
Cohesion: 0.22
Nodes (8): Back off transient failures, Batch jobs for group coordination, Configure time-based retry limits deliberately, Keep reservation time longer than execution time, Queue and job best practices, Rate limit external calls, Use Horizon for Redis queue operations, Use unique jobs for dispatch deduplication

### Community 175 - "Caching best practices"
Cohesion: 0.22
Nodes (8): Caching best practices, Configure failover cache stores in production, Consider `Cache::flexible()` for stale-while-revalidate, Use `Cache::add()` for atomic conditional writes, Use `Cache::memo()` to avoid redundant hits within an execution, Use `Cache::remember()` for cache-aside reads, Use cache tags to invalidate related groups, Use `once()` for in-process memoization

### Community 176 - "Eloquent best practices"
Cohesion: 0.22
Nodes (8): Apply global scopes sparingly, Cast date and time attributes, Define attribute casts, Define precise relationship types, Eloquent best practices, Keep application queries model-aware, Use local scopes for reusable queries, Use `whereBelongsTo()` for relationship queries

### Community 177 - "Blade and view best practices"
Cohesion: 0.25
Nodes (7): Blade and view best practices, Prefer components for explicit interfaces, Return Blade fragments for partial rendering, Share compatible view data with a view composer, Share parent component props with `@aware`, Use `$attributes->merge()` in component templates, Use `@pushOnce` for per-component scripts

### Community 178 - "Error handling best practices"
Cohesion: 0.25
Nodes (7): Add context to exception classes, Choose where to report and render exceptions, Define JSON rendering for API routes, Error handling best practices, Mark exceptions the handler should not report, Prevent duplicate reports of one exception instance, Throttle high-volume exception reports

### Community 179 - "Task scheduling best practices"
Cohesion: 0.25
Nodes (7): Bound work inside the task, Group shared configuration, Prevent unwanted overlap, Restrict tasks by environment, Run a task on one server, Run eligible commands in the background, Task scheduling best practices

### Community 180 - "Endpoint tests"
Cohesion: 0.25
Nodes (7): Endpoint coverage, Endpoint tests, How to write the test, Tenant isolation, Test authorization at the policy level, Testing validation, Which layer owns which case

### Community 181 - "Assertions"
Cohesion: 0.29
Nodes (6): Arrange, act, assert, Assert a known value, Assert the complete result, Assertions, How to find the correct assertion, Named response assertions

### Community 182 - "Blade and view best practices"
Cohesion: 0.25
Nodes (7): Blade and view best practices, Prefer components for explicit interfaces, Return Blade fragments for partial rendering, Share compatible view data with a view composer, Share parent component props with `@aware`, Use `$attributes->merge()` in component templates, Use `@pushOnce` for per-component scripts

### Community 183 - "Error handling best practices"
Cohesion: 0.25
Nodes (7): Add context to exception classes, Choose where to report and render exceptions, Define JSON rendering for API routes, Error handling best practices, Mark exceptions the handler should not report, Prevent duplicate reports of one exception instance, Throttle high-volume exception reports

### Community 184 - "Task scheduling best practices"
Cohesion: 0.25
Nodes (7): Bound work inside the task, Group shared configuration, Prevent unwanted overlap, Restrict tasks by environment, Run a task on one server, Run eligible commands in the background, Task scheduling best practices

### Community 185 - "Endpoint tests"
Cohesion: 0.25
Nodes (7): Endpoint coverage, Endpoint tests, How to write the test, Tenant isolation, Test authorization at the policy level, Testing validation, Which layer owns which case

### Community 187 - "DocumentService"
Cohesion: 0.16
Nodes (3): DocumentChanged, DocumentItem, DocumentService

### Community 188 - "App\Support\Live"
Cohesion: 0.29
Nodes (5): Live, Architektura, Model danych, Reguły gospodarki magazynowej, Role i uprawnienia (RBAC)

### Community 189 - ".claude/skills/laravel-best-practices/SKILL.md"
Cohesion: 0.29
Nodes (5): Consistency first, Decision rules, How to apply, Laravel best practices, Rule index

### Community 190 - "Collection best practices"
Cohesion: 0.29
Nodes (6): Choose between `cursor()` and `lazy()`, Collection best practices, Use `#[CollectedBy]` for custom collection classes, Use higher-order messages for simple operations, Use `lazyById()` when updating records while iterating, Use `toQuery()` for bulk operations on collections

### Community 191 - "HTTP client best practices"
Cohesion: 0.29
Nodes (6): Fake HTTP requests in tests, Handle errors explicitly, HTTP client best practices, Pool independent requests, Retry only safe operations, Set explicit timeouts

### Community 192 - "Mail best practices"
Cohesion: 0.29
Nodes (6): Assert the delivery mode, Dispatch queued mail after commit, Mail best practices, Queue slow mail delivery, Separate content and delivery tests, Use Markdown mailables when they fit

### Community 193 - "Routing and controller best practices"
Cohesion: 0.29
Nodes (6): Keep controllers focused on HTTP concerns, Organize controllers around resources, Routing and controller best practices, Scope nested bindings, Use implicit route model binding, Use resource routes for resourceful actions

### Community 194 - "Convention and style best practices"
Cohesion: 0.29
Nodes (6): Convention and style best practices, Follow project naming conventions, Keep presentation code maintainable, Prefer clear, idiomatic syntax, Use utilities when they clarify intent, Write comments that explain why

### Community 195 - "Validation and forms best practices"
Cohesion: 0.29
Nodes (6): Add cross-field validation after base rules, Express conditional rules clearly, Extract validation when it improves the boundary, Prefer readable rule syntax, Use only the validated data you intend, Validation and forms best practices

### Community 197 - "Fakes, mocks, and determinism"
Cohesion: 0.29
Nodes (7): Database, Fakes, mocks, and determinism, Framework fakes, How to isolate a dependency, Mocking, Outbound HTTP testing, Time and randomness

### Community 198 - "Test suite performance"
Cohesion: 0.33
Nodes (5): Global fakes, How to find a slow test, How to run the suite in parallel, Test environment, Test suite performance

### Community 199 - ".junie/skills/laravel-best-practices/SKILL.md"
Cohesion: 0.29
Nodes (5): Consistency first, Decision rules, How to apply, Laravel best practices, Rule index

### Community 200 - "Collection best practices"
Cohesion: 0.29
Nodes (6): Choose between `cursor()` and `lazy()`, Collection best practices, Use `#[CollectedBy]` for custom collection classes, Use higher-order messages for simple operations, Use `lazyById()` when updating records while iterating, Use `toQuery()` for bulk operations on collections

### Community 201 - "HTTP client best practices"
Cohesion: 0.29
Nodes (6): Fake HTTP requests in tests, Handle errors explicitly, HTTP client best practices, Pool independent requests, Retry only safe operations, Set explicit timeouts

### Community 202 - "Mail best practices"
Cohesion: 0.29
Nodes (6): Assert the delivery mode, Dispatch queued mail after commit, Mail best practices, Queue slow mail delivery, Separate content and delivery tests, Use Markdown mailables when they fit

### Community 203 - "Routing and controller best practices"
Cohesion: 0.29
Nodes (6): Keep controllers focused on HTTP concerns, Organize controllers around resources, Routing and controller best practices, Scope nested bindings, Use implicit route model binding, Use resource routes for resourceful actions

### Community 204 - "Convention and style best practices"
Cohesion: 0.29
Nodes (6): Convention and style best practices, Follow project naming conventions, Keep presentation code maintainable, Prefer clear, idiomatic syntax, Use utilities when they clarify intent, Write comments that explain why

### Community 205 - "Validation and forms best practices"
Cohesion: 0.29
Nodes (6): Add cross-field validation after base rules, Express conditional rules clearly, Extract validation when it improves the boundary, Prefer readable rule syntax, Use only the validated data you intend, Validation and forms best practices

### Community 206 - "Assertions"
Cohesion: 0.29
Nodes (6): Arrange, act, assert, Assert a known value, Assert the complete result, Assertions, How to find the correct assertion, Named response assertions

### Community 207 - "Fakes, mocks, and determinism"
Cohesion: 0.29
Nodes (7): Database, Fakes, mocks, and determinism, Framework fakes, How to isolate a dependency, Mocking, Outbound HTTP testing, Time and randomness

### Community 208 - "Test suite performance"
Cohesion: 0.29
Nodes (6): Common errors, Global fakes, How to find a slow test, How to run the suite in parallel, Test environment, Test suite performance

### Community 209 - "Reviewing tests"
Cohesion: 0.29
Nodes (6): Assertions, Coverage, Data and determinism, Names and structure, Reviewing tests, Test value

### Community 212 - "Configuration best practices"
Cohesion: 0.33
Nodes (5): Configuration best practices, Name repeated domain values, Protect production secrets, Read environment variables in configuration files, Use `App::environment()` for environment checks

### Community 213 - "Naming and structure"
Cohesion: 0.33
Nodes (5): File layout, Grouping, Naming and structure, Naming tests, Test class and methods

### Community 214 - "Reviewing tests"
Cohesion: 0.29
Nodes (6): Assertions, Coverage, Data and determinism, Names and structure, Reviewing tests, Test value

### Community 215 - "Configuration best practices"
Cohesion: 0.33
Nodes (5): Configuration best practices, Name repeated domain values, Protect production secrets, Read environment variables in configuration files, Use `App::environment()` for environment checks

### Community 216 - "Naming and structure"
Cohesion: 0.33
Nodes (5): File layout, Grouping, Naming and structure, Naming tests, Test class and methods

### Community 217 - "Factories and test data"
Cohesion: 0.40
Nodes (4): Data providers, Each test makes its own data, Factories and test data, Record construction

### Community 218 - "Testing best practices"
Cohesion: 0.40
Nodes (5): Consistency first, How to apply, Rule index, Testing best practices, What to test

### Community 219 - "Testing best practices"
Cohesion: 0.40
Nodes (5): Consistency first, How to apply, Rule index, Testing best practices, What to test

### Community 223 - "User"
Cohesion: 0.19
Nodes (4): User, {closure#1}(), {closure#2}(), {closure#3}()

### Community 224 - ".claude/skills/testing-best-practices/SKILL.md"
Cohesion: 0.29
Nodes (3): Built-in Laravel assertion methods, How to find test framework features, Security tests

### Community 226 - "DocumentStatus"
Cohesion: 0.33
Nodes (4): DocumentStatus, Approved, Cancelled, Draft

## Knowledge Gaps
- **85 isolated node(s):** `DocumentApproved`, `DocumentCancelled`, `Ping`, `ScanReceived`, `StockLow` (+80 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1184 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **95 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `.addProduct`, `App\Models\User`, `Convention and style best practices`, `Illuminate\Foundation\Testing\RefreshDatabase`, `Document`, `Role`, `UserFactory.php`, `Documents/Show.php`, `Convention and style best practices`, `Illuminate\Database\Eloquent\Model`, `App\Models\Document`, `Product`, `.createDraft`?**
  _High betweenness centrality (0.156) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `User` (e.g. with `Follow project naming conventions` and `Follow project naming conventions`) actually correct?**
  _`User` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `DocumentApproved`, `DocumentCancelled`, `Ping` to the rest of the system?**
  _85 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `package.json` be split into smaller, more focused modules?**
  _Cohesion score 0.06471631205673758 - nodes in this community are weakly interconnected._
- **Why does `Metoda w skrócie` connect `scanner.js` to `.addProduct`?**
  _High betweenness centrality (0.103) - this node is a cross-community bridge._
- **Should `Illuminate\Foundation\Testing\RefreshDatabase` be split into smaller, more focused modules?**
  _Cohesion score 0.13333333333333333 - nodes in this community are weakly interconnected._
- **Why does `Follow project naming conventions` connect `Convention and style best practices` to `User`?**
  _High betweenness centrality (0.068) - this node is a cross-community bridge._
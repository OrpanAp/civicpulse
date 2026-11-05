# CivicPulse — Project Context & Development Tracker

> **Purpose of this file:** Preserve the project's original motivation, current technical state, decisions, and next actions. Update this file as work is completed so development can continue across sessions without relying on chat history.

**Last updated:** 2026-11-05  
**Repository:** https://github.com/OrpanAp/civicpulse  
**Default branch:** `main`  
**Project status:** Early development — HTTP lifecycle and an initial multi-driver PDO database foundation have been implemented and locally verified. CivicPulse report persistence is not yet connected.

---

## 1. Why We Started CivicPulse

CivicPulse was started with two connected ambitions.

### 1.1 Build a real-world product

We do not want to spend the project building another traditional task manager, tutorial CRUD application, or collection of disconnected practice scripts.

We want to build a civic infrastructure reporting platform that addresses recognizable community problems. People should eventually be able to report issues such as:

- Potholes, damaged roads, and unsafe public infrastructure
- Water supply interruptions, drainage, and flooding problems
- Power outages and damaged electrical infrastructure
- Waste collection and sanitation problems
- Other local public-service disruptions

The intended product should support a meaningful lifecycle:

1. A citizen reports a problem with a description, category, and location.
2. The report can include supporting evidence such as photographs.
3. Reports can be reviewed and verified.
4. Similar reports can be identified and grouped to avoid duplicate records.
5. Issues can be prioritized and assigned to the appropriate people or teams.
6. Progress can be tracked through investigation and resolution.
7. Important actions and status changes can be audited.
8. Citizens can receive updates, while aggregated data can help reveal recurring problems.

These are product goals. They are **not** claims that these features have already been implemented.

### 1.2 Build the PHP framework ourselves

The second ambition is equally important: build the PHP framework that powers CivicPulse rather than using Laravel or another full-stack framework to do the work for us.

The purpose is to understand and implement the engineering concepts behind a modern PHP framework through a substantial application:

- HTTP request and response lifecycle
- Routing and dispatch
- Application bootstrap and front controller
- Dependency injection and service management
- Middleware pipeline
- Database connections and query building
- Models and migrations
- Validation, authentication, authorization, and security
- Events and other reusable application services

The framework should solve actual needs that arise while building CivicPulse. We should not create abstractions just to increase the number of classes or files.

**The original motivation in one sentence:** Build a useful civic infrastructure reporting product while learning how to engineer the PHP framework underneath it, through complete, working features rather than tutorial-sized exercises.

---

## 2. Development Rules We Agreed On

These rules are important because the earlier learning process spent too much time on tiny isolated scripts and repeated checkpoints.

1. **Build complete milestones.** Prefer a working request-to-response flow or a complete reporting feature over many disconnected micro-exercises.
2. **Explain the architecture.** Explain why a class or layer exists, what responsibility it owns, and how it collaborates with other parts.
3. **Teach while implementing.** Explain PHP and framework concepts when they become relevant. Do not turn every small detail into a separate lesson or quiz.
4. **Do not skip necessary steps.** Provide exact file paths, code, commands, and ways to test the result.
5. **Test observable behavior.** A milestone is not complete merely because files exist or code looks plausible. Run it and verify its behavior.
6. **Keep the project honest.** Document what works, what is incomplete, and what is only planned. Never present planned functionality as implemented.
7. **Avoid unnecessary rewrites.** Respect existing decisions unless there is a clear technical reason to change them. Explain that reason before changing architecture.
8. **Keep CivicPulse central.** Framework work should enable the real civic product, not become an endless framework-building exercise.
9. **Track progress here.** Update the status, decisions, and next actions in this file at the end of meaningful milestones.

---

## 3. Current Environment

Known development environment:

- Operating system: Windows 11
- Terminal: Git Bash / MSYS2 UCRT64
- PHP CLI: 8.5.12
- Composer: 2.10.3
- Git: installed and working
- Editor: Visual Studio Code

Project directory used locally:

```text
~/Desktop/PHP OOP/Project/Power PHP Framework/civicpulse
```

Repository:

```text
https://github.com/OrpanAp/civicpulse
```

Do not rerun the original directory setup batch file. The project structure already exists; running the setup script again may create nested directories.

---

## 4. Current Repository Structure

```text
civicpulse/
├── app/
│   ├── Controllers/
│   ├── Events/
│   ├── Middleware/
│   ├── Models/
│   ├── Policies/
│   └── Services/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── framework/
│   ├── src/
│   │   ├── Application/
│   │   ├── Container/
│   │   ├── Database/
│   │   ├── Http/
│   │   ├── Routing/
│   │   ├── Support/
│   │   └── Validation/
│   └── tests/
├── public/
├── routes/
├── storage/
├── tests/
├── .env.example
├── .gitignore
├── composer.json
└── README.md
```

Some directories are placeholders for future work. Their presence does not mean the corresponding components are implemented.

---

## 5. Implemented Work — Verified Repository State

This section records what exists in the repository as of the date above.

### 5.1 Project and autoloading

- [x] GitHub repository initialized.
- [x] Initial project directory structure created.
- [x] Composer project initialized.
- [x] Composer PSR-4 autoload mappings configured.
- [x] Composer autoload generation tested.

Current `composer.json` namespace mappings:

```json
{
  "autoload": {
    "psr-4": {
      "CivicPulse\\Framework\\": "framework/src/",
      "App\\": "app/"
    }
  }
}
```

This means classes under the `CivicPulse\\Framework\\` namespace are loaded from `framework/src/`, and application classes under `App\\` are loaded from `app/`.

### 5.2 HTTP Request

File: `framework/src/Http/Request.php`

Implemented behavior:

- [x] Stores and normalizes the HTTP method to uppercase.
- [x] Extracts the URL path from a URI.
- [x] Parses query-string parameters with PHP's `parse_str()`.
- [x] Exposes all query parameters through `getQuery()`.
- [x] Retrieves one query parameter with a default through `getQueryParam()`.
- [x] Normalizes incoming header names to lowercase.
- [x] Retrieves a header through `getHeader()`.
- [x] Preserves the raw request body through `getBody()`.
- [x] Provides `createFromGlobals()` to build a request from PHP's request environment.

- [x] Normalizes the result of `getallheaders()` to an array when the function returns `false`.

The Request implementation is integrated with the Router and Application lifecycle.

### 5.3 Router

File: `framework/src/Routing/Router.php`

Implemented behavior:

- [x] Uses an instance-owned route registry.
- [x] Registers GET and POST routes.
- [x] Supports direct route registration through `addRoute()`.
- [x] Normalizes HTTP methods to uppercase during registration and lookup.
- [x] Dispatches an incoming `Request` object.
- [x] Passes the Request to the matched handler.
- [x] Enforces a `Response` return contract for route handlers.
- [x] Returns HTTP 404 when the path is unknown.
- [x] Returns HTTP 405 when the path exists but the method is unsupported.
- [x] Includes the supported methods in the 405 `Allow` header.
- [x] Keeps routes isolated between Router/Application instances.

**Architecture decision:** use an instance-based Router. Each Application receives its own Router, avoiding shared static route state and making isolated tests possible. Basic exact-path routing is intentionally implemented before dynamic route parameters or route groups.

### 5.4 Existing test/check script

File: `tests/autoload.php`

Current check creates a Request and verifies query parameter retrieval for:

- `page=5`
- `status=pending`
- a missing `category` with default `all`

Command, run from the project root:

```bash
php tests/autoload.php
```

Expected output:

```text
string(1) "5"
string(7) "pending"
string(3) "all"
```

This is a small foundation check, not a full automated test suite.

### 5.5 HTTP Response

File: `framework/src/Http/Response.php`

Implemented behavior:

- [x] Stores response content, status code, and headers.
- [x] Validates HTTP status codes and response header names/values.
- [x] Exposes content, status, and headers for tests.
- [x] Provides a JSON response helper with safe JSON encoding.
- [x] Supports optional additional headers in the JSON helper.
- [x] Sends the HTTP status, headers, and body through `send()`.

### 5.6 Application lifecycle and public entry points

Files:

- `framework/src/Application/Application.php`
- `public/index.php`
- `public/router.php`
- `routes/web.php`

Implemented behavior:

- [x] Application handles a supplied Request and returns a Response.
- [x] Application runtime entry point creates a Request from PHP globals and sends the Response.
- [x] Front controller loads Composer autoloading, registers routes, creates Application, and runs it.
- [x] PHP development-server router forwards non-file paths to the front controller.
- [x] GET `/` returns a CivicPulse JSON health/landing response.
- [x] GET `/reports` returns an explicitly empty, not-yet-persisted reports response.

### 5.8 Database foundation — initial implementation present remotely; hardening changes verified locally

The initial database implementation files are present in the inspected `main` branch. The hardening changes and new test scripts described below are currently **local and uncommitted**; they have not been pushed to GitHub.

- `framework/src/Database/Contracts/DatabaseDriverInterface.php`
- `framework/src/Database/Drivers/MySqlDriver.php`
- `framework/src/Database/Drivers/PostgreSqlDriver.php`
- `framework/src/Database/Drivers/SQLiteDriver.php`
- `framework/src/Database/Connection.php`
- `framework/src/Database/ConnectionFactory.php`
- `framework/src/Database/DatabaseConfig.php`
- `framework/src/Database/DatabaseManager.php`
- `config/database.php`
- `tests/database_connection.php`

Implemented locally:

- [x] A common `DatabaseDriverInterface` contract.
- [x] PDO connection implementations for MySQL/MariaDB, PostgreSQL, and SQLite.
- [x] Dynamic driver selection through `ConnectionFactory`.
- [x] Driver aliases for `mysql`, `mariadb`, `pgsql`, `postgres`, `postgresql`, and `sqlite`.
- [x] A `Connection` wrapper for parameterized select, select-one, scalar, statement, inserted-ID retrieval, and transaction operations.
- [x] Configuration validation through `DatabaseConfig`, including safe validation order, default connection existence, array connection definitions, and non-empty string driver names (local hardening change; tests passed).
- [x] Named connection resolution and connection caching through `DatabaseManager`.
- [x] Configuration-driven default connection settings in `config/database.php`.
- [x] Transaction commit and rollback behavior verified against MariaDB.
- [x] Nested transaction rejection verified; nested transactions remain intentionally unsupported.

Developer environment and verification:

- [x] PHP PDO is enabled.
- [x] The `pdo_mysql` extension is available.
- [x] The MariaDB server responds with version `10.4.32-MariaDB`.
- [x] All ten database PHP files passed `php -l`.
- [x] `php tests/database_connection.php` passed against the real MariaDB server, including parameterized queries, connection caching, transaction commit, nested-transaction rejection, and rollback.
- [x] `php tests/database_config.php` passed all eight configuration checks, including invalid and valid configurations.
- [x] `php tests/database_factory.php` passed unsupported-driver handling, PostgreSQL alias/missing-extension checks, SQLite missing-extension handling, and real MySQL/MariaDB alias connections.
- [x] `git diff --check` reported no whitespace errors for the local changes.
- [ ] Commit/push the verified local hardening and test scripts after final review.

Local test files currently added or modified:
- Modified: `framework/src/Database/DatabaseConfig.php`
- Modified: `tests/database_connection.php`
- Added: `tests/database_config.php`
- Added: `tests/database_factory.php`
- Added: `tests/setup_test_database.php` (local test utility that creates the `civicpulse_test` database; it uses local development credentials and must be reviewed before any commit).

The connection integration test currently overrides the MySQL test connection to use the dedicated `civicpulse_test` database. The test database setup utility was run successfully. The developer's environment uses a local MariaDB account with username `root` and an empty password; do not assume those credentials are portable or suitable for shared/production environments.

Important limitations and verification boundaries:

- PostgreSQL and SQLite driver files passed PHP syntax checks, but their actual connections have not been tested because the current PHP installation exposes only the `mysql` PDO driver.
- MariaDB is reached through PDO's `pdo_mysql` driver; it does not require a separate `pdo_mariadb` extension.
- The current database configuration reads process environment variables with `getenv()`. A project environment-file loader has not yet been implemented.
- The current connection layer is an initial foundation, not a complete cross-database SQL abstraction. SQL dialect differences, schema grammar, portability, nested transactions, reconnect behavior, and production connection policies remain future work.
- The inspected GitHub `main` branch contains the listed database implementation files. Local uncommitted changes may still differ from the remote branch; verify with `git status --short` and local file inspection before continuing.

**Definition of Done for the current local hardening pass:** configuration validation, driver factory behavior, MySQL/MariaDB aliases, parameterized queries, connection caching, transaction commit, rollback, and nested-transaction rejection have all passed their respective local test scripts. PostgreSQL and SQLite runtime support remain unverified. The hardening changes and test files are still uncommitted locally; the remote repository does not yet contain them.

### 5.7 Documentation

- [x] `README.md` created and committed.
- [x] Keep this `context.md` updated as the living development handoff and progress tracker.

---

## 6. Completed Milestone: Browser HTTP Request-to-Response Lifecycle

**Status: Implemented and locally verified on 2026-11-05.**

The first browser-facing HTTP lifecycle is working:

```text
Browser
  |
  v
public/index.php
  |
  v
Application
  |
  v
Router
  |
  v
Route handler
  |
  v
Response
  |
  v
Browser
```

### Implemented files

- `framework/src/Http/Response.php`
- `framework/src/Routing/Router.php`
- `framework/src/Application/Application.php`
- `routes/web.php`
- `public/index.php`
- `public/router.php`
- `tests/http_lifecycle.php`

### Local verification reported by the developer

- [x] `git status --short` returned no output before these checks, indicating a clean working tree at that point.
- [x] PHP syntax checks passed for all seven implementation/test files.
- [x] `php tests/http_lifecycle.php` passed all eight assertions.
- [x] PHP development server started with `php -S localhost:8000 -t public public/router.php`.
- [x] `GET /` returned HTTP 200 and the CivicPulse JSON response.
- [x] `GET /reports` returned HTTP 200 and an empty reports payload explicitly stating persistence is not connected.
- [x] `GET /unknown` returned HTTP 404.
- [x] `POST /reports` returned HTTP 405 with `Allow: GET`.
- [x] Separate Application instances were verified to have isolated route registries by the integration test.

Commands used:

```bash
php -l framework/src/Http/Response.php
php -l framework/src/Routing/Router.php
php -l framework/src/Application/Application.php
php -l routes/web.php
php -l public/index.php
php -l public/router.php
php -l tests/http_lifecycle.php

php tests/http_lifecycle.php

php -S localhost:8000 -t public public/router.php
```

### Known scope and limitations

- Routing currently supports exact paths only; dynamic parameters, route groups, and named routes are not implemented.
- The reports endpoint is a placeholder. There is no database connection or persistence yet.
- The observed JSON content-type and Allow headers were emitted without a space after the colon by PHP's `header()` call. The responses were accepted by the local client; header formatting can be tidied separately without changing the lifecycle design.
- The built-in PHP server is for local development, not production deployment.

**Definition of Done:** the browser lifecycle milestone is complete based on the syntax checks, eight passing integration assertions, and the reported real HTTP responses above.

---

## 7. Expanded Framework and CivicPulse Roadmap

These are planned capabilities, not claims of implementation. Build each subsystem behind a clear contract, then verify a complete working path before checking it off. Avoid building every possible adapter before CivicPulse needs it.

### Milestone 2 — Application foundation and configuration

- [ ] Dependency injection / service container.
- [ ] Application configuration and configuration access.
- [ ] Environment-file loader, safe secret handling, and environment validation.
- [ ] Centralized exception handling and error responses.
- [ ] A consistent automated test structure.

### Milestone 3 — Middleware and HTTP request pipeline

- [ ] Middleware contract and pipeline/dispatcher.
- [ ] Middleware registration and ordering.
- [ ] Request logging and correlation/request IDs.
- [ ] Security headers and trusted-proxy handling.
- [ ] Session abstraction and session middleware.
- [ ] Authentication and authorization middleware when those features are introduced.

### Milestone 4 — Extensible database and storage foundation

**Initial local implementation:** PDO MySQL/MariaDB, PostgreSQL, and SQLite drivers, common driver contract, connection factory, configuration, manager, connection wrapper. MariaDB connection and basic operations have passed local tests. See section 5.8.

Relational database adapters to support over time:

- [x] MySQL/MariaDB PDO driver implementation (MariaDB runtime-tested).
- [ ] PostgreSQL PDO runtime verification after enabling `pdo_pgsql`.
- [ ] SQLite PDO runtime verification after enabling `pdo_sqlite`.
- [ ] SQL Server PDO adapter, subject to `pdo_sqlsrv` availability.
- [ ] Oracle PDO adapter, subject to `pdo_oci` availability.
- [ ] Firebird PDO adapter, subject to `pdo_firebird` availability.
- [ ] IBM Db2 adapter, subject to the supported PDO/ODBC extension and client libraries.
- [ ] Generic PDO_ODBC adapter for supported ODBC data sources, with explicit DSN and capability limitations.
- [ ] Evaluate Sybase/FreeTDS (`pdo_dblib`) only if a real deployment or integration requires it.
- [ ] Driver capability detection, extension checks, clear missing-extension errors, and platform compatibility checks.
- [ ] A documented support matrix recording each adapter's status: planned, implemented, syntax-checked, integration-tested, or production-verified.
- [ ] Additional adapters only where a real use case justifies them; do not claim universal support for every database engine.

Database capabilities:

- [ ] Driver registry/factory design that can add adapters without a growing, fragile conditional block.
- [ ] Consistent configuration schema and connection options with driver-specific validation.
- [ ] Explicit driver capability metadata (transactions, savepoints, returning/insert IDs, JSON, schemas, SSL, and other engine-specific features).
- [ ] Multiple named connections and lifecycle management hardening.
- [ ] Connection health checks, reconnect policy, timeouts, and safe failure handling.
- [ ] Read/write connection separation and read-replica routing.
- [ ] Parameter binding rules and portable query operations.
- [ ] Database-specific SQL grammars for query building and schema operations.
- [ ] Query builder based on CivicPulse report/user workflows.
- [ ] Transaction behavior, including an explicit strategy for nested transactions/savepoints.
- [ ] Database integration tests for each available engine.

**Portability rule:** a shared connection contract does not make every SQL statement portable. Engine-specific syntax and capabilities must be isolated in driver/grammar adapters. PDO-based SQL databases and non-SQL stores must not be forced into one misleading interface.

### Milestone 5 — Non-relational data stores, cache, and queues

**Key-value/cache drivers:**

- [ ] Define a cache contract around get, set, delete, has, TTL/expiration, and clear operations where appropriate.
- [ ] In-memory adapter for tests and local development.
- [ ] Redis adapter for production cache, counters, distributed locks, rate-limit state, and queue backend use.
- [ ] Memcached adapter if a real deployment requires it.
- [ ] Optional database-backed cache adapter.
- [ ] Cache prefixing, serialization rules, atomic operations, TTL behavior, and cache-stampede protection where needed.

**Other data-store drivers:**

- [ ] MongoDB/document-store adapter behind a separate document-database contract if product requirements justify it.
- [ ] Evaluate other document, search, or wide-column stores only against a concrete product requirement; do not force them into the SQL connection interface.

**Sessions and queues:**

- [ ] Session store contract and file, database, and Redis adapters where appropriate.
- [ ] Queue contract covering dispatch, reservation, acknowledgement, retry, delay, failure, and dead-letter behavior.
- [ ] In-memory/synchronous queue for tests and development.
- [ ] Redis-backed queue adapter; consider database-backed queues when useful.
- [ ] Cache invalidation, TTL, serialization, atomicity, concurrency, and failure behavior tests.

Redis is not a relational database driver. It is a key-value store. Reuse a Redis connection/client adapter where sensible, but expose separate cache, lock, session, rate-limit, and queue contracts because their semantics and reliability requirements differ.

### Milestone 6 — Schema, migrations, and CivicPulse persistence

- [ ] Migration interface, runner, and migration tracking.
- [ ] Per-engine schema grammar for supported relational engines.
- [ ] Initial schema for users, reports, categories, locations, and report status history.
- [ ] Seeders for local development data.
- [ ] Deliberate repository/model pattern driven by actual report use cases.
- [ ] Database-backed integration tests.

### Milestone 7 — First complete CivicPulse reporting workflow

Build a real end-to-end vertical slice:

- [ ] Define report data model and lifecycle.
- [ ] Validate a submitted report.
- [ ] Store a report and its location.
- [ ] Expose report details and a report listing.
- [ ] Handle expected errors and invalid input.
- [ ] Test the workflow against a real test database.

### Milestone 8 — Console / CLI tools

- [ ] Console kernel and command registry.
- [ ] Command signature, arguments, options, and help output.
- [ ] Clear exit codes and formatted console output.
- [ ] Commands for migrations, seeders, cache operations, and application diagnostics.
- [ ] Scheduled-task integration only when a concrete operational need exists.

The CLI is a framework subsystem, not a database driver. It should invoke framework services through the same application/container architecture.

### Milestone 9 — Security, sessions, and form protection

- [ ] Secure session abstraction and configurable session storage.
- [ ] CSRF token generation, storage, validation, and rotation policy.
- [ ] CSRF middleware for state-changing requests.
- [ ] Safe template directive such as `@csrf` that renders a hidden CSRF token input.
- [ ] Output escaping by default in the view/template system.
- [ ] Password hashing, authentication, authorization, and policies.
- [ ] Input validation, upload validation, and security-focused tests.

The `@csrf` syntax depends on a template compiler/view engine. It is not PHP syntax and must not be treated as a magic annotation. The directive should generate a real hidden form field, while middleware validates the submitted token. Template directives must compile to explicit, testable behavior; they must not bypass escaping, authorization, or request validation.

**Planned template syntax / developer shortcuts (Laravel-inspired in ergonomics, independently designed and implemented):**

- [ ] `@csrf` — render the hidden CSRF token field.
- [ ] `@method('PUT')` — render a validated method-override field for supported form methods.
- [ ] `@if`, `@elseif`, `@else`, `@endif` — conditional rendering.
- [ ] `@foreach`, `@forelse`, `@empty`, `@endforeach` — safe collection iteration and empty states.
- [ ] `@include`, `@extends`, `@section`, `@yield`, `@endsection` — reusable layouts and partials.
- [ ] `@auth`, `@guest`, and role/permission directives only after authentication and authorization services exist.
- [ ] `@error` and validation-error helpers after the validation and form-error contracts exist.
- [ ] Escaped output by default, with an explicit and carefully controlled raw-output escape hatch.
- [ ] Compile templates to cached PHP safely; invalidate compiled templates when source changes in development.
- [ ] Tests for directive parsing, malformed directives, nested blocks, escaping, and generated output.

**Other framework shortcuts and APIs to evaluate after the underlying services are stable:** named route URL generation, redirect helpers, request input helpers, response/JSON/download helpers, configuration and environment access, session/flash helpers, validation helpers, authorization checks, and dependency resolution. These should be thin, documented entry points over real framework services—not hidden global state or magic that makes behavior difficult to trace.

### Milestone 10 — Traffic management, rate limiting, and load balancing

- [ ] Rate-limiter contract with configurable limits and windows.
- [ ] In-memory limiter for tests and single-process development.
- [ ] Redis-backed distributed limiter for multiple application instances.
- [ ] Per-IP, per-user, and per-route policies with trusted-proxy safeguards.
- [ ] HTTP throttling middleware and correct 429 responses/headers.
- [ ] Application-level read/write database routing.
- [ ] Health checks, failure detection, and configurable failover.
- [ ] Reverse-proxy/load-balancer deployment support and trusted forwarded headers.
- [ ] Observability for rejected requests, database health, and routing decisions.

Load balancing must be treated at the correct layer: a reverse proxy/load balancer distributes HTTP traffic across application instances; database read/write splitting distributes database operations. These are related but distinct systems. Avoid implementing a custom network load balancer when mature infrastructure such as Nginx, HAProxy, or a cloud load balancer is the appropriate tool.

### Milestone 11 — Trust, verification, and operational workflow

- [ ] Report review and verification states.
- [ ] Similar-report detection strategy.
- [ ] Duplicate grouping and linking.
- [ ] Preserve individual citizen submissions while linking them to a shared issue where appropriate.
- [ ] Audit history for important actions.
- [ ] Citizen, reviewer, and staff permissions.
- [ ] Assignment, prioritization, and status transitions.

### Milestone 12 — Public experience and operational features

- [ ] Evidence/photo upload with validation and safe storage.
- [ ] Location-based browsing and filtering.
- [ ] Notifications and public issue status pages.
- [ ] Administrative views and analytics.
- [ ] API documentation.
- [ ] Search and reporting capabilities appropriate to real civic data.

### Milestone 13 — Production readiness

- [ ] Security review and threat modelling.
- [ ] Structured logging, metrics, tracing, and monitoring.
- [ ] Performance and concurrency testing.
- [ ] Backup, restore, and disaster-recovery strategy.
- [ ] Deployment configuration and CI/CD.
- [ ] Multi-instance deployment and infrastructure health checks.
- [ ] Operational and developer documentation.

### Milestone 14 — Framework developer experience and expressive APIs

- [ ] Stable application bootstrap and a clear public framework API.
- [ ] CLI command shortcuts for common operations (for example, migration, seeding, cache clearing, route listing, and environment diagnostics).
- [ ] Consistent helper APIs for route URLs, redirects, request access, responses, configuration, sessions, validation, and authorization.
- [ ] Template engine with a documented directive syntax and safe compilation/caching.
- [ ] Optional facade-style access only if it remains testable, explicit, and does not obscure dependencies.
- [ ] Developer error pages in local mode and safe, non-sensitive error responses in production.
- [ ] Framework documentation covering setup, configuration, supported drivers, extension contracts, and examples.
- [ ] Compatibility tests for public APIs and documented shortcuts so syntax does not change unexpectedly.


---

## 8. Important Technical Notes and Decisions

- **PHP version:** The current local CLI is PHP 8.5.12. Composer's project requirements should explicitly reflect the actual minimum PHP version the code supports; do not assume every deployment host has the same version.
- **Autoloading:** Use Composer's PSR-4 autoloader. When adding a class inside an existing mapped namespace directory, Composer does not need to be regenerated for every file. Regenerate autoload metadata when mappings or autoload configuration change.
- **Request body:** Keep the raw request body as a string at the HTTP boundary. Parse JSON or form data deliberately based on content type and the needs of a specific feature.
- **Query strings:** Query parameters are not part of the route path. For example, `/reports?page=2` and `/reports?page=3` should match the same route; the page value is input to filtering/pagination.
- **Responsibilities:** Request represents incoming HTTP data. Router matches a request to a handler. Application coordinates execution. Controller/application services implement application behavior. Response represents outgoing HTTP data.
- **Database:** the initial PDO connection foundation is present in the inspected repository and MariaDB-tested according to the recorded local test output. PostgreSQL and SQLite connection classes need runtime verification after their PDO extensions are enabled. Maintain a support matrix; never equate a driver class or syntax check with runtime or production support.
- **Driver families:** relational PDO drivers, document stores, key-value stores, cache, sessions, queues, filesystem/object storage, mail, and logging are distinct adapter families. Define contracts around each family's actual behavior; do not create one universal `DriverInterface` that erases important differences.
- **Relational coverage:** track MySQL/MariaDB, PostgreSQL, SQLite, SQL Server, Oracle, Firebird, Db2, and ODBC/Sybase options individually. Availability depends on PHP extensions, client libraries, operating system, and deployment environment. Prioritize tested and needed engines over an unrealistic promise to support every vendor immediately.
- **Redis:** use it as a key-value backend through separate cache, queue, session, lock, and rate-limit services. Do not implement Redis as a PDO SQL driver.
- **Template shortcuts:** directives such as `@csrf` are compiled template syntax backed by real CSRF/session/middleware services. Convenience syntax must never replace the security mechanism underneath it.
- **Framework scope:** Build only what the application needs, while keeping the components reusable and understandable.

---

## 9. Session Handoff Checklist

At the beginning of a future development session:

1. Read this file first.
2. Check the current repository state and compare it with the progress tracker.
3. Ask for or inspect any local changes that have not been pushed.
4. Continue from the first unchecked task in the active milestone.
5. Do not repeat completed lessons or rebuild existing files without a reason.

At the end of a meaningful session:

1. Record what was implemented.
2. Record the commands/tests run and their actual results.
3. Check off only work that has been verified.
4. Record known bugs, limitations, and design decisions.
5. Set the next concrete action.
6. Keep the README aligned with the actual public-facing project status when necessary.

**Current next action:** The local database hardening review and final test run are complete. The developer has verified that `php tests/database_config.php`, `php tests/database_factory.php`, and `php tests/database_connection.php` all pass, and `git diff --check` reports no whitespace errors. The working tree contains two modified files and three new local test/setup files; these changes remain uncommitted and have not been pushed. The next action is for the developer to commit and push the verified local source/test changes when ready; do not edit source files on GitHub. Once that commit is pushed, synchronize this tracker with the resulting repository state. Then enable and runtime-test PostgreSQL/SQLite where practical, followed by environment configuration and service-container integration. The expanded roadmap also tracks additional relational engines, separate Redis/cache/queue/session/lock/rate-limit adapters, a MongoDB contract if justified, CLI commands, a secure template engine with `@csrf` and related directives, framework helper shortcuts, rate limiting, application/database load balancing, and production observability. Treat each item as planned until implemented and tested; maintain a per-driver support matrix and never check off an adapter based on a class file or syntax check alone.

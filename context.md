# CivicPulse — Project Context & Development Tracker

> **Purpose of this file:** Preserve the project's original motivation, current technical state, decisions, and next actions. Update this file as work is completed so development can continue across sessions without relying on chat history.

**Last updated:** 2026-11-06  
**Repository:** https://github.com/OrpanAp/civicpulse  
**Default branch:** `main`  
**Project status:** Early development — HTTP lifecycle, middleware pipeline, request IDs and structured HTTP request logging, configurable HTTP security headers, trusted-proxy handling, multi-driver PDO database foundation, environment/configuration loading, dependency injection, and container-based application bootstrap have been implemented, tested locally, committed, and pushed to `main`. CivicPulse report persistence is not yet implemented.

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

### 1.3 Provide a built-in administrative experience

CivicPulse should include a first-party, customizable dashboard and admin panel with the convenience and completeness of Django's built-in admin, while being independently designed and implemented as part of this project. It should not be an afterthought or a third-party dependency. The administrative experience should eventually let authorized staff manage the platform and its civic data through a secure web interface.

The intended capabilities include:

- A configurable dashboard with useful operational summaries, report statistics, pending reviews, workload, recent activity, and actionable alerts.
- User administration, account status management, authentication, password reset/change flows, and secure session handling.
- Role-based access control (RBAC), permissions, role assignment, permission checks, and policies for resource- and action-level authorization.
- Distinct citizen, reviewer, staff, administrator, and other roles only where actual workflows require them; do not rely on hiding UI controls as an authorization mechanism.
- Administrative resource screens with search, filtering, sorting, pagination, detail views, safe create/update actions, bulk actions where appropriate, and validation feedback.
- Management of report categories, reports, assignments, review queues, status transitions, and reference data according to the current user's permissions.
- Audit trails for sensitive administrative actions, clear attribution, and safe handling of personal data.
- Extensible dashboard widgets, navigation, resource definitions, and reusable UI components so application developers can tailor the admin experience without forking framework internals.
- Responsive, accessible layouts, clear empty/error/loading states, and safe defaults suitable for real operational use.

This is a product and framework requirement, not a claim that authentication, authorization, RBAC, dashboard pages, or admin CRUD are implemented today. Build it on explicit authentication, authorization, policy, session, validation, and audit services; never treat a hidden menu item or template directive as a security boundary.



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


### 2.1 Repository access and development boundaries

The user confirmed the following access and workflow rules for the CivicPulse repository:

- Repository: https://github.com/OrpanAp/civicpulse
- Repository files other than `context.md` are read-only for the assistant. The assistant may inspect, read, analyze, and discuss source code, tests, configuration, and documentation, but must not create, edit, delete, or otherwise write those files directly through GitHub.
- `context.md` is the only repository file the assistant may read and update directly on GitHub. Use it to preserve verified project context, decisions, milestones, and next actions.
- All source-code development and changes must be performed locally by the user. The assistant should provide instructions and code for the user to apply locally, then review the resulting output.
- Do not modify `role.md` or any other repository file unless the user explicitly changes this restriction.
- Before updating `context.md`, fetch its latest version and use its current blob SHA. Preserve the complete existing content and make only the intended, narrowly scoped change.
- Never claim that local files were changed, tests were run, commits were created, pushes succeeded, or local and remote branches are synchronized unless the user or reliable tool output verifies that result.

These boundaries apply across future CivicPulse development sessions.

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

The following is the structure observed from the developer's local inspection on 2026-11-06. It reflects the actual directories and files reported at that checkpoint, including local, uncommitted session and CSRF work. It is not a claim that those local changes are already present on GitHub.

```text
civicpulse/
├── config/
│   ├── database.php
│   ├── security.php
│   ├── session.php
│   └── trusted_proxies.php
├── framework/
│   └── src/
│       ├── Application/
│       │   └── Application.php
│       ├── Configuration/
│       │   ├── ConfigRepository.php
│       │   └── Environment.php
│       ├── Container/
│       │   ├── Container.php
│       │   └── ContainerException.php
│       ├── Database/
│       │   ├── Contracts/
│       │   ├── Drivers/
│       │   ├── Connection.php
│       │   ├── ConnectionFactory.php
│       │   ├── DatabaseConfig.php
│       │   └── DatabaseManager.php
│       ├── Http/
│       │   ├── Contracts/
│       │   ├── Middleware/
│       │   │   ├── CsrfMiddleware.php
│       │   │   ├── MiddlewareInterface.php
│       │   │   ├── MiddlewarePipeline.php
│       │   │   ├── MiddlewareRequestHandler.php
│       │   │   ├── RequestIdMiddleware.php
│       │   │   ├── RequestLoggingMiddleware.php
│       │   │   ├── SecurityHeadersMiddleware.php
│       │   │   └── SessionMiddleware.php
│       │   ├── Request.php
│       │   ├── Response.php
│       │   └── TrustedProxyResolver.php
│       ├── Logging/
│       │   ├── ErrorLogLogger.php
│       │   └── LoggerInterface.php
│       ├── Routing/
│       │   ├── Router.php
│       │   └── web.php
│       ├── Security/
│       │   └── Csrf/
│       │       └── CsrfTokenManager.php
│       └── Session/
│           ├── NativeSession.php
│           ├── SessionInterface.php
│           └── SessionManager.php
├── bootstrap/
│   └── app.php
├── public/
│   └── index.php
├── tests/
│   ├── autoload.php
│   ├── bootstrap.php
│   ├── config_repository.php
│   ├── container.php
│   ├── csrf_application.php
│   ├── csrf_middleware.php
│   ├── csrf_token_manager.php
│   ├── database_config.php
│   ├── database_connection.php
│   ├── database_factory.php
│   ├── environment.php
│   ├── http_lifecycle.php
│   ├── http_middleware.php
│   ├── http_request_logging.php
│   ├── http_security_headers.php
│   ├── request_metadata.php
│   ├── request_parsed_body.php
│   ├── session.php
│   ├── session_application.php
│   ├── session_config.php
│   ├── session_manager.php
│   ├── session_middleware.php
│   ├── setup_test_database.php
│   └── trusted_proxy_resolver.php
├── .env.example
├── .gitignore
├── composer.json
└── README.md
```

**Observed absences at this checkpoint:** app/ and resources/ did not exist in the developer's local directory listing. config/app.php was also absent. The Composer App namespace PSR-4 mapping exists, but that mapping alone does not mean the app/ directory has been created. The local working tree contained uncommitted and untracked session/CSRF changes; the developer had not reported them as committed or pushed.

### 4.1 Planned template/view directory structure

The following is the proposed target structure for the upcoming template/view milestone. These directories and files are **planned, not yet created or implemented**. Create them locally in stages as each component is developed and tested.

```text
framework/
└── src/
    └── View/
        ├── View.php
        ├── ViewFinder.php
        ├── ViewRenderer.php
        ├── Compiler/
        │   ├── Compiler.php
        │   └── CompiledViewCache.php
        └── Exceptions/
            ├── ViewException.php
            └── ViewNotFoundException.php

resources/
└── views/

storage/
└── framework/
    └── views/
```

**Planned responsibilities:**

- View: public rendering API.
- ViewFinder: resolve template names only inside approved view directories and reject traversal attempts.
- Compiler: compile the supported template language into PHP without evaluating arbitrary user-provided PHP expressions.
- CompiledViewCache: store compiled templates outside the public directory and invalidate them safely when source templates or compiler behavior changes.
- ViewRenderer: execute compiled templates and capture rendered output.
- ViewException and ViewNotFoundException: provide explicit, predictable template error types.
- resources/views/: hold application view templates.
- storage/framework/views/: hold generated compiled templates; this runtime directory must not be publicly accessible.

This is an architectural proposal, not a requirement to create every class immediately. The first implementation step is ViewFinder plus focused path-resolution and traversal tests.
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
- [x] Retrieves one query parameter with a default through `getQueryParam()`.- [x] Normalizes incoming header names to lowercase.
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

The initial database implementation files are present in the inspected `main` branch. The hardening changes and new test scripts described below were committed and pushed in `d49657c` (`Add multiple test cases for db connections`). The developer has confirmed that local `main` is synchronized with `origin/main` and the working tree is clean.

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
- [x] PHP 8.5.12 permanently loads `pdo_pgsql` and `pdo_sqlite` alongside `pdo_mysql`; `PDO::getAvailableDrivers()` reports `mysql`, `pgsql`, and `sqlite`.
- [x] SQLite runtime-tested through CivicPulse's `ConnectionFactory` and `Connection`: in-memory database creation, table creation, parameterized insert/select, scalar count, transaction commit, and rollback all passed.
- [x] The MariaDB server responds with version `10.4.32-MariaDB`.
- [x] All ten database PHP files passed `php -l`.
- [x] `php tests/database_connection.php` passed against the real MariaDB server, including parameterized queries, connection caching, transaction commit, nested-transaction rejection, and rollback.
- [x] `php tests/database_config.php` passed all eight configuration checks, including invalid and valid configurations.
- [x] `php tests/database_factory.php` passed unsupported-driver handling, PostgreSQL alias/missing-extension checks, SQLite missing-extension handling, and real MySQL/MariaDB alias connections.
- [x] `git diff --check` reported no whitespace errors for the local changes.
- [x] Commit/push the verified local hardening and test scripts after final review (`d49657c`); local `main` is synchronized with `origin/main` and the working tree is clean.

Local test files currently added or modified:
- Modified: `framework/src/Database/DatabaseConfig.php`
- Modified: `tests/database_connection.php`
- Added: `tests/database_config.php`
- Added: `tests/database_factory.php`
- Added: `tests/setup_test_database.php` (local test utility that creates the `civicpulse_test` database; it uses local development credentials and must be reviewed before any commit).

The connection integration test currently overrides the MySQL test connection to use the dedicated `civicpulse_test` database. The test database setup utility was run successfully. The developer's environment uses a local MariaDB account with username `root` and an empty password; do not assume those credentials are portable or suitable for shared/production environments.

Important limitations and verification boundaries:

- PostgreSQL 18.6 runtime integration is now verified locally through CivicPulse's `PostgreSqlDriver`, `ConnectionFactory`, `DatabaseManager`, and `Connection`. The developer installed PostgreSQL Server 18.6, enabled `pdo_pgsql`, created the dedicated `civicpulse_test` database, and passed connection, server-version, parameterized-query, connection-caching, temporary-table, transaction-commit, and transaction-rollback checks. This is local integration evidence, not production verification.
- MariaDB is reached through PDO's `pdo_mysql` driver; it does not require a separate `pdo_mariadb` extension.
- The current database configuration reads process environment variables with `getenv()`. A project environment-file loader has not yet been implemented.
- The current connection layer is an initial foundation, not a complete cross-database SQL abstraction. SQL dialect differences, schema grammar, portability, nested transactions, reconnect behavior, and production connection policies remain future work.
- The inspected GitHub `main` branch contains the listed database implementation files. Local uncommitted changes may still differ from the remote branch; verify with `git status --short` and local file inspection before continuing.

**Definition of Done for the current local hardening pass:** configuration validation, driver factory behavior, MySQL/MariaDB aliases, parameterized queries, connection caching, transaction commit, rollback, and nested-transaction rejection have passed their respective local test scripts. SQLite runtime support has passed through CivicPulse's PDO connection layer. PostgreSQL 18.6 runtime integration has now also passed through the existing CivicPulse classes, including connection, parameterized query, connection caching, temporary-table creation, commit, and rollback. The hardening changes and test files are committed and present in the remote repository at `d49657c`; the PostgreSQL integration was performed with an inline local test and did not modify repository source files.

### 5.8 PostgreSQL 18.6 runtime integration — locally verified

**Status: Passed locally on 2026-11-05 using the existing framework implementation. No framework source files were changed for this verification.**

Developer environment and setup:

- [x] PostgreSQL Server 18.6 installed on Windows and Windows service `postgresql-x64-18` confirmed running.
- [x] PostgreSQL command-line client verified at `C:\\Program Files\\PostgreSQL\\18\\bin\\psql.exe`; `psql --version` reported 18.6. The executable is not currently on Git Bash's PATH, so it was invoked by its full path.
- [x] PHP's `pdo_pgsql` extension enabled; `PDO::getAvailableDrivers()` includes `pgsql`.
- [x] Password authentication for the local `postgres` role repaired. A narrowly scoped temporary IPv4 localhost `trust` rule was used only to set a new password, then the backed-up original `pg_hba.conf` was restored and reloaded. A subsequent password-authenticated `psql` connection succeeded; the original SCRAM rules are in place.
- [x] Dedicated database `civicpulse_test` created, owned by `postgres`, using UTF-8 encoding.

**Actual CivicPulse integration test results:**

The developer ran an inline PHP script from the project root using Composer autoloading and the existing `DatabaseConfig`, `DatabaseManager`, `ConnectionFactory`, `PostgreSqlDriver`, and `Connection` classes. Credentials were read from the temporary `CIVICPULSE_PGSQL_PASSWORD` environment variable and the variable was unset after the test.

- [x] Connected driver reported `pgsql`.
- [x] Server version query returned PostgreSQL 18.6 on x86_64 Windows.
- [x] Named parameterized SELECT returned the expected message.
- [x] `DatabaseManager` returned the same cached connection for default and named `pgsql` access.
- [x] PostgreSQL temporary table created successfully using an identity primary key.
- [x] Transaction commit persisted the expected row within the test session.
- [x] An intentional exception caused transaction rollback; the committed row remained and the rolled-back row was absent.
- [x] Test printed `All PostgreSQL integration tests passed.`
- [x] Temporary password environment variable was unset after the test.

**Scope and limitations:**

- This verifies local PostgreSQL connection and core operations through the existing CivicPulse classes. It does not establish production readiness, cross-platform support, schema-builder portability, migration behavior, or full PostgreSQL feature compatibility.
- The test table was temporary and session-scoped; no permanent application table or framework source file was created.
- The existing `tests/database_connection.php` remains MySQL/MariaDB-specific (for example, it uses `SELECT VERSION()` and `AUTO_INCREMENT`). PostgreSQL was therefore tested with a separate inline script rather than changing the existing shared test.
- PostgreSQL is locally integration-tested; it is not yet production-verified. No new committed PostgreSQL test script has been added.

### 5.9 Environment loading, configuration repository, service container, and bootstrap integration

**Status: Implemented, fully verified locally, committed, and pushed to GitHub `main` on 2026-11-06.** The implementation is included in commit `e1487fe` (`feat: add dependency injection container and application bootstrap`).

**Environment and configuration services**

Files:
- `framework/src/Configuration/Environment.php`
- `framework/src/Configuration/ConfigRepository.php`
- `tests/environment.php`
- `tests/config_repository.php`

Verified behavior:
- [x] Environment loader reads simple key/value entries, quoted values, blank lines, comments, and supported double-quoted escapes.
- [x] Existing process environment values are preserved.
- [x] Missing optional files are allowed; missing required files and malformed entries are rejected.
- [x] Environment variable interpolation and shell execution are not performed.
- [x] Configuration repository loads trusted PHP configuration files from the configured directory.
- [x] Dotted-key access, defaults, key-existence checks, full configuration retrieval, and preservation of original value types/null values work.
- [x] Missing configuration directories and configuration files that do not return arrays are rejected.- [x] `php tests/environment.php` passed all 12 checks.
- [x] `php tests/config_repository.php` passed all 12 checks.

**Dependency injection container**

Files:
- `framework/src/Container/Container.php`
- `framework/src/Container/ContainerException.php`
- `tests/container.php`

Verified behavior:
- [x] Transient bindings, singleton bindings, prebuilt instances, interface-to-concrete bindings, and closure factories.
- [x] Automatic constructor dependency resolution, including nested dependencies and constructor defaults.
- [x] Clear rejection of unresolved scalar, union, and intersection dependencies.
- [x] Circular dependency and circular alias detection with resolution-chain diagnostics.
- [x] Failed resolution cleanup, singleton factory caching, rebinding behavior, nullable class dependencies, and invalid factory-result rejection.
- [x] Unbound interfaces, abstract classes, missing classes, and empty service identifiers are rejected.
- [x] `php tests/container.php` passed all 26 checks.

**Application bootstrap integration**

Files:
- `bootstrap/app.php`
- `public/index.php`
- `tests/bootstrap.php`

Verified behavior:
- [x] Bootstrap loads the environment file before trusted PHP configuration.
- [x] Bootstrap registers the configuration repository as a shared instance.
- [x] DatabaseConfig, ConnectionFactory, DatabaseManager, and Router are registered as singletons.
- [x] Application is automatically constructed through dependency injection.
- [x] The front controller resolves the shared Router and Application from the container before dispatching the HTTP request.
- [x] `php tests/bootstrap.php` passed all 17 checks, including shared-instance and singleton behavior.
- [x] Real HTTP requests through the PHP development server returned the expected results: GET `/` = 200, GET `/reports` = 200, GET `/unknown` = 404, and POST `/reports` = 405 with `Allow: GET`.
- [x] The HTTP test confirms the existing reports response remains a placeholder; persistence is not connected.

**Full local verification reported on 2026-11-06**

The developer ran the following scripts successfully:
- [x] `php tests/environment.php` — 12 checks.
- [x] `php tests/config_repository.php` — 12 checks.
- [x] `php tests/container.php` — 26 checks.
- [x] `php tests/bootstrap.php` — 17 checks.
- [x] `php tests/http_lifecycle.php` — 8 checks.
- [x] `php tests/database_config.php` — 8 checks.
- [x] `php tests/database_factory.php` — all applicable checks passed; PostgreSQL and SQLite extension-absence checks were skipped because both extensions are installed; MySQL and MariaDB aliases passed.
- [x] `php tests/database_connection.php` — MariaDB 10.4.32 connection, parameterized query, connection caching, transaction commit, nested-transaction rejection, and rollback all passed.
- [x] Live HTTP smoke tests — all four route/method cases returned the expected status and response.
- [x] `git diff --check` — no whitespace errors.

**Commit and synchronization status**

- [x] The developer committed the verified local changes and rebased the two local commits onto the updated remote `main` history.
- [x] The final pushed commit is `e1487fe` (`feat: add dependency injection container and application bootstrap`).
- [x] The developer ran `git fetch origin` and confirmed `main` and `origin/main` both point to `e1487fe`.
- [x] `git status -sb` returned `## main...origin/main`, confirming the local branch is synchronized and the working tree is clean.
- [x] The remote `context.md` update from `0928f10` is included in the history before the application-container commit.
- [x] No force-push or destructive reset was used.

### 5.10 HTTP middleware contract and pipeline

**Status: Implemented, locally tested, committed, and pushed to GitHub `main` on 2026-11-06.** Commit: `24e9696` (`feat: add HTTP middleware pipeline`).

**Files added:**
- `framework/src/Http/Contracts/RequestHandlerInterface.php`
- `framework/src/Http/Middleware/MiddlewareInterface.php`
- `framework/src/Http/Middleware/MiddlewareRequestHandler.php`
- `framework/src/Http/Middleware/MiddlewarePipeline.php`
- `tests/http_middleware.php`

**Files modified:**
- `framework/src/Application/Application.php`
- `framework/src/Routing/Router.php`

**Architecture and behavior:**
- [x] Added a request-handler contract with `handle(Request): Response`.
- [x] Added a middleware contract with `process(Request, RequestHandlerInterface): Response`.
- [x] Added a handler adapter that wraps each middleware and delegates to the next handler.
- [x] Added a pipeline that wraps the destination in reverse registration order, producing execution in registration order and response unwinding in reverse order.
- [x] Added per-Application middleware registration through `addMiddleware()`.
- [x] Made Router implement `RequestHandlerInterface`; its `handle()` delegates to the existing `dispatch()`, preserving the router's existing API and behavior.
- [x] Preserved the `Application(Router $router)` constructor signature, keeping existing container-based construction compatible.
- [x] Middleware may return a response without invoking the next handler, allowing short-circuit behavior.
- [x] Applications with no registered middleware continue to work.

**Local verification reported by the developer on 2026-11-06:**
- [x] Syntax checks passed for all seven changed/added PHP files.
- [x] `php tests/http_middleware.php` passed all six assertions: route reaches HTTP 200, original Request is forwarded, registration/response order is correct, middleware can short-circuit with HTTP 401, short-circuit prevents route execution, and an application without middleware still works.
- [x] `php tests/http_lifecycle.php` passed all eight existing HTTP lifecycle assertions.
- [x] `php tests/bootstrap.php` passed all 17 application bootstrap assertions, including automatic Application construction through dependency injection.
- [x] `git diff --check` and `git diff --cached --check` reported no whitespace errors.
- [x] Seven intended files were staged and reviewed; commit `24e9696` was created successfully.
- [x] `git push origin main` succeeded, advancing remote `main` from `0824a02` to `24e9696`.

**Scope and limitations:**
- This milestone covers the initial middleware contract and pipeline only. Request IDs and structured HTTP request logging were added in the subsequent milestone documented in section 5.11. Security-header middleware, trusted-proxy policy, session service, authentication, and authorization middleware are not yet implemented.
- Middleware is registered on each Application instance. Global middleware groups, route-specific middleware, middleware aliases/priorities, and configurable middleware resolution are not implemented.
- The test script is a standalone PHP test; no PHPUnit dependency or general test runner has been introduced.
- This milestone does not connect CivicPulse reports to database persistence.

**Definition of Done:** the middleware contract, registration, execution order, response unwinding, short-circuit behavior, no-middleware compatibility, existing HTTP lifecycle regression tests, and container bootstrap regression tests all passed locally. The implementation is committed and pushed as `24e9696`.

### 5.11 HTTP request IDs and structured request logging

**Status: Implemented, locally tested, committed, and pushed to GitHub `main` on 2026-11-06.** Source commit: `654cbd7` (`feat: add HTTP request IDs and request logging`).

**Files added:**
- `framework/src/Http/Middleware/RequestIdMiddleware.php`
- `framework/src/Http/Middleware/RequestLoggingMiddleware.php`
- `framework/src/Logging/LoggerInterface.php`
- `framework/src/Logging/ErrorLogLogger.php`
- `tests/http_request_logging.php`

**Files modified:**
- `framework/src/Http/Request.php`
- `framework/src/Http/Response.php`
- `bootstrap/app.php`
- `public/index.php`
- `tests/bootstrap.php`

**Architecture and behavior:**
- [x] Added request attributes to `Request` so middleware and downstream handlers can share request-scoped metadata.
- [x] Added immutable `Response::withHeader()`, replacing an existing header case-insensitively and validating the resulting response through the constructor.
- [x] Added `RequestIdMiddleware`, which generates a cryptographically secure 128-bit random request ID and stores its 32-character lowercase hexadecimal representation on the Request.
- [x] Added the `X-Request-ID` response header so clients can correlate a response with server-side logs.
- [x] Added `LoggerInterface` with a structured level/message/context contract and an `ErrorLogLogger` implementation that writes JSON records through PHP's `error_log()`.
- [x] Registered the logger as a singleton in the application service container.
- [x] Registered request-ID middleware before request-logging middleware in the front controller, ensuring the ID is available to log records and is attached to the final response.
- [x] Logged successful requests at `info` level with request ID, method, path (without query string), response status, and elapsed duration in milliseconds.
- [x] Logged failed requests at `error` level with request ID, method, path, duration, and exception class. Exception messages are deliberately excluded from the structured context.
- [x] Made logging best-effort: a logger failure does not interrupt a successful HTTP response and does not replace the original application exception.
- [x] Preserved exception propagation; the middleware logs and rethrows rather than converting application exceptions into responses.

**Local verification reported by the developer on 2026-11-06:**
- [x] Syntax checks passed for the ten changed/added PHP files.
- [x] `php tests/http_request_logging.php` passed all 19 assertions, covering generated and distinct request IDs, response/request ID consistency, structured success and failure records, query-string exclusion, status and duration fields, exception propagation, omission of exception messages, and logging-failure isolation for both successful and failed requests.
- [x] `php tests/http_middleware.php` passed all six middleware pipeline assertions.
- [x] `php tests/http_lifecycle.php` passed all eight HTTP lifecycle assertions.
- [x] `php tests/bootstrap.php` passed all 19 bootstrap assertions, including logger interface resolution and singleton behavior.
- [x] `git diff --check` and `git diff --cached --check` passed without whitespace errors.
- [x] Exactly ten intended files were staged and reviewed; the source commit was created as `654cbd7`.
- [x] `git push origin main` succeeded, advancing remote `main` from `396b827` to `654cbd7`.
- [x] The developer verified `git status -sb` as `## main...origin/main` and `git log -1 --oneline` as `654cbd7`, confirming the local branch is synchronized and the working tree is clean.

**Scope and limitations:**
- The current logger writes JSON to PHP's configured error log. File rotation, remote log shipping, retention policy, log levels/configuration, and production observability integration are not implemented.
- The request ID is generated by the application for each request; accepting and validating externally supplied correlation IDs is not implemented.
- Security headers, trusted-proxy handling, sessions, authentication, and authorization middleware remain future work.
- This milestone does not connect CivicPulse reports to database persistence.

**Definition of Done:** request-scoped ID generation, response header propagation, structured success/failure logging, logging-failure isolation, middleware ordering, container registration, and regression compatibility all passed the reported local tests. Source is committed and pushed as `654cbd7`.

### 5.12 Configurable HTTP security headers

**Status: Implemented, locally tested, committed, and pushed to GitHub `main` on 2026-11-06.** Source commit: `9daac38` (`Add configurable HTTP security headers`).

**Files added:**
- `config/security.php`
- `framework/src/Http/Middleware/SecurityHeadersMiddleware.php`
- `tests/http_security_headers.php`

**Files modified:**
- `public/index.php`

**Architecture and behavior:**
- [x] Added centralized `security.headers` configuration, loaded through the existing `ConfigRepository` without adding a separate configuration-loading path.
- [x] Added `SecurityHeadersMiddleware`, which delegates to the next handler, validates the configured header map and string names/values, then adds headers to the returned immutable Response using `Response::withHeader()`.
- [x] Configured `X-Content-Type-Options: nosniff`.
- [x] Configured `X-Frame-Options: DENY`.
- [x] Configured `Referrer-Policy: strict-origin-when-cross-origin`.
- [x] Configured `Permissions-Policy: camera=(), microphone=(), geolocation=(self)`; same-origin geolocation is allowed for potential CivicPulse location-reporting functionality.
- [x] Registered middleware after request-ID and request-logging middleware in the front controller. It decorates normal and short-circuit responses before request logging observes the completed response.
- [x] Existing response headers are preserved unless a configured header replaces the same header case-insensitively.

**Local verification reported by the developer on 2026-11-06:**
- [x] PHP syntax checks passed for `config/security.php`, `framework/src/Http/Middleware/SecurityHeadersMiddleware.php`, `tests/http_security_headers.php`, and `public/index.php`.
- [x] `php tests/http_security_headers.php` passed all 11 assertions, covering all four configured headers, preservation of existing/unrelated headers, case-insensitive replacement without duplicates, short-circuit response status/header behavior, and rejection of invalid configuration.
- [x] Regression tests passed: `php tests/http_middleware.php` (6 assertions), `php tests/http_request_logging.php` (19 assertions), `php tests/http_lifecycle.php` (8 assertions), and `php tests/bootstrap.php` (19 assertions).
- [x] Real HTTP checks using the PHP development server and `curl -i` verified `GET /` (200), `GET /reports` (200), an unknown route (404), and `POST /reports` (405 with `Allow: GET`). All four responses included the four configured security headers and a distinct `X-Request-ID`.
- [x] `git diff --check` and staged diff checks passed without whitespace errors.
- [x] Four intended files were reviewed, committed as `9daac38`, and pushed successfully; the developer verified `git status -sb` as `## main...origin/main` and `git log -1 --oneline` as `9daac38`, confirming a clean working tree synchronized with `origin/main`.

**Scope and limitations:**
- This milestone implements the four configured browser security headers only. Content Security Policy (CSP), HSTS, and other deployment-specific headers are not configured.
- Trusted-proxy handling is not implemented. Forwarded client IP, host, and scheme headers must not be trusted until explicit proxy trust configuration and address/chain validation are designed and tested.
- The middleware decorates responses returned through the normal application pipeline, including tested 404 and 405 responses. The request-logging middleware still rethrows unhandled application exceptions; centralized exception-to-response handling is not yet implemented, so do not claim that this middleware guarantees headers on unhandled exception responses.
- No production HTTPS or reverse-proxy deployment verification has been performed.
- This milestone does not connect CivicPulse reports to database persistence.

**Definition of Done:** all 11 dedicated security-header assertions, the 52 regression assertions across the four existing suites, PHP syntax checks, whitespace checks, and four real HTTP response checks passed as reported by the developer. Source commit `9daac38` is pushed to `origin/main` and the working tree is clean.

### 5.13 Trusted-proxy handling and request network metadata

**Status: Implemented, locally tested, committed, and pushed to GitHub `main` on 2026-11-06.** Source commit: `2087a7c` (`Add trusted proxy support`).

**Files added:**
- `config/trusted_proxies.php`
- `framework/src/Http/TrustedProxyResolver.php`
- `tests/request_metadata.php`
- `tests/trusted_proxy_resolver.php`

**Files modified:**
- `framework/src/Http/Request.php`
- `framework/src/Application/Application.php`
- `tests/bootstrap.php`

**Architecture and behavior:**
- [x] Added explicit trusted proxy IP/CIDR configuration. The default proxy list is empty, so forwarded metadata is not trusted by default.
- [x] Added IPv4 and IPv6 exact-address and CIDR matching, including partial-byte network prefixes.
- [x] Added a dedicated `TrustedProxyResolver` to validate trust and resolve client IP and scheme metadata.
- [x] Resolve `X-Forwarded-For` only when the immediate connecting peer (`REMOTE_ADDR`) is configured as trusted. Walk the validated chain from right to left, skip trusted proxy hops, and select the first untrusted address. Limit the chain to 20 addresses; malformed chains fall back to the immediate remote address.
- [x] Accept `X-Forwarded-Proto` only from a trusted immediate proxy and only when it contains one supported `http` or `https` value. Otherwise retain the direct connection scheme.
- [x] Keep forwarded-host trust disabled by default. When explicitly enabled, accept a validated `X-Forwarded-Host` only from a trusted immediate proxy.
- [x] Add host syntax validation for direct and forwarded host values, including domain, IPv4, bracketed IPv6, and valid optional port forms. Syntax validation is not a host authorization policy.
- [x] Expose resolved request metadata through `Request::getClientIp()`, `getScheme()`, `getHost()`, and `isSecure()`.
- [x] Integrate the resolver into `Request::createFromGlobals()` and Application's container-based construction while preserving the existing `Application(Router)` usage.
- [x] Harden handling of duplicate security-sensitive forwarded headers: conflicting separately exposed duplicate values are discarded; identical duplicates and valid comma-separated XFF chains are handled deliberately. Ordinary header duplicate behavior remains unchanged.

**Local verification reported by the developer on 2026-11-06:**
- [x] Eight regression scripts passed: `tests/bootstrap.php`, `tests/container.php`, `tests/trusted_proxy_resolver.php`, `tests/request_metadata.php`, `tests/http_lifecycle.php`, `tests/http_middleware.php`, `tests/http_request_logging.php`, and `tests/http_security_headers.php`.
- [x] Seven PHP syntax checks passed for `Application.php`, `Request.php`, `TrustedProxyResolver.php`, `config/trusted_proxies.php`, and the three affected/new test files.
- [x] `git diff --check` reported no whitespace errors.
- [x] Real HTTP checks using the PHP development server confirmed that spoofed forwarded headers are ignored when no proxy is trusted. With loopback explicitly trusted for the test and forwarded-host trust enabled, the request resolved the supplied test client IP, HTTPS scheme, and forwarded host as expected. The temporary test configuration and route were removed/restored afterward.
- [x] The developer reviewed and committed the nine-file source change, including replacement of `folder-boilerplate-1.3.1.vsix` with `folder-boilerplate-1.5.0.vsix`.
- [x] The final source commit was rebased onto the remote deletion commit without force-pushing, then pushed successfully: `2087a7c` (`Add trusted proxy support`). The push advanced remote `main` from `5543634` to `2087a7c`.

**Scope and limitations:**
- The standardized `Forwarded` header is not supported in this first implementation; the resolver handles the `X-Forwarded-For` and `X-Forwarded-Proto` headers, and optionally `X-Forwarded-Host`.
- Forwarded-host trust remains opt-in. Host syntax validation does not replace a future application-level host allowlist.
- If a web server combines duplicate header lines into one value before PHP exposes them, the application cannot recover the original header-line boundaries. The duplicate-header checks cover conflicting duplicate entries PHP exposes separately; XFP and XFH values containing comma-separated ambiguity are rejected by their validators.
- The implementation has passed local unit/integration and real HTTP checks, but has not been verified in a production reverse-proxy or load-balancer deployment.
- This milestone does not implement report persistence, sessions, authentication, or authorization.

**Definition of Done:** trusted-proxy configuration, IPv4/IPv6/CIDR matching, forwarded client-IP and scheme resolution, opt-in forwarded-host handling, request metadata accessors, container integration, duplicate forwarded-header hardening, all eight regression scripts, seven syntax checks, and the reported real HTTP checks passed locally. Source commit `2087a7c` is pushed to `origin/main`.

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

- [x] `git status --short` returned no output before these checks, indicating a clean working tree at that point.- [x] PHP syntax checks passed for all seven implementation/test files.
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

- [x] Dependency injection / service container — implemented and locally tested; see section 5.9.
- [x] Application configuration and configuration access — implemented and locally tested; see section 5.9.
- [x] Environment-file loader, safe secret handling, and environment validation — initial loader implemented and tested; production secret-management policy remains future work.
- [ ] Centralized exception handling and error responses.
- [ ] A consistent automated test structure.

### Milestone 3 — Middleware and HTTP request pipeline

- [x] Middleware contract and pipeline/dispatcher — implemented through `RequestHandlerInterface`, `MiddlewareInterface`, `MiddlewareRequestHandler`, and `MiddlewarePipeline`; see section 5.10.
- [x] Middleware registration and ordering — `Application::addMiddleware()` registers middleware per application; execution follows registration order and response processing unwinds in reverse order. Short-circuit responses are supported and tested; see section 5.10.
- [x] Request logging and correlation/request IDs — generated request IDs, response header propagation, structured success/failure logging, and logging-failure isolation implemented and tested; see section 5.11.
- [x] Configurable HTTP security headers — implemented and tested; see section 5.12.
- [x] Trusted-proxy handling with explicit proxy trust configuration and forwarded-header validation; implemented and tested locally, including real HTTP checks. See section 5.13.
- [x] Native PHP session abstraction, environment-driven session configuration, session manager, and session middleware — implemented and locally tested; application integration and persistence across requests verified in section 5.14. Changes remain local and have not been reported as committed or pushed.
- [ ] Authentication and authorization middleware when those features are introduced.

### Milestone 4 — Extensible database and storage foundation

**Initial local implementation:** PDO MySQL/MariaDB, PostgreSQL, and SQLite drivers, common driver contract, connection factory, configuration, manager, connection wrapper. MariaDB connection and basic operations have passed local tests. See section 5.8.

Relational database adapters to support over time:

- [x] MySQL/MariaDB PDO driver implementation (MariaDB runtime-tested).
- [x] PostgreSQL PDO runtime verification — PostgreSQL 18.6 locally integration-tested; see section 5.8.
- [x] SQLite PDO runtime verification: in-memory connection and representative query/transaction operations passed locally.
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

### Milestone 6 — Schema builder, migrations, models, and CivicPulse persistence

**Schema definition and fluent column API**

- [ ] Design a fluent schema/blueprint API for defining tables and columns in migrations.
- [ ] Support column types appropriate to supported engines: string/char/text, integer/big integer, boolean, decimal/float, date/time/timestamp, JSON, UUID, binary, and engine-specific types where needed.
- [ ] Support chainable column constraints/modifiers such as nullable, default, unsigned where supported, unique, index, primary key, auto-increment/identity, length/precision/scale, comment, and foreign-key references.
- [ ] Support table operations: create, alter, rename, drop, indexes, composite indexes, unique constraints, foreign keys, and constraint removal.
- [ ] Provide explicit fluent APIs with predictable semantics, for example: `$table->string('title', 180)->nullable()->unique()`, `$table->foreignId('user_id')->constrained('users')`, and `$table->timestamps()`.
- [ ] Keep schema definitions separate from model classes: migrations/schema builders describe storage; models describe application data and behavior. Do not make a model itself ambiguously act as a table blueprint.
- [ ] Compile schema operations through per-engine grammar/adapters. Detect unsupported features and fail clearly rather than silently emitting incorrect SQL.
- [ ] Test generated SQL and actual schema behavior separately for each supported relational engine.

**Migration system**

- [ ] Migration base contract/class and timestamped migration files with `up()` and `down()`.
- [ ] Migration repository/table that records applied migration identifiers, batches, and execution order.
- [ ] Commands for status, migrate, rollback, reset, refresh, and fresh rebuild, with destructive operations clearly confirmed/guarded.
- [ ] Batch-aware rollback, targeted steps, transactions where the database supports transactional DDL, and documented behavior where it does not.
- [ ] Concurrency protection so two deploy processes cannot apply the same migration simultaneously.
- [ ] Migration discovery, deterministic ordering, failure reporting, and recovery guidance.
- [ ] Database-specific schema grammars and integration tests for supported engines.

**Models and data mapping**

- [ ] A base Model with explicit table/primary-key configuration, attributes, safe hydration, inserts, updates, deletes, refresh, and serialization.
- [ ] Query API for common filtering, ordering, limits, pagination, aggregates, and parameterized conditions.
- [ ] Mass-assignment allowlists/guarding, attribute casts, timestamps, hidden/visible fields, and predictable null handling.
- [ ] Relationships needed by CivicPulse: belongs-to, has-one, has-many, and many-to-many, with explicit foreign/local keys.
- [ ] Eager loading and protection against accidental N+1 query patterns.
- [ ] Query scopes, model lifecycle events/hooks, soft deletes where justified, and optimistic/concurrency controls where needed.
- [ ] Model factories for repeatable test data, separate from production seeders.
- [ ] Avoid implementing a large ORM surface before the initial CivicPulse reporting workflow proves which features are needed.

**Seeders and test data**

- [ ] Seeder contract/base class and deterministic seeder runner.
- [ ] Seeder ordering/dependencies and idempotency strategy.
- [ ] Development seeders for report categories, sample users/roles, and representative civic reports.
- [ ] Separate safe production/reference-data seeding from destructive demo/test data.
- [ ] Model factories and database reset helpers for isolated tests.
- [ ] Database-backed integration tests for migrations, models, relationships, seeders, rollback, and constraints.

**Initial CivicPulse schema:** users, roles/permissions as required, report categories, reports, normalized location data where appropriate, report evidence metadata, report status history, assignments, and audit events. Finalize fields and constraints from actual reporting workflows rather than guessing a generic schema.

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
- [ ] `@method('PUT')` — render a validated method-override field for supported form methods.- [ ] `@if`, `@elseif`, `@else`, `@endif` — conditional rendering.
- [ ] `@foreach`, `@forelse`, `@empty`, `@endforeach` — safe collection iteration and empty states.
- [ ] `@include`, `@extends`, `@section`, `@yield`, `@endsection` — reusable layouts and partials.
- [ ] `@components("component.php")` — proposed component-rendering syntax for reusable, parameterized UI components; define its exact naming, data/slot, escaping, and nesting contract before implementation.
- [ ] `@call("include.php/style.css/main.js")` — proposed asset/include registration or resolution syntax; specify whether each path is a PHP partial, stylesheet, or script, how assets are emitted, ordering/deduplication, and path safety before implementation.
- [ ] `@build("include.php")` — proposed composition/build directive for assembling a reusable include or view fragment; define how it differs from `@include` and `@components` before implementation.
- [ ] Keep the three proposed directives distinct only if they provide genuinely different, documented behavior. If their responsibilities overlap, refine or consolidate the syntax before freezing a public API. All component and asset paths must be constrained to approved view/resource roots; no arbitrary file inclusion or user-controlled path execution.
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

### Milestone 15 — Built-in dashboard, admin panel, and access management

**Authentication and identity foundation**

- [ ] Implement secure authentication flows: registration where enabled, login/logout, password hashing, password change/reset, session rotation, remember-me only with a reviewed token design, and account status/lockout policies.
- [ ] Define user identity and account models with validated fields, unique identifiers, safe serialization, and protected credential handling.
- [ ] Implement authorization independently from authentication. A signed-in user is not automatically authorized to perform an action.
- [ ] Implement roles and permissions with explicit many-to-many relationships, permission naming conventions, role assignment/revocation, and deterministic permission evaluation.
- [ ] Add policies or equivalent resource-aware authorization for actions such as view, create, update, delete, verify, assign, publish, and administer.
- [ ] Enforce authorization in the server-side request/controller/service layer for every protected operation. UI visibility and template directives are convenience features only.
- [ ] Test unauthenticated, authenticated-but-forbidden, permitted, revoked-permission, inactive-account, and cross-user/resource access cases.

**Built-in administrative application**

- [ ] Provide a first-party admin area with its own route group, middleware, layout, navigation, dashboard, and configurable resource registry.
- [ ] Provide dashboard cards/widgets for pending report review, report status distribution, unresolved/high-priority issues, assignment workload, recent activity, and other metrics justified by actual CivicPulse workflows.
- [ ] Provide secure user, role, and permission administration, including role assignment and revocation with audit events.
- [ ] Provide administrative screens for report review, verification, duplicate grouping, prioritization, assignment, category/reference-data management, and status transitions as those domain services are implemented.
- [ ] Provide reusable resource views and actions with search, filtering, sorting, pagination, detail pages, validation, safe bulk operations, and clear empty/error states.
- [ ] Support configurable navigation, dashboard widgets, resource fields/actions, and per-resource policies without allowing configuration to bypass authorization.
- [ ] Record sensitive administrative actions in an append-only or tamper-evident audit design appropriate to the deployment, including actor, action, target, timestamp, and relevant change metadata while minimizing sensitive data.
- [ ] Add accessibility, responsive behavior, CSRF protection, output escaping, secure uploads, and administrative security tests.
- [ ] Keep the admin interface useful for CivicPulse first, while exposing stable extension points so the custom framework can reuse it in other applications.

**Reusable component and asset composition in templates**

- [ ] Build a first-class component/partial system with explicit inputs, optional slots/sections, predictable scope, escaping by default, and nested composition.
- [ ] Evaluate and formally specify the requested syntax `@components("component.php")` for rendering reusable components. Define whether it accepts data/props and slots; avoid ambiguous implicit variable sharing.
- [ ] Evaluate and formally specify `@call("include.php/style.css/main.js")` for registering or including PHP partials and front-end assets. Define typed asset handling, output locations, dependency ordering, duplicate prevention, cache behavior, and safe path resolution.
- [ ] Evaluate and formally specify `@build("include.php")` for composing/building a view or include. Document exactly how it differs from a component render or ordinary include; consolidate directives if distinct semantics cannot be justified.
- [ ] Support layouts, reusable components, partials, asset stacks/bundles, and page-level composition without repeated markup or duplicated script/style tags.
- [ ] Test nested components, data isolation, escaping, malformed syntax, asset ordering, duplicate registration, cache invalidation, and traversal/unauthorized-path attempts.
- [ ] Treat these strings as proposed template-language syntax, not PHP functions or already-implemented directives. Finalize the public API only after the template compiler and view runtime exist.

**Milestone completion criteria**

- [ ] A real administrator can sign in and manage users, roles, permissions, and relevant CivicPulse resources through the browser.
- [ ] Authorization is enforced server-side and covered by positive and negative integration tests.
- [ ] Dashboard figures are derived from real application data, not hard-coded demo values.
- [ ] Component and asset composition works end-to-end in rendered pages and passes security and caching tests.

---

### Milestone 14 — Framework developer experience, resource generators, and expressive APIs

**Resource/component configuration and generators**

- [ ] Build a configurable scaffolding/generator system so developers can create a resource and its related components consistently instead of manually creating disconnected files.
- [ ] Provide focused commands such as `make:resource`, `make:model`, `make:migration`, `make:seeder`, `make:factory`, `make:controller`, `make:middleware`, `make:request`/form-request, `make:policy`, and `make:event` where the corresponding subsystem exists.
- [ ] Support generator options to create related files together (for example, a resource with model, migration, seeder, factory, controller, validation/request class, policy, and route registration) while allowing each component to be generated independently.
- [ ] Provide configurable resource presets/profiles, naming conventions, namespaces, directory paths, table names, primary keys, timestamps, soft deletes, API/web mode, and selected components.
- [ ] Make generated files valid, minimal, documented, PSR-4-compatible, and consistent with the actual framework APIs; never generate placeholder methods that pretend to work.
- [ ] Preview planned files and detect collisions before writing; require an explicit force/overwrite option for existing files.
- [ ] Keep generator templates and defaults configurable without allowing project settings to bypass validation or security.
- [ ] Add tests for command arguments/options, naming and namespace resolution, generated file contents, collisions, and end-to-end generated resource behavior.

**Fluent schema and model developer APIs**

- [ ] Provide a readable migration schema DSL, for example `Schema::create('tasks', function (Blueprint $table): void { $table->string('title', 180)->nullable()->unique(); $table->timestamps(); });`.
- [ ] Provide a coherent model API for querying and manipulating records, while keeping model behavior distinct from schema/migration definitions.
- [ ] Support declarative model configuration for table, primary key, casts, fillable/guarded attributes, timestamps, relationships, and serialization.
- [ ] Add relationship and query conveniences only when backed by tested query behavior and explicit database portability rules.
- [ ] Document the API contract and maintain compatibility tests so fluent chains do not change unexpectedly.

**Framework command line and shortcuts**

- [ ] Stable application bootstrap and a clear public framework API.
- [ ] CLI command kernel, registry, help, arguments/options, exit codes, and safe console output.
- [ ] Commands for migrations, seeders, factories, resource generation, cache operations, route listing, and environment diagnostics.
- [ ] Consistent helper APIs for route URLs, redirects, request access, responses, configuration, sessions, validation, and authorization.
- [ ] Template engine with a documented directive syntax and safe compilation/caching.
- [ ] Optional facade-style access only if it remains testable, explicit, and does not obscure dependencies.
- [ ] Developer error pages in local mode and safe, non-sensitive error responses in production.
- [ ] Framework documentation covering setup, configuration, supported drivers, extension contracts, generators, and examples.
- [ ] Compatibility tests for public APIs and documented shortcuts so syntax does not change unexpectedly.


---

## 8. Important Technical Notes and Decisions

- **PHP version:** The current local CLI is PHP 8.5.12. Composer's project requirements should explicitly reflect the actual minimum PHP version the code supports; do not assume every deployment host has the same version.
- **Autoloading:** Use Composer's PSR-4 autoloader. When adding a class inside an existing mapped namespace directory, Composer does not need to be regenerated for every file. Regenerate autoload metadata when mappings or autoload configuration change.
- **Request body:** Keep the raw request body as a string at the HTTP boundary. Parse JSON or form data deliberately based on content type and the needs of a specific feature.
- **Query strings:** Query parameters are not part of the route path. For example, `/reports?page=2` and `/reports?page=3` should match the same route; the page value is input to filtering/pagination.
- **Responsibilities:** Request represents incoming HTTP data. Router matches a request to a handler. Application coordinates execution. Controller/application services implement application behavior. Response represents outgoing HTTP data.
- **Database:** the initial PDO connection foundation is present. MariaDB, SQLite, and PostgreSQL 18.6 have passed local runtime tests through CivicPulse's connection layer. PostgreSQL verification used the dedicated `civicpulse_test` database and passed connection, parameterized query, connection caching, temporary-table creation, transaction commit, and rollback. Maintain a per-engine support matrix; never equate a driver class or syntax check with runtime or production support.
- **Driver families:** relational PDO drivers, document stores, key-value stores, cache, sessions, queues, filesystem/object storage, mail, and logging are distinct adapter families. Define contracts around each family's actual behavior; do not create one universal `DriverInterface` that erases important differences.
- **User-confirmed driver coverage requirement (2026-11-05):** The user wants CivicPulse to support the full driver/integration scope tracked in the roadmap, not only the currently implemented MySQL/MariaDB, PostgreSQL, and SQLite PDO drivers. This includes the listed relational database options (SQL Server, Oracle, Firebird, Db2, ODBC and conditionally Sybase/FreeTDS), MongoDB/document storage where justified, Redis and other cache/key-value adapters, and the separate session, queue, lock, rate-limit, and related infrastructure adapters. Implement this breadth incrementally, with a distinct contract for each adapter family and a support matrix that distinguishes planned, implemented, syntax-checked, integration-tested, and production-verified. This is a long-term compatibility requirement, not a claim that every vendor is already supported or that every adapter should be implemented before CivicPulse needs it.
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

**Current next action:** The View/template foundation and its first HTTP integration have now been implemented and locally verified as recorded in section 5.16. The next development milestone is template composition: shared layouts, reusable partials/components, explicit data passing and scope, and tests for nested rendering, escaping, and safe path resolution. Begin by inspecting the current View/Compiler APIs and designing layout/partial behavior that fits the existing compiler rather than adding ambiguous directives. CivicPulse report persistence, authentication, and authorization remain unimplemented. Continue to distinguish local verification from committed/pushed repository state; source changes remain user-managed locally.

### 5.14 Session abstraction, configuration, and request lifecycle

**Status: Implemented and locally verified by the developer on 2026-11-06. Changes remain local; no session source commit or push has been reported.**

Implemented locally:

- [x] `SessionInterface` defines the session data contract: get, set, has, remove, clear, and session ID regeneration.
- [x] `NativeSession` adapts PHP's native session storage and rejects access when no active, valid session is available.
- [x] `config/session.php` provides environment-driven session enablement and cookie configuration, with validated booleans, non-negative lifetime, and automatic Secure-cookie mode.
- [x] `.env.example` documents session settings; the local `.env` contains corresponding development settings and remains ignored.
- [x] Framework-enforced PHP session policies require strict mode and cookie-only session IDs, and disable transparent session ID propagation.
- [x] `SessionManager` validates configuration, configures PHP session/cookie settings, starts and closes sessions, tracks ownership, enforces Secure cookies on HTTPS, and rejects insecure `SameSite=None` configuration.
- [x] `SessionMiddleware` starts the session before downstream handling, attaches the session adapter to the request, and closes the session after normal or exceptional downstream handling while preserving the original application exception.
- [x] Session manager and middleware are registered/resolved through the application container; bootstrap tests verify the manager singleton and the exact shared manager injected into the middleware.
- [x] The front controller registers session middleware after request ID, request logging, and security-header middleware, and before the router destination.
- [x] Application integration verifies session availability inside a real route, response propagation, session closure, and data persistence across two application requests.

**Developer-reported local verification on 2026-11-06:**

- [x] `php tests/session.php` — 12 assertions passed.
- [x] `php tests/session_config.php` — 24 assertions passed.
- [x] `php tests/session_manager.php` — 20 assertions passed.
- [x] `php tests/session_middleware.php` — 11 assertions passed.
- [x] `php tests/session_application.php` — 12 assertions passed.
- [x] `php tests/bootstrap.php` — 30 assertions passed, including session service dependency injection.
- [x] All six reported test suites passed: 109 assertions total.
- [x] The developer separately reported clean PHP syntax checks for `bootstrap/app.php`, `public/index.php`, and the new application session test.

**Scope and limitations:**

- The implementation currently uses PHP's native session handler. File/database/Redis session-store adapters remain future work and must be designed behind appropriate storage contracts if/when required.
- CSRF token generation/validation and CSRF middleware were implemented in the subsequent local milestone recorded in section 5.15. Authentication and authorization remain future work. `SameSite=Lax` is a useful cookie policy but does not replace CSRF token validation.
- Tests were run locally by the developer. The session changes have not been reported as committed or pushed, and no production deployment verification is claimed.

**Definition of Done:** session abstraction, configuration, manager, middleware, dependency-injection integration, and two-request application persistence all passed the six locally reported test suites (109 assertions). CSRF protection was subsequently implemented and locally verified in section 5.15.


### 5.15 CSRF protection and parsed request-body support

**Status: Implemented and locally verified by the developer on 2026-11-06. Changes remain local; no source commit or push has been reported.**

Implemented locally:

- [x] Request now stores parsed form data separately from the raw request body and exposes it through getParsedBody().
- [x] Request::createFromGlobals() supplies PHP's parsed $_POST data for ordinary POST form submissions without treating raw JSON as parsed form data.
- [x] CsrfTokenManager generates cryptographically secure 32-byte random tokens, stores them in the session, reuses valid tokens, and validates submissions using hash_equals().
- [x] CsrfMiddleware skips validation for GET, HEAD, and OPTIONS; validates state-changing requests using the X-CSRF-Token header or _token form field; rejects missing, malformed, conflicting, or invalid tokens and requests without an attached session.
- [x] CSRF rejection returns HTTP 403 with a plain-text content type and Cache-Control: no-store.
- [x] The CSRF token manager is registered as a container singleton, and the middleware resolves through the container with the shared token manager.
- [x] The front controller registers CSRF middleware after session middleware and before the router, ensuring the session is available for token validation.
- [x] An application-level integration test exercises token generation on GET, rejection of missing/invalid tokens, acceptance of a valid token, route non-execution on rejection, and session lifecycle behavior.

**Developer-reported local verification on 2026-11-06:**

- [x] php tests/request_parsed_body.php — 4 checks passed.
- [x] php tests/csrf_token_manager.php — 11 checks passed.
- [x] php tests/csrf_middleware.php — 27 checks passed.
- [x] php tests/csrf_application.php — 17 checks passed.
- [x] php tests/bootstrap.php — 35 checks passed, including CSRF service registration and dependency injection.
- [x] All five reported test scripts passed: 94 checks total.
- [x] The developer reported no syntax errors in the CSRF application test and successful execution of the complete listed CSRF test group.

**Scope and limitations:**

- Ordinary PHP-parsed POST form data is supported through $_POST. JSON and other non-form request bodies remain raw; clients using non-POST state-changing methods should submit the token through the X-CSRF-Token header.
- Token rotation policy beyond session lifecycle/regeneration has not yet been implemented as a separate policy.
- The template compiler/view system does not exist yet, so the planned @csrf directive is not implemented. The existing token manager and middleware provide the security mechanism that a future directive must use.
- Authentication and authorization are not implemented.
- These results are local developer-reported tests, not production security verification. Session and CSRF changes have not been reported as committed or pushed.

**Definition of Done:** parsed request-body support, token generation and validation, middleware behavior, container registration, middleware ordering, and end-to-end application integration passed the five locally reported test scripts (94 checks). The next milestone is the template/view foundation; the future @csrf directive must render a real hidden form field backed by this existing CSRF token manager.


### 5.16 Template/view foundation and first HTML route integration

**Status: Implemented and locally verified by the developer on 2026-11-06. The developer confirmed that the new HTML route works in the browser. Commit/push status for these View and Response changes has not been reported.**

Implemented locally:

- [x] Added the View subsystem under `framework/src/View/`: `View`, `ViewFinder`, `ViewRenderer`, `HtmlEscaper`, `Compiler/Compiler`, `Compiler/CompiledViewCache`, and the View exception classes.
- [x] `ViewFinder` resolves named templates within the configured `resources/views/` directory and rejects invalid names and traversal outside the approved directory.
- [x] The template compiler supports escaped `{{ ... }}` expressions and compiles templates to PHP. Templates are trusted application code; the supported expression scanner is not a complete PHP parser.
- [x] `CompiledViewCache` stores compiled templates in `storage/framework/views/`, uses source/compiler fingerprints for cache identity, writes temporary files, and renames compiled files into place.
- [x] `ViewRenderer` uses dependency injection for the compiled cache and HTML escaper, executes compiled templates with isolated extracted data, and handles output buffers and rendering exceptions.
- [x] `View` provides the application-facing `render($name, $data)` API.
- [x] Registered the View services in `bootstrap/app.php`, including explicit construction of `CompiledViewCache` with the storage path and `ViewFinder` with the resources path. Container tests verify resolution and singleton identity.
- [x] Added `Response::html()` with the default `text/html; charset=utf-8` content type, optional status code, and custom headers. Existing JSON response behavior remains available.
- [x] Updated route registration to receive the shared `View` service and added `GET /home`, which renders `resources/views/home.php` and returns an HTML response.
- [x] Updated `public/index.php` to resolve the shared View service and pass it to the route-registration callback. The Router and Application classes did not need modification.
- [x] Preserved the existing JSON endpoints: `GET /` and `GET /reports`.
- [x] Created the first application template, `resources/views/home.php`, with escaped dynamic title, heading, description, status, and year values.

**Developer-reported local test results:**

- [x] `php tests/view_finder.php` — 10 passed; the symlink-specific test was skipped because symlink creation was unavailable in the Windows environment.
- [x] `php tests/html_escaper.php` — 13 passed.
- [x] `php tests/view_compiler.php` — 15 passed.
- [x] `php tests/compiled_view_cache.php` — 11 passed.
- [x] `php tests/view_renderer.php` — 11 passed.
- [x] `php tests/view.php` — 8 passed.
- [x] `php tests/view_container.php` — 13 passed.
- [x] `php tests/response.php` — 17 passed.
- [x] All eight listed View/Response test scripts passed: 98 reported checks in total.
- [x] The developer reported successful browser rendering of `http://127.0.0.1:8000/home` ("works"). The existing JSON routes were intended to remain intact; no separate browser response capture was supplied in this checkpoint.

**Architecture decisions and limitations:**

- Keep rendering in the View subsystem and HTTP representation in `Response`; do not couple the Router or Application directly to template compilation.
- Pass the shared View service explicitly to route registration. Avoid global service state or a service locator.
- The compiled view cache is outside the public document root.
- The compiler supports its documented interpolation syntax only; it is not a full PHP parser and does not support every possible PHP lexical construct.
- The first `resources/views/home.php` template is a simple standalone page. Shared layouts, partials/components, slots/sections, and asset composition have not yet been implemented.
- Report persistence, authentication, authorization, and the CivicPulse administrative dashboard remain future work.
- These are local developer-reported results. Do not claim a source commit, push, clean working tree, or synchronization without subsequent verification.

**Next milestone:** implement shared layouts and reusable partials/components with explicit data passing and predictable scope. Inspect the existing compiler and renderer APIs first, define the smallest coherent composition contract, and add focused tests for nested composition, escaping, data isolation, malformed syntax, and path traversal before expanding the template language.

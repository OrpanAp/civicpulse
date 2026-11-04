# CivicPulse — Project Context & Development Tracker

> **Purpose of this file:** Preserve the project's original motivation, current technical state, decisions, and next actions. Update this file as work is completed so development can continue across sessions without relying on chat history.

**Last updated:** 2026-11-05  
**Repository:** https://github.com/OrpanAp/civicpulse  
**Default branch:** `main`  
**Project status:** Early development — framework foundations exist; the browser application is not running yet.

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

Known small hardening item: `getallheaders()` can return `false`; normalize its result to an array before passing it to the constructor:

```php
$headers = function_exists('getallheaders')
    ? (getallheaders() ?: [])
    : [];
```

This is a small correction, not a reason to delay the next major milestone.

### 5.3 Router

File: `framework/src/Routing/Router.php`

Implemented behavior:

- [x] Stores route handlers in a static route registry.
- [x] Registers GET routes.
- [x] Registers POST routes.
- [x] Checks whether a method/path pair is registered.
- [x] Dispatches a registered handler.

Current limitations to address during integration or a deliberate refactor:

- Dispatch currently accepts separate method and path strings, not a Request object.
- Route handlers are invoked without a Request argument.
- The return type is `mixed`, not a defined Response contract.
- Missing routes throw a generic `Exception`.
- The registry is static, so routes can be shared between tests or application instances.
- Direct calls to `addRoute()` do not normalize the method.

**Architecture decision still open:** retain the static router for the first working vertical slice, or refactor it to an instance-based router before Application integration. An instance-based router is generally easier to isolate in tests and inject into an Application, but do not silently change the existing design. Explain the trade-off and make one deliberate decision.

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

### 5.5 Documentation

- [x] `README.md` created and committed.
- [ ] Keep this `context.md` updated as the living development handoff and progress tracker.

---

## 6. Immediate Next Milestone: Make CivicPulse Run in a Browser

**Priority: Do this before adding more framework subsystems.**

The next milestone is a complete HTTP request-to-response lifecycle. At the end, a browser should be able to make a request to the local PHP server, pass through the application, match a route, execute a handler, and receive a real HTTP response.

Target flow:

```text
Browser
  |
  | HTTP request
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
Route handler / Controller
  |
  v
Response
  |
  | status + headers + body
  v
Browser
```

### Tasks

#### 6.1 Implement the Response object

Target file: `framework/src/Http/Response.php`

It should represent an outgoing HTTP response and support:

- [ ] Response body/content.
- [ ] HTTP status code.
- [ ] Response headers.
- [ ] Accessors for inspecting the response in tests.
- [ ] A `send()` method that applies the status and headers and writes the body.
- [ ] A JSON response helper that encodes data safely and sets the JSON content type.

Design principle: constructing a Response should not immediately send headers or print output. Keep response creation separate from sending it.

#### 6.2 Integrate Router with Request and Response

Target file: `framework/src/Routing/Router.php`

- [ ] Decide static versus instance-based router and document why.
- [ ] Dispatch using the incoming Request.
- [ ] Pass the Request to route handlers.
- [ ] Establish a clear handler return contract using Response.
- [ ] Return a proper 404 response when no route matches.
- [ ] Ensure HTTP methods are normalized consistently.

Do not add dynamic route parameters, route groups, named routes, or advanced routing features yet. First make basic routes work end-to-end.

#### 6.3 Implement the Application lifecycle

Target file: `framework/src/Application/Application.php`

- [ ] Make Application coordinate the Router and Request.
- [ ] Provide a testable method that handles a supplied Request and returns a Response.
- [ ] Provide the runtime entry method that creates a Request from globals and sends the resulting Response.
- [ ] Keep routing decisions out of the Request class.

#### 6.4 Add the public front controller

Target file: `public/index.php`

- [ ] Load Composer's autoloader.
- [ ] Create/configure the router.
- [ ] Load the route definitions.
- [ ] Create the Application.
- [ ] Run the Application.

#### 6.5 Register the first CivicPulse routes

Target file: `routes/web.php`

Start with a small but real set of routes:

- [ ] `GET /` — a simple CivicPulse landing/health response.
- [ ] `GET /reports` — a JSON response representing the reports endpoint (initially empty or clearly marked as not yet connected to persistence).

These are initial integration routes, not the final reporting implementation.

#### 6.6 Run locally and verify

- [ ] Configure PHP's built-in server with the public directory as document root.
- [ ] Ensure non-file paths such as `/reports` are forwarded to the front controller using a development router script if needed.
- [ ] Open the root route in a browser.
- [ ] Request the reports route and inspect its HTTP status, headers, and body.
- [ ] Verify an unknown path produces a 404 response.
- [ ] Verify a wrong HTTP method does not accidentally execute another method's handler.
- [ ] Add automated tests for the Response, Router, and Application behavior.

Suggested local server command once the front controller and development router script exist:

```bash
php -S localhost:8000 -t public public/router.php
```

The server command is a future step; do not expect it to work until the required files are implemented.

### Definition of Done

This milestone is complete only when all of the following are true:

- [ ] A request to `http://localhost:8000/` reaches CivicPulse and returns a visible response.
- [ ] A request to `http://localhost:8000/reports` returns the expected response.
- [ ] The response has the intended HTTP status and content type.
- [ ] An unknown route returns HTTP 404.
- [ ] The behavior is tested, not merely inferred from the code.
- [ ] This tracker is updated with completed tasks and any remaining limitations.

---

## 7. Later Milestones — Product-Driven Framework Roadmap

These are ordered broadly by dependency. Do not start them before the browser lifecycle milestone is complete.

### Milestone 2 — Framework application foundation

- [ ] Dependency injection / service container.
- [ ] Application configuration.
- [ ] Environment configuration and safe secret handling.
- [ ] Centralized exception handling and error responses.
- [ ] A proper automated test structure.

### Milestone 3 — Middleware and request pipeline

- [ ] Middleware contract.
- [ ] Middleware pipeline / dispatcher.
- [ ] Request logging.
- [ ] Security-related middleware.
- [ ] Authentication middleware when authentication is introduced.

### Milestone 4 — Database foundation

- [ ] Choose and configure PostgreSQL for the application.
- [ ] Database connection management using PDO.
- [ ] Configuration-driven connection settings.
- [ ] Safe parameterized queries.
- [ ] Query builder for the needs of CivicPulse.
- [ ] Database integration tests.

### Milestone 5 — Schema and persistence

- [ ] Migration interface and runner.
- [ ] Migration tracking.
- [ ] Initial schema for users, reports, categories, locations, and report status history.
- [ ] Models/repositories or another deliberate persistence pattern.
- [ ] Seeders for local development data.

### Milestone 6 — First complete CivicPulse reporting workflow

Build a real end-to-end vertical slice:

- [ ] Define the report data model and lifecycle.
- [ ] Validate a submitted report.
- [ ] Store a report and its location.
- [ ] Expose report details and a report listing.
- [ ] Handle expected errors and invalid input.
- [ ] Test the workflow against a real test database.

A complete vertical slice is more important than implementing a large number of generic framework features.

### Milestone 7 — Trust, verification, and duplicate handling

- [ ] Report review and verification states.
- [ ] Similar-report detection strategy.
- [ ] Duplicate grouping and linking.
- [ ] Preserve individual citizen submissions while linking them to a shared issue where appropriate.
- [ ] Audit history for important actions.

### Milestone 8 — Users, roles, and operational workflow

- [ ] Authentication and secure password handling.
- [ ] Authorization and policies.
- [ ] Citizen, reviewer, and staff permissions.
- [ ] Assignment and prioritization.
- [ ] Status transitions and responsible-party tracking.

### Milestone 9 — Public experience and operational features

- [ ] Evidence/photo upload with validation and safe storage.
- [ ] Location-based browsing and filtering.
- [ ] Notifications.
- [ ] Public issue status pages.
- [ ] Administrative views and analytics.
- [ ] API documentation.

### Milestone 10 — Production readiness

- [ ] Security review.
- [ ] Rate limiting and abuse prevention.
- [ ] Logging and monitoring.
- [ ] Performance testing.
- [ ] Backup and recovery strategy.
- [ ] Deployment configuration and CI/CD.
- [ ] Operational documentation.

---

## 8. Important Technical Notes and Decisions

- **PHP version:** The current local CLI is PHP 8.5.12. Composer's project requirements should explicitly reflect the actual minimum PHP version the code supports; do not assume every deployment host has the same version.
- **Autoloading:** Use Composer's PSR-4 autoloader. When adding a class inside an existing mapped namespace directory, Composer does not need to be regenerated for every file. Regenerate autoload metadata when mappings or autoload configuration change.
- **Request body:** Keep the raw request body as a string at the HTTP boundary. Parse JSON or form data deliberately based on content type and the needs of a specific feature.
- **Query strings:** Query parameters are not part of the route path. For example, `/reports?page=2` and `/reports?page=3` should match the same route; the page value is input to filtering/pagination.
- **Responsibilities:** Request represents incoming HTTP data. Router matches a request to a handler. Application coordinates execution. Controller/application services implement application behavior. Response represents outgoing HTTP data.
- **Database:** PostgreSQL is the intended database direction, but it has not yet been integrated.
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

**Current next action:** Implement `framework/src/Http/Response.php`, then integrate it with the existing Router and build the Application lifecycle as one working milestone.

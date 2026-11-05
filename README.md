# CivicPulse

**A civic infrastructure reporting platform built on a custom PHP framework.**

CivicPulse is being developed to help communities report infrastructure problems, follow their progress, and make local issues easier to understand and act on.

The project has two connected goals:

1. Build a practical civic reporting platform around real-world problems.
2. Design and implement the PHP framework that powers it, learning and applying the architecture behind modern frameworks rather than relying on an existing application framework.

> **Project status:** Early development. The framework foundation is being built. The complete web application and its reporting workflows are not available yet.

---

## The Problem

Infrastructure problems are often reported through scattered channels, making it difficult to track whether an issue has been acknowledged, investigated, or resolved.

CivicPulse is intended to provide a more structured process for issues such as:

- Potholes and damaged roads
- Water supply interruptions and drainage problems
- Power outages and damaged electrical infrastructure
- Waste collection problems
- Public infrastructure damage
- Other local service disruptions

## Planned Platform Capabilities

The following capabilities describe the intended product. They are planned features, not claims about the current implementation.

- **Issue reporting:** Citizens can submit a description, category, and location for an infrastructure problem.
- **Evidence:** Reports can include photographs and other supporting information.
- **Verification:** Reports can be reviewed and verified before being treated as confirmed issues.
- **Duplicate detection:** Similar reports about the same issue can be grouped instead of fragmenting the public record.
- **Location-based grouping:** Issues can be organized by area.
- **Prioritization and assignment:** Authorized staff can assess urgency and assign work.
- **Status tracking:** Citizens can follow progress from submission through investigation and resolution.
- **Audit history:** Important status changes and actions can be recorded.
- **Notifications and analytics:** Updates and aggregated information can help keep the process transparent.

## Technology

| Area | Technology |
| --- | --- |
| Language | PHP |
| Dependency management | Composer |
| Autoloading | Composer PSR-4 |
| Version control | Git and GitHub |
| Database | Planned: PostgreSQL |
| Framework | Custom PHP framework under development |

The framework is intentionally being developed inside this repository under `framework/src/`.

## Repository Structure

The repository is organized to keep the framework, application, configuration, and tests separate.

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
├── composer.json
└── README.md
```

Some directories are architectural placeholders for upcoming work and may not contain implemented features yet.

## Framework Development

The custom framework is being built incrementally around a request-to-response lifecycle.

Planned framework components:

1. **Autoloading:** PSR-4 namespace mapping through Composer.
2. **HTTP layer:** Request and Response objects for handling incoming requests and outgoing responses.
3. **Routing:** Route registration, matching, and dispatching.
4. **Application lifecycle:** A central application class and public entry point.
5. **Dependency injection:** A service container for constructing and managing application dependencies.
6. **Middleware:** A pipeline for request handling, authentication, and other cross-cutting concerns.
7. **Database layer:** Database connection management and a query builder.
8. **Persistence:** Models and a migration system.
9. **Validation and security:** Input validation, authorization, and secure request handling.
10. **Application services:** Events, notifications, and other reusable services as the platform grows.

### Current Implementation

The current foundation includes:

- Composer PSR-4 autoloading for `CivicPulse\\Framework\\` and `App\\`.
- An HTTP `Request` class that extracts the request method and path, parses query parameters, normalizes header names, and exposes the raw request body.
- An initial `Router` implementation with GET and POST route registration and dispatch.

The response lifecycle, application bootstrap, middleware pipeline, database integration, and civic reporting workflows are still under development.

## Getting Started

### Requirements

- PHP 8.2 or later
- Composer
- Git

### Clone the repository

```bash
git clone https://github.com/OrpanAp/civicpulse.git
cd civicpulse
```

### Install Composer dependencies

```bash
composer install
```

Composer generates the autoloader using the PSR-4 mappings in `composer.json`.

### Check the current Request/autoloading foundation

```bash
php tests/autoload.php
```

This is currently a small foundation check, not a complete automated test suite or a runnable web application. A browser-based application entry point and development-server workflow will be added as the application lifecycle is implemented.

## Development Principles

- Build features around real civic problems rather than tutorial-only examples.
- Keep framework responsibilities separate from application and business logic.
- Prefer explicit, understandable architecture over unnecessary abstraction.
- Validate input and handle failures deliberately.
- Test behavior as complete features are introduced.
- Document implemented behavior separately from planned functionality.

## Roadmap

- [x] Initialize the repository and project structure
- [x] Configure Composer PSR-4 autoloading
- [x] Implement the initial HTTP Request object
- [x] Implement the initial Router
- [ ] Implement HTTP Response handling
- [ ] Connect routing to the application lifecycle
- [ ] Add the public front controller and run the app locally
- [ ] Build a dependency injection container
- [ ] Add middleware support
- [ ] Implement database connections and query building
- [ ] Add migrations and persistence
- [ ] Build the first complete civic issue reporting workflow
- [ ] Add verification, duplicate grouping, and status history
- [ ] Add authentication, authorization, and role-based workflows
- [ ] Expand automated tests and documentation

Roadmap items will be checked off as they are implemented and tested.

## Contributing

The project is under active development. Suggestions, issue reports, and focused contributions are welcome. Before working on a substantial change, open an issue or discussion so the intended behavior and design can be agreed upon.

## License

This project is licensed under the MIT License. See the `LICENSE` file when it is added to the repository.

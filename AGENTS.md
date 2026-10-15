Jedu API (Laravel). Project rules below override generic Boost guidelines on conflict.

## Source of Truth
For repo work, locate affected code through Graphify/search; read scoped code and tests first. Consult relevant digest sections only for unresolved domain meaning, workflow boundaries, or cross-module effects, or when path rules require them. `docs/Digestions/CODEBASE_DIGEST.md` is optional orientation for unfamiliar domains. No mandatory digest preload. Reuse context already read; avoid reading code and digests for same facts. Digest maintenance remains required below.
Digests also serve chatbots receiving only uploaded digestion files; preserve standalone reference coverage. With repo access, current code, migrations, configuration, and tests decide implementation details. Without access, use supplied docs and flag missing details; never infer contracts from class names. Scope unclear → ask, no assumptions. Smallest change that fulfills requirement, preserve API contracts, no unrelated refactor.

## Path-Scoped Rules
Before editing any file, check .ai/rules/index.md for a glob matching the path in scope. Read the matching rule file first — it is load-bearing, not optional context.

## API Contract
- spatie/laravel-data DTOs only. NO Form Requests. NO API Resources — this overrides the Boost "APIs & Eloquent Resources" guideline below.
- apiResponse() for every response. No raw arrays/JSON.
- Business logic in app/Actions/. Controllers thin, no logic in them.

## Testing
PEST only. AuthTestTrait, not actingAs(). Database reset is owned by the shared `Tests\TestCase` — never add a refresh trait to an individual test. Command shape and the one-run-per-Sail-stack rule: `.ai/rules/tests.md`.

## Commands
- `sail artisan` always, never bare `php artisan` (same for composer and node).
- Pint: `sail bin pint --dirty --format agent` before finalizing any PHP change. `--dirty` (or an explicit file list) is mandatory — a repo-wide `sail bin pint --format` rewrites unrelated files that were already drifted.
- Database: `scripts/db.sh "<sql>"` — add `-d <database>` to target a database other than the one in `.env`.
- Never pipe a check through `tail`/`head` without `set -o pipefail`; the pipe's exit status hides the failure and the harness reports exit 0.

## Scratch Space
`/tmp` does not survive a tool call and writes outside the workspace are sandbox-denied, so keep scratch inside the repo:

- `.tmp/` — git-ignored, ephemeral: backups, probes, generated files, logs. Delete freely; never reference it from a tracked file.
- `.scratch/` — git-ignored, session-scoped: handoffs from or to another session (`handoff-<topic>-<date>.md`) and per-session exploration notes, referenced by bare filename (`@handoff-…`). A handoff states its base commit SHA and one verification command per claim.
- Anything that must survive a clone, a review, or a later session goes in `docs/` (tracked): plans and ideas to revisit → `docs/plans/`; agent-facing references → `docs/agents/`. `.scratch/` is never committed — if a note matters later, move it into `docs/` and delete the scratch copy.
- When a skill tells a subagent to save notes or artifacts "outside the repo", use `.scratch/<topic>/` — writes outside the workspace are denied.

## Tools & Delegation
- Laravel Boost skills are installed under `.agents/skills/**` and the harness loads them; activate the matching one before working in its domain. Framework and package guidance lives there and in the Boost blocks at the end of this file; `.ai/rules/` holds this project's own rules.
- The Boost MCP tools (`search-docs`, `database-query`, `database-schema`, `browser-logs`, `get-absolute-url`, `record-rule`) only exist where the harness starts the server in `.mcp.json`. Where they are absent: read the package source under `vendor/`, use `sail artisan tinker --execute '…'` and `scripts/db.sh "<sql>"` for data, `sail artisan route:list` / `config:show` for wiring, and the official docs on the web for version-specific APIs.
- Record a durable rule by writing `.ai/rules/<topic>.md` (with `paths:` front matter) and adding a row to `.ai/rules/index.md`. That is the only shared place rules persist.
- Deployment: Deployer → Nexus/frankenphp (`.forgejo/workflows/pipeline.yml`, `deploy.php`).
- Delegation: a subagent writes its full report to `.scratch/<topic>.md` and returns at most ~20 lines plus that path. Never let a subagent run tests while your own Pest run is in flight.
- Do not poll: `list_agents` and `job_output` return the same state until a job finishes, and completion notices arrive on their own. Never `sleep` to wait for a background job.
- Read whole files with the `read` tool, not `cat`/`sed -n` chunking: shell inspection does not satisfy read-before-edit, and re-printing ranges wastes context.

## Workspaces
Default to the primary checkout on `main`. Create a worktree when the user asks for one, when the skill they invoked requires it (`/implement-spec` gives each implementer subagent its own), or after they approve one — otherwise ask first. Never keep the same change in two trees: read-before-edit is tracked per absolute path.
Inside a worktree, git-dependent tooling fails in Sail because the worktree's `.git` file points outside the bind mount, so `pint --dirty` cannot see the diff. Pass files explicitly: `git diff --name-only main...HEAD -- '*.php' | xargs vendor/bin/sail bin pint --format agent`.

Worktrees live under `.worktrees/<name>` (ignored). Create one with `git worktree add .worktrees/<name> -b <branch> <base>`, then run `scripts/worktree-setup.sh .worktrees/<name>`; it copies `.env`, allocates the port block and Compose project, links the Typesense model cache, starts Sail and migrates. It is idempotent and must exit 0 before work starts there. Remove one with `scripts/worktree-teardown.sh .worktrees/<name>`. Never run those setup steps by hand, copy `docker/typesense-data` between worktrees, or delete a worktree directory by hand.

## Digest Maintenance
Before finalizing any code task, check whether changes affect documented models/relations, schema, workflows/services/jobs, API contracts, or navigation. Update affected digest sections in same task, even when no commit requested:

- Models/relations → `DIGEST_DATA_MODELS.md`; columns/indexes/constraints → `DIGEST_SCHEMA.md`.
- Workflows, side effects, invariants, failure behavior → `DIGEST_CORE_LOGIC.md`.
- Routes, guards, request/response contracts → `DIGEST_API_INTERFACES.md`.
- Domain boundaries or navigation entry points → `CODEBASE_DIGEST.md`; keep overview compact.

For maintenance, inspect only affected digest sections and dependencies after determining code changes; reuse sections already read. Update changed facts and add coverage for new models, schema, workflows, and API interfaces. Preserve details needed by chatbots receiving only digestion files: relationships/casts, columns/constraints, workflow effects/failures, endpoint auth and request/response contracts. Remove repeated facts between docs through cross-links; retain brief context needed to understand each section. Code accessibility alone is no reason to remove reference detail. Digestion reading guidance must stand alone; AGENTS.md, README, ADRs, and code are not part of chatbot upload unless explicitly supplied. Formatting, internal refactors, and test-only changes need no digest edits unless documented behavior or paths change. Before final response, verify affected documentation matches change; mention updates or why none needed.

## Commits
Conventional Commits: `type(scope): imperative description`
e.g. `feat(api-product): add endpoint for creating new products`

## Agent skills

### Issue tracker

Issues are tracked in Forgejo at `https://git.jedu.ir/kuro/Jedu-api`; GitHub is a mirror only. See `docs/agents/issue-tracker.md`.

### Triage labels

Triage uses the five default Matt Pocock engineering-skill labels. See `docs/agents/triage-labels.md`.

### Domain docs

Domain documentation uses the single-context layout. See `docs/agents/domain.md`.

---
<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `vendor/bin/sail artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `vendor/bin/sail artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/sail bin pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `vendor/bin/sail artisan test --compact`.

=== maatwebsite/excel/core rules ===

# Laravel Excel

- Use `maatwebsite/excel` for spreadsheet exports, imports, queued spreadsheet work, CSV handling, and PhpSpreadsheet integration in Laravel applications.
- Prefer explicit export/import classes with package concerns over ad-hoc spreadsheet generation in controllers, jobs, or commands.
- Activate the `laravel-excel` skill when working with `Excel::download()`, `Excel::store()`, `Excel::queue()`, `Excel::raw()`, `Excel::import()`, `Excel::toArray()`, `Excel::toCollection()`, export/import concerns, queued imports/exports, validation, CSV settings, styling, events, formulas, charts, drawings, multiple sheets, mapped cells, macros, config, cache, transactions, temporary files, or `Excel::fake()`.
- For broad docs, all-feature tasks, or missing-feature audits, use the skill's `references/package.md` feature matrix before answering.
- For large datasets, prefer `FromQuery` with queued exports or `WithChunkReading` and `WithBatchInserts` for imports.
- Test spreadsheet behavior with `Excel::fake()` when asserting dispatch/download/store/import intent, and inspect generated files only when cell contents, formatting, sheets, or writer behavior must be proven.

</laravel-boost-guidelines>

---
paths:
  - 'tests/**'
---

# Tests

## Commands
- **One Pest process per Sail stack at a time** — one per checkout/worktree, counting background jobs and subagents. Two runs against the same stack corrupt its shared `testing_test_*` databases (symptom: ~149 migration/table failures), and killing a run mid-flight leaves them half-migrated.
- A second worktree is a second stack: its own Compose project, pgsql container, and test databases, so running the suite in two worktrees (or in a worktree and the primary checkout) at once is fine. `scripts/reset-test-databases.sh` only touches the stack you run it from. Several full parallel suites at once compete for CPU and RAM.
- `--parallel` is **not** the cause of that corruption and stays on for every run. Do not "fix" it by dropping to `--processes=1` or to a serial run: that changes nothing about safety and slows the suite down. The fix is exclusivity within one stack.
- `--parallel` accepts exactly one path argument. For several files use `--filter`, or run them as separate strictly sequential commands.
  Full suite: `vendor/bin/sail artisan test --compact --parallel`. Focused: `vendor/bin/sail bin pest <one path> --parallel`.
- Recover a corrupted run with `scripts/reset-test-databases.sh` (drops `testing_test_*`; the next parallel run recreates them). A raw `DROP DATABASE … WITH (FORCE)` silently no-ops while a connection is still attached. Use `vendor/bin/sail down -v && vendor/bin/sail up -d` only if that fails.
- Inspect the database with `scripts/db.sh "<sql>"` — add `-d testing` for the test database.

## Never in a test
- A second Pest run against the same stack while one is in flight, or killing a run: see Commands. This includes delegating a review or verification task to a subagent while your own run is going — say explicitly in the subagent prompt that it must not run the suite.
- DDL on shared tables (`CREATE/DROP INDEX`, `CREATE EXTENSION`, `ALTER TABLE`). It locks tables the shared `TestCase` tears down per process and deadlocks under `--parallel`. Prove such DDL by running the command against a real database instead.
- The live Typesense server. CI has no Typesense, so the suite exercises that code path through the recorded-engine seam (`RecordingProductScoutEngine` in `tests/Feature/Search/ProductTypesenseIndexTest.php`). A temporary probe that needs a real server is allowed, but it must be marked `->group('typesense-live')`: `phpunit.xml` excludes that group, so CI never collects it. Run it locally with `vendor/bin/sail bin pest --group=typesense-live <path>`.

## Workflow: behavior, then coverage, then mutation
1. **Write behavior tests** for the feature: the happy path plus the edge cases that carry real business decisions (limits, state transitions, permissions, money, concurrency guards, validation of untrusted input). Run the focused scope.
2. **Check coverage on the full suite**: `vendor/bin/sail bin pest --parallel --coverage`. Single-file coverage is NOT valid because other files cover the same code. Look only at the files you touched and read their uncovered lines.
3. **Add tests only for uncovered lines that encode a real decision.** Skip uncovered lines that are defensive, trivial, or framework glue. Do not add tests to raise the number.
4. **Only then run mutation**, scoped to the one production class you changed: `vendor/bin/sail bin pest <that class's test file> --mutate --parallel`. Run it in the background unless it is the final step.
5. Fix survivors only when the mutant is a real decision (removed branch, flipped comparison, changed return, skipped side effect). Ignore equivalent mutants. Never exclude broad classes of code to hide survivors.

## Scope limits (keep tests few and meaningful)
- One `mutates()` target per test file: the single class under test. Never use `covers()` — it is not the convention here; `mutates()` marks the class for the mutation run and is what existing tests use. Never list many classes in one call; it makes mutation runs huge.
- One test per decision. Use datasets for variations of the same rule instead of copy-pasted tests.
- Test the code you wrote or changed in this task, not the code around it.

## Model tests (allowed, bounded)
- Every Eloquent model gets one `tests/Unit/Models/<Model>Test.php` with: a `toArray` test and one test per relationship (create real records, attach/associate, assert the related class and id). That is all. No scope, accessor, or cast tests here; those are tested through the behavior that uses them.
- Add or update these only when you add or change a model or relationship. Do not add model-style tests for any non-model class.
- Model test files are never `covers()`/`mutates()` targets and are excluded from mutation runs.

## Do NOT write tests for
- Seeders (`DemoSeeder` etc.), factories, migrations, config files, enums with no logic, plain DTOs/Resources with no logic, getters/setters, route registration.
- Laravel or package behavior (validation rules exist, middleware is applied, events can be dispatched).
- Query counts, N+1 detection, SQL text (`DB::listen`, `DB::getQueryLog`), log output (`Log::spy`, `Log::shouldReceive`), call order, private methods.
  If a test would still pass with the implementation deleted or replaced by anything plausible, delete it.

## Layers and doubles
- Assert behavior in the cheapest layer that proves it: pure logic in unit/integration tests; a feature test proves the HTTP seam, not every branch.
- Use the real implementation: real DB with factories, Laravel fakes (events, queues, mail, notifications, storage, HTTP, time), or an existing app fake.
- Mock only a contract whose real implementation leaves the process or is nondeterministic (outbound HTTP, provider clients, payment gateways).
- Never mock Eloquent models, the query builder, or the database.

## Architecture tests
Directory-wide or suite-wide conventions go in `tests/Architecture` as `arch()` rules (AST-based, no app boot), not in behavioral tests. Never assert which trait a class uses or read framework internals in a behavioral test.

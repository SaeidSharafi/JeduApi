---
paths:
  - 'tests/**'
---

# Tests

## Commands
- Always pass `--parallel` to every Pest command, including single-file runs.
  Full suite: `vendor/bin/sail artisan test --compact --parallel`. Focused: `vendor/bin/sail bin pest <scope> --parallel`.
- Run exactly one Pest command at a time and never kill one mid-run. Interleaving or killing corrupts the `testing_test_*` databases (symptom: ~149 migration/table failures).
- Fix corruption by dropping the per-process `testing_test_*` test databases/tables (the next parallel run recreates them). Use `vendor/bin/sail down -v && vendor/bin/sail up -d` only if dropping them doesn't work.

## Workflow: behavior, then coverage, then mutation
1. **Write behavior tests** for the feature: the happy path plus the edge cases that carry real business decisions (limits, state transitions, permissions, money, concurrency guards, validation of untrusted input). Run the focused scope.
2. **Check coverage on the full suite**: `vendor/bin/sail bin pest --parallel --coverage`. Single-file coverage is NOT valid because other files cover the same code. Look only at the files you touched and read their uncovered lines.
3. **Add tests only for uncovered lines that encode a real decision.** Skip uncovered lines that are defensive, trivial, or framework glue. Do not add tests to raise the number.
4. **Only then run mutation**, scoped to the one production class you changed: `vendor/bin/sail bin pest <that class's test file> --mutate --parallel`. Run it in the background unless it is the final step.
5. Fix survivors only when the mutant is a real decision (removed branch, flipped comparison, changed return, skipped side effect). Ignore equivalent mutants. Never exclude broad classes of code to hide survivors.

## Scope limits (keep tests few and meaningful)
- One `covers()` target per test file: the single class under test. Never list many classes; it makes mutation runs huge.
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

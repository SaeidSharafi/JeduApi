---
paths:
  - 'tests/**'
---

# Tests

## Always run every Pest command with --parallel
Never run `sail artisan test` or `sail bin pest` without `--parallel`, including single-file scopes. Use `vendor/bin/sail artisan test --compact --parallel` for the full suite and `vendor/bin/sail bin pest <scope> --parallel` for focused runs. Keep piping through `tail` to limit output if desired.

Run exactly one Pest command at a time. Interleaving or sequentially stacking Pest invocations corrupts the per-process `testing_test_*` databases; wait for each command to finish before starting the next. Killing a Pest run mid-flight (including an aborted or timed-out `--mutate` run) corrupts them the same way. If the databases do get corrupted — typically `149 failed` migration/table errors right after a killed run — reset them with `vendor/bin/sail down -v` followed by `vendor/bin/sail up -d` before re-running.

## Mutation-test new behavior
Mutation testing does not require a special kind of test. Write ordinary Pest behavior tests with meaningful setup and assertions, then declare which production code the file tests with `covers()` or `mutates()`:

```php
use App\Actions\Admin\Bundle\CreateBundleOptionAction;

covers(CreateBundleOptionAction::class);

it('rejects allocations above a component base price', function (): void {
    // Arrange a real request scenario, perform it, and assert the validation response.
});
```

Use `covers()` when the target should also be included in code-coverage reports; use `mutates()` when the target is only being selected for mutation testing. Target production classes or methods, never the test class itself. Keep tests externally observable: a test must still pass after an implementation change that keeps the behaviour. Observable means observable at the boundary of the unit under test, not only at the HTTP boundary — a service's own public contract is fair game; private methods, call ordering and log lines are not.

For every newly written or materially changed feature test, run the normal focused test command first, then run Pest mutation testing for the same scope through Sail with parallelism:

`vendor/bin/sail bin pest <same-test-scope> --mutate --parallel`

Always pass `--parallel` for mutation runs, including single-file scopes. Pest reports mutations as tested, untested, or uncovered.

mutation runs takes a long time, so it's better to run them in background and check the results later. unless it is the last step and there is no other work to do, in which case it is better to run it in foreground and wait for the results.

The goal is **meaningful coverage, not a 100% score**. Strengthen the behavior tests until every mutation that reflects a real decision is killed — especially mutations that remove a branch, alter a comparison, change a returned value, or skip a side effect. Do not add tests whose only purpose is to move the number, and do not chase survivors that cannot change observable behavior.

A surviving mutant may be left when it is demonstrably equivalent or intentionally outside the contract. Do not hide survivors by excluding broad classes of code.

## Cheapest layer, and what a test may double

Assert each behavior in the cheapest layer that can prove it. Logic that needs no HTTP belongs in a unit or integration test; a feature test proves the seam between layers, not every branch, because each HTTP test pays a full request cycle and a mutation run pays it once per mutant.

Prefer the real implementation over a double:

- Use Laravel's fakes for facades (events, queues, mail, notifications, storage, HTTP, time, sleep).
- Use an application-provided fake where one exists.
- Mock only a contract whose real implementation leaves the process or is nondeterministic — outbound HTTP, provider clients, payment gateways.
- Use the real implementation for everything else, including the database.

Never mock Eloquent models, the query builder, or the test database. Create real records with factories and assert the result; a test that mocks them asserts the mock, and the mutations it was meant to catch survive.

## Architecture tests declare conventions; behavioral tests prove behavior

Arch tests live in `tests/Architecture`, registered as its own `Architecture` testsuite in `phpunit.xml`. They are AST-based and are not bound to `Tests\TestCase`, so they never boot the application or the database.

A convention that covers a directory or the whole suite belongs in an arch rule, not in a behavioral test. Asserting which trait a class uses, or reading a framework's internal migration state, asserts an implementation detail — and such a test usually passes under the wrong implementation too, so it proves nothing. State the rule with `arch()` and let it fail when a new file breaks it.

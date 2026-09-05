---
paths:
  - 'tests/**'
---

# Tests

## Always run the test suite with --parallel
Never run `sail artisan test` without `--parallel`. Use `vendor/bin/sail artisan test --compact --parallel` for the full suite and for multi-file runs. Keep piping through `tail` to limit output if desired, but the --parallel flag is mandatory — single-file runs may omit it when parallelism offers nothing.
Never run multiple Pest commands concurrently; use one combined `--parallel` command or run commands sequentially.

## Mutation-test new behavior
Mutation testing does not require a special kind of test. Write ordinary Pest behavior tests with meaningful setup and assertions, then declare which production code the file tests with `covers()` or `mutates()`:

```php
use App\Actions\Admin\Bundle\CreateBundleOptionAction;

covers(CreateBundleOptionAction::class);

it('rejects allocations above a component base price', function (): void {
    // Arrange a real request scenario, perform it, and assert the validation response.
});
```

Use `covers()` when the target should also be included in code-coverage reports; use `mutates()` when the target is only being selected for mutation testing. Target production classes or methods, never the test class itself. Keep tests externally observable: mutation testing strengthens assertions and branch scenarios; it does not justify testing private methods or implementation details.

For every newly written or materially changed feature test, run the normal focused test command first, then run Pest mutation testing for the same scope through Sail with parallelism:

`vendor/bin/sail bin pest <same-test-scope> --mutate --parallel --min=100`

If the scope is a single test file and parallelism offers no benefit, `--parallel` may be omitted. Pest reports mutations as tested, untested, or uncovered. Strengthen the behavior tests until every meaningful mutation is killed—especially mutations that remove a branch, alter a comparison, change a returned value, or skip a side effect. A surviving mutant may be left only when it is demonstrably equivalent or intentionally outside the contract, and the reason must be recorded beside the test or in the ticket. Do not hide surviving mutants by excluding broad classes or lowering the minimum score.

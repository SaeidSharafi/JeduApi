---
paths:
  - 'tests/**'
---

# Tests

## Always run the test suite with --parallel
Never run `sail artisan test` without `--parallel`. Use `vendor/bin/sail artisan test --compact --parallel` for the full suite and for multi-file runs. Keep piping through `tail` to limit output if desired, but the --parallel flag is mandatory — single-file runs may omit it when parallelism offers nothing.

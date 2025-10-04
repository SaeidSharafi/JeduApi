# Typesense Test Helper - Dynamic Skip Strategy

## Overview

The `TypesenseTestHelper` class provides a dynamic way to handle tests that require Typesense to be running. Instead of hardcoded `->skip()` calls, it performs a real health check to determine if Typesense is available.

## Benefits

1. **Automatic Test Execution**: If Typesense is running (e.g., via Sail), tests will automatically run and provide real coverage
2. **Graceful Degradation**: If Typesense is unavailable, tests are skipped with a clear message
3. **Better CI/CD Integration**: Tests can run in environments with Typesense configured without code changes
4. **Improved Coverage**: Tests that were always skipped can now run when the service is available

## Usage

### In Test Files

```php
use Tests\Support\TypesenseTestHelper;

it('performs search with Typesense', function () {
    if (TypesenseTestHelper::skipIfTypesenseUnavailable()) {
        return;
    }
    
    // Test code that requires Typesense
    $results = $service->search($searchData);
    expect($results)->toBeInstanceOf(LengthAwarePaginator::class);
});
```

### Methods

#### `isTypesenseAvailable(): bool`
Checks if Typesense is available by:
1. Verifying `scout.driver` is set to `'typesense'`
2. Checking required configuration (host, port, API key)
3. Performing a health check HTTP request to `/health` endpoint with 2-second timeout
4. Validating response contains `{"ok": true}`

#### `skipIfTypesenseUnavailable(): bool`
Convenience method that:
- Calls `isTypesenseAvailable()`
- If unavailable, marks the test as skipped with message: "Typesense is not available"
- Returns `true` if test should be skipped, `false` otherwise

## Health Check Details

The health check uses:
- **Endpoint**: `http://{host}:{port}/health`
- **Timeout**: 2 seconds
- **Headers**: `X-TYPESENSE-API-KEY` header with configured API key
- **Expected Response**: `{"ok": true}` with HTTP 2xx status

## Test Coverage Impact

With this helper, the following lines are now testable when Typesense is running:

### GlobalSearchService
- Lines 43-48: Typesense availability check and try-catch
- Lines 69-187: Full `searchWithTypesense()` method including:
  - Cache key generation
  - Product and blog filter building
  - Multi-search request construction
  - Result hydration
  - Search analytics logging

### ProductQueryService
- Lines 174-178: Typesense try-catch and fallback logic

### SearchController
- Lines 47-58: Response transformation for Product and BlogPost results

## Running Tests

### With Typesense (Sail environment)
```bash
./vendor/bin/sail artisan test --filter=Search
```
Result: All tests run, minimal skips

### Without Typesense (CI without Typesense)
```bash
./vendor/bin/sail artisan test --filter=Search
```
Result: Typesense-dependent tests are skipped, others run

## Example Output

### With Typesense Available:
```
Tests:  54 passed (142 assertions)
Duration: 10.82s
```

### Without Typesense:
```
Tests:  7 skipped, 47 passed (130 assertions)
Duration: 8.50s
```

The skipped tests clearly indicate: "Typesense is not available"

## Configuration

The helper respects your existing Scout configuration:
- `config/scout.php` - Driver setting
- `config/scout.typesense.client-settings` - Connection details

No additional configuration needed!

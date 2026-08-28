---
paths:
  - 'app/Data/**'
---

# Laravel Data

## Avoid magical creation recursion in `from*()` and `collect*()` methods

Spatie Laravel Data treats public static methods whose names begin with `from` or `collect` as magical creation methods. If one of those methods calls `self::from()` or `self::collect()`, Spatie can dispatch back to the same custom method and recurse until a native stack overflow or silent segmentation fault.

When a custom `from*()` or `collect*()` method must delegate to the normal Data pipeline, disable magical creation explicitly:

```php
return self::factory()->withoutMagicalCreation()->from($value);
```

Use the equivalent `withoutMagicalCreation()` factory path when delegating collection creation. Apply this rule to every Data class, not only to classes that already exhibit the failure.

---
paths:
  - app/Data/Shop/Customer/CustomerData.php
---

# Customer

## CustomerData::fromUser() is the only sanctioned constructor path
CustomerData identity flags (is_teacher) come from User computed attributes backed by relations. Construct via `CustomerData::fromUser($user)` — it does `loadMissing('teacherData')` then `from()`. The User `is_teacher` Attribute must stay dumb: `fn () => $this->teacherData !== null`. No `relationLoaded()` checks, no `exists()` queries inside accessors. N+1 is owned globally by `Model::preventLazyLoading(!app()->isProduction())` in AppServiceProvider plus eager-loading (`with('teacherData')`) at collection query sites.

## NEVER call self::from() inside a public static "from*" method on a Data class
spatie/laravel-data treats any public static method whose name starts with `from` as a magical custom creation method (`DataMethodFactory::resolveCustomCreationMethodType`). When the payload type matches the method's parameters, `DataFromSomethingResolver::createFromCustomCreationMethod` calls that method INSTEAD of the normal pipeline. A `from*` method that calls `self::from($model)` then recurses into itself forever → native stack overflow → **silent segfault (signal 11), no exception, try/catch cannot catch it**. Observed with `CustomerData::fromUser()` + `User` payload on PHP 8.4 / laravel-data 4.23. Fix: call `self::factory()->withoutMagicalCreation()->from($user)` inside the method (disables magical dispatch, runs the normal pipeline). Same trap applies to any `collect*`-prefixed static method (Collection type).

## Laravel lazy-loading guard only fires on multi-model hydration
`Builder::hydrate()` sets the `preventsLazyLoading` instance flag ONLY when `count($items) > 1`. Single-model fetches (`find()`, `first()`) never trigger `LazyLoadingViolationException` — the relation silently lazy-loads. Do not write tests asserting a violation for single-model access; use a 2+ row collection instead.

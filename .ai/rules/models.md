---
paths:
  - 'app/Models/**'
  - app/Models/User.php
---

# Models

## Widen MorphTo generic to union of morph targets for scope/property access
Larastan types morphTo() as MorphTo<Model, $this>, so scopes/properties on the related model are unresolvable. Fix: declare @return MorphTo<Course|Seminar|DigitalAsset, $this> on the relation method (all targets must declare the accessed scopes/props, e.g. via shared contract/trait) and add `@phpstan-ignore return.type` on the `return $this->morphTo();` line since Larastan flags the widened docblock as a mismatch. See larastan/larastan#1777 (closed not planned; custom-builder pattern).

## Identity flags surface through CustomerData, not controllers
Customer identity flags (first: `is_teacher` = teacherData()->exists(), admin-granted) are computed on the User model and mapped into Shop CustomerData DTO. CustomerData ships in profile AND both login responses (password + OTP), so flags reach the frontend at session start with zero extra requests. Never duplicate exists()/flag checks inline in controllers; teacher endpoints keep their own 403 aborts as enforcement, independent of the exposed flag.

## Lazy-loading prevention differs between single and multiple hydration

`Builder::hydrate()` sets the model's `preventsLazyLoading` flag only when hydrating more than one model. Single-model fetches such as `find()` and `first()` can therefore lazy-load relations without raising `LazyLoadingViolationException`. Tests for lazy-loading prevention must use a collection containing at least two models.

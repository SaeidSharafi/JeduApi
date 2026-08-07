---
paths:
  - 'app/Models/**'
---

# Models

## Widen MorphTo generic to union of morph targets for scope/property access
Larastan types morphTo() as MorphTo<Model, $this>, so scopes/properties on the related model are unresolvable. Fix: declare @return MorphTo<Course|Seminar|DigitalAsset, $this> on the relation method (all targets must declare the accessed scopes/props, e.g. via shared contract/trait) and add `@phpstan-ignore return.type` on the `return $this->morphTo();` line since Larastan flags the widened docblock as a mismatch. See larastan/larastan#1777 (closed not planned; custom-builder pattern).

# Best Practices Research Summary

**Date**: June 2, 2026 | **Scope**: Laravel 12, Spatie Data v4, Pest 4, JeduShop Architecture

## Document Location
- **Full Research**: `docs/RESEARCH_BEST_PRACTICES.md` (39KB)
- Contains: Code examples, patterns, best practices with evidence from official docs and JeduShop codebase

## Quick Reference by Topic

### 1️⃣ Spatie Data Package (v4)

#### Nested Data Objects
- Type-hint nested classes directly in constructor
- Validation cascades automatically
- Each nested class can have own `rules()` and `bodyParameters()`

#### Validation Rules  
- Use `rules()` static method (single source of truth)
- Use `ValidationContext` for runtime conditions
- Use `Rule::enum()` for enum validation
- Use `.*.` syntax for nested array validation

#### Scribe Documentation
- **CRITICAL**: Add `@codeCoverageIgnore` to `bodyParameters()`
- **CRITICAL**: Explicitly define EACH `.*.` sub-field (Scribe can't infer nested fields)
- Provide realistic examples for API docs
- Never use `@bodyParam` in controller if using Data class

#### Collections vs Single Resources
- Single: `response()->success($data)` with Data instance
- Collections: `Data::collect()` returns `DataCollection`
- Add pagination metadata to response, not in data structure
- Eager load relationships to prevent N+1

---

### 2️⃣ Job Dispatching Patterns

#### Conditional Dispatch
- `Job::dispatchIf(condition, ...args)` - only if true
- `Job::dispatchUnless(condition, ...args)` - only if false
- Use to avoid queuing unnecessary jobs

#### Passing Context
- Use `Context::add()` for visible metadata (logged)
- Use `Context::addHidden()` for sensitive data (not logged)
- Jobs automatically receive context - no explicit passing
- Use `dehydrating` callback to filter what serializes
- Use `hydrated` callback to restore app state

#### Error Handling & Retries
- Array backoff: `[60, 180, 600]` = exponential delays
- `failed()` callback after all retries exhausted
- Guard clauses for missing resources (not errors)
- Validate prerequisites before main work
- Rethrow exceptions to trigger retry

---

### 3️⃣ State Machine Patterns

#### Validating Transitions
- Use enum with static transition validation methods
- Define allowed transitions as matrix/map
- `canTransition(enrollment, targetStatus)` before executing
- `getPossibleTransitions(enrollment)` for UI options

#### Business Logic During Changes
- Use Actions to encapsulate transition logic
- Store old status before transition for audit
- Execute transition with before/after hooks
- Trigger events after successful transition
- Log status changes with reason and user

#### Preventing Invalid Changes
- Database constraints (enum + check)
- Model observers for runtime validation
- Custom exceptions for clear error messages
- Throw before saving to prevent invalid state

---

### 4️⃣ PEST Testing

#### API Testing with Auth
- Use `AuthTestTrait` for authentication
- `$this->authorized_user(permissions, 'guard')` - with permission
- `$this->unauthorized_user('guard')` - without permission  
- `$this->customer(user)` - customer user
- Use `Sanctum::actingAs()` under the hood

#### Testing Job Dispatches
- `Bus::fake()` in beforeEach to prevent execution
- `Bus::assertDispatched(JobClass::class)` - was dispatched
- `Bus::assertDispatched(JobClass::class, fn($job) => ...)` - with payload check
- `Bus::assertNotDispatched(JobClass::class)` - wasn't dispatched

#### Testing Policies
- Use `Gate::authorize()` in controllers
- Tests verify permission denied returns 403
- Tests verify permission allowed returns 200/201
- Test both with and without permissions

#### Datasets
- **Inline**: `.with([data1, data2, ...])` repeats test
- **Named**: `.with(['key1' => data1, 'key2' => data2])`
- **Shared**: `dataset('name', [...])` in `tests/Datasets/`
- **Bound**: `fn() => Model::factory()->create()` - created per test
- **Multiple**: Chain `.with()` calls for cartesian product

---

## Key Takeaways

✅ **Data Classes**: Single source of truth for API contracts (validation + docs)
✅ **Context**: Pass request context to jobs without explicit parameters
✅ **State Machines**: Centralized transition logic prevents invalid states
✅ **Testing**: AuthTestTrait + Bus::fake() + datasets = comprehensive coverage
✅ **Error Handling**: Exponential backoff + failed callbacks + guard clauses
✅ **Architecture**: Controllers → Actions → Services → Jobs

---

## Example Flow: Payment Workflow

```
1. POST /admin/payments (Controller)
   ├─ Validate: PaymentCreateData.rules()
   ├─ Authorize: Gate::authorize('create', Payment)
   ├─ Execute: CreatePaymentAction.execute()
   │  ├─ Create model
   │  ├─ Dispatch UpdatePricingJob::dispatchIf(dirty)
   │  └─ Trigger event
   └─ Response: response()->success(PaymentShowData::from($payment))

2. Test creates payment
   ├─ Setup: $this->authorized_user([PAYMENT_CREATE])
   ├─ Act: postJson(route('admin.payment.store'), data)
   ├─ Bus::fake() prevents actual job execution
   ├─ Assert: $response->status() === 201
   └─ Assert: Bus::assertDispatched(UpdatePricingJob::class)
```

---

## Related Files in JeduShop

- **Data patterns**: `app/Data/Admin/Payment/`
- **Job patterns**: `app/Jobs/Provisioning/`
- **Job tests**: `tests/Integration/Jobs/`
- **Enums**: `app/Enums/EnrollmentStatusEnum.php`
- **State service**: `app/Services/EnrollmentStateTransitionService.php` (if exists)
- **Auth trait**: `tests/Support/Traits/AuthTestTrait.php`

---

See `docs/RESEARCH_BEST_PRACTICES.md` for full details with code examples.

# Implementation Checklists

Quick reference for implementing features following best practices.

---

## ✅ Create API Endpoint (Data → Action → Controller)

### 1. Create Data DTO Classes

```
[ ] Request Data class (App\Data\Admin\[Resource]\[Action]Data.php)
    [ ] Constructor with typed properties
    [ ] rules() method with validation
    [ ] bodyParameters() method for Scribe docs
    [ ] @codeCoverageIgnore on bodyParameters()

[ ] Response Data class (App\Data\Admin\[Resource]\[Resource]ShowData.php)
    [ ] Constructor matching response structure
    [ ] Include all necessary fields
    [ ] Optional nested Data classes
```

**Example**:
```php
// Request: app/Data/Admin/Payment/PaymentCreateData.php
final class PaymentCreateData extends Data
{
    public function __construct(
        public string $method,
        public ?BankTransferPaymentData $data,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return ['method' => ['required', Rule::enum(PaymentMethodEnum::class)]];
    }

    public function bodyParameters(): array { /* ... */ }
}

// Response: app/Data/Admin/Payment/PaymentShowData.php
final class PaymentShowData extends Data
{
    public function __construct(
        public int $id,
        public string $status,
        public float $amount,
    ) {}
}
```

### 2. Create Action Class

```
[ ] Create App\Actions\Admin\[Resource]\[Action][Resource]Action.php
[ ] Constructor injection of dependencies
[ ] execute() method encapsulating logic
[ ] Return typed value
[ ] Handle exceptions with meaningful messages
```

**Example**:
```php
final class CreatePaymentAction
{
    public function __construct(
        private PaymentService $paymentService,
        private CacheService $cacheService,
    ) {}

    public function execute(PaymentCreateData $data): Payment
    {
        $payment = Payment::create($data->toArray());
        
        UpdatePricingJob::dispatch($payment->id);
        
        return $payment;
    }
}
```

### 3. Create Controller

```
[ ] Create App\Http\Controllers\Api\Admin\[Resource]\[Action][Resource]Controller.php
[ ] Add class docblock with @group and @authenticated
[ ] Type-hint Data DTO for request
[ ] Gate::authorize() for policy check
[ ] Inject Action class
[ ] Return response()->success(Data::from(...))
[ ] Add method docblock with @responseFile storage/responses/admin/[resource]/[action].json
```

**Example**:
```php
/**
 * @group Admin - Payments
 * @authenticated
 */
final class CreatePaymentController
{
    /**
     * Create a new payment.
     * @responseFile storage/responses/admin/payment/show.json
     */
    public function __invoke(
        PaymentCreateData $data,
        CreatePaymentAction $action,
    ): JsonResponse {
        Gate::authorize('create', Payment::class);
        
        $payment = $action->execute($data);
        
        return response()->success(
            PaymentShowData::from($payment),
            message: 'Payment created',
            statusCode: 201,
        );
    }
}
```

### 4. Create Response File

```
[ ] Create storage/responses/admin/payment/show.json
[ ] Valid JSON structure matching PaymentShowData
[ ] Include all nested objects
[ ] Use realistic example values
```

### 5. Register Route

```
[ ] Add route to routes/Api/V1/admin/[resource].php
[ ] Use named route: admin.[resource].[action]
[ ] Bind model if needed (e.g., {payment})
```

**Example**:
```php
Route::post('/payments', CreatePaymentController::class)->name('admin.payment.store');
Route::get('/payments/{payment}', GetPaymentController::class)->name('admin.payment.show');
```

### 6. Run Scribe Documentation

```bash
vendor/bin/sail artisan scribe:generate
# Validates Data class rules() + bodyParameters()
# Generates API documentation
```

---

## ✅ Implement Conditional Job Dispatch

### 1. Create/Update Action

```
[ ] Add condition before dispatch
[ ] Use dispatchIf() or dispatchUnless()
[ ] Pass required arguments to job
```

**Example**:
```php
final class UpdateProductAction
{
    public function execute(Product $product, array $data): Product
    {
        $product->update($data);
        
        // Dispatch if price changed
        UpdatePricingJob::dispatchIf(
            $product->isDirty('base_price'),
            [$product->id]
        );
        
        return $product;
    }
}
```

### 2. Create Job Class

```
[ ] Implement ShouldQueue
[ ] Use Dispatchable, InteractsWithQueue, Queueable, SerializesModels
[ ] Set $tries and $timeout
[ ] Create constructor with required parameters
[ ] Implement handle() method
[ ] Add guard clauses for missing resources
[ ] Implement backoff() method
[ ] Implement failed() callback
```

**Example**:
```php
final class UpdatePricingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public array $productIds) {}

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function handle(ProductPriceService $service): void
    {
        if (empty($this->productIds)) return;
        
        // Main logic...
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Pricing update failed', ['error' => $exception->getMessage()]);
        // Notify admin
    }
}
```

### 3. Test Job Dispatch

```
[ ] Test with Bus::fake()
[ ] Test dispatchIf with true condition
[ ] Test dispatchIf with false condition
[ ] Assert job was/wasn't dispatched
[ ] Assert job received correct payload
```

---

## ✅ Implement State Machine (Status Transitions)

### 1. Create Status Enum

```
[ ] Define enum cases with values
[ ] Add docstring explaining each state
[ ] Create static methods for grouping (e.g., occupyingStatuses())
```

**Example**:
```php
enum EnrollmentStatusEnum: string
{
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PENDING_PROVISIONING = 'pending_provisioning';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public static function occupyingStatuses(): array
    {
        return [self::ACTIVE, self::PENDING_PROVISIONING, self::SUSPENDED];
    }
}
```

### 2. Create Transition Service

```
[ ] Create App\Services\[Model]StateTransitionService.php
[ ] Implement canTransition(model, targetStatus): bool
[ ] Define allowed transitions matrix
[ ] Implement getPossibleTransitions(model): array
[ ] Implement transition(model, targetStatus, reason): Model
[ ] Add before/after transition hooks
[ ] Handle side effects (dispatch jobs, send notifications, etc)
```

**Example**:
```php
final class EnrollmentStateTransitionService
{
    public function canTransition(Enrollment $e, EnrollmentStatusEnum $target): bool
    {
        $allowed = [
            EnrollmentStatusEnum::AWAITING_PAYMENT => [
                EnrollmentStatusEnum::PENDING_PROVISIONING,
            ],
            // ...
        ];
        
        return in_array($target, $allowed[$e->status] ?? []);
    }

    public function transition(
        Enrollment $e,
        EnrollmentStatusEnum $target,
        ?string $reason = null,
    ): Enrollment {
        $this->beforeTransition($e, $target);
        
        $e->enrollment_status = $target;
        $e->status_updated_at = now();
        $e->save();
        
        $this->afterTransition($e, $target);
        
        return $e;
    }
}
```

### 3. Create Action Class

```
[ ] Create App\Actions\Admin\[Resource]\Update[Resource]StatusAction.php
[ ] Validate transition is allowed
[ ] Store old status for audit
[ ] Execute transition via service
[ ] Log change
[ ] Trigger event
[ ] Return updated model
```

### 4. Create Controller

```
[ ] Create PATCH/PUT endpoint
[ ] Type-hint Data DTO with status field
[ ] Gate::authorize() for permission
[ ] Call action
[ ] Return updated resource
```

### 5. Create Tests

```
[ ] Test valid transitions
[ ] Test invalid transitions (should throw)
[ ] Test side effects dispatched
[ ] Test audit logged
[ ] Use dataset for multiple transition scenarios
```

**Example**:
```php
it('validates transition rules', function (
    EnrollmentStatusEnum $from,
    EnrollmentStatusEnum $to,
    bool $shouldSucceed
) {
    $enrollment = Enrollment::factory()->create(['enrollment_status' => $from]);
    $service = app(EnrollmentStateTransitionService::class);
    
    expect($service->canTransition($enrollment, $to))->toBe($shouldSucceed);
})->with([
    [EnrollmentStatusEnum::AWAITING_PAYMENT, EnrollmentStatusEnum::ACTIVE, false],
    [EnrollmentStatusEnum::AWAITING_PAYMENT, EnrollmentStatusEnum::PENDING_PROVISIONING, true],
    // ...
]);
```

---

## ✅ Test Job Dispatches with PEST

### 1. Setup Test Class

```php
uses(AuthTestTrait::class);

beforeEach(function () {
    Bus::fake();  // Prevent actual execution
});
```

### 2. Test Job Dispatch

```
[ ] Test dispatched when condition met
[ ] Test not dispatched when condition not met
[ ] Test job payload is correct
[ ] Use dataset for multiple scenarios
```

**Example**:
```php
it('dispatches provisioning job when active', function () {
    $enrollment = Enrollment::factory()->create();
    $action = app(UpdateEnrollmentStatusAction::class);
    
    $action->execute($enrollment, EnrollmentStatusEnum::ACTIVE);
    
    Bus::assertDispatched(ProvisionEnrollmentJob::class, function ($job) use ($enrollment) {
        return $job->enrollmentId === $enrollment->id;
    });
});
```

### 3. Test with Datasets

```
[ ] Create inline dataset with multiple scenarios
[ ] Use .with([...]) on test
[ ] Test each combination
```

---

## ✅ Test API Endpoints with Auth

### 1. Setup Authentication

```
[ ] Use AuthTestTrait
[ ] In beforeEach: $this->authorized_user([PERMISSION])
[ ] Or: $this->unauthorized_user()
```

### 2. Test Authorization

```
[ ] Test returns 403 without permission
[ ] Test returns 200/201 with permission
[ ] Use dataset for multiple permissions
```

**Example**:
```php
it('requires PAYMENT_CREATE permission', function () {
    $this->authorized_user([PermissionEnum::PAYMENT_CREATE]);
    
    $response = postJson(route('admin.payment.store'), [...]);
    expect($response->status())->toBe(201);
});

it('denies without permission', function () {
    $this->unauthorized_user();
    
    $response = postJson(route('admin.payment.store'), [...]);
    expect($response->status())->toBe(403);
});
```

### 3. Test Validation

```
[ ] Test required fields
[ ] Test invalid enum values
[ ] Test nested object validation
[ ] Test optional fields
```

### 4. Test Response Structure

```
[ ] Assert response status
[ ] Assert response contains expected data
[ ] Assert database has record
[ ] Use Data::from() to validate structure
```

---

## ✅ Use Datasets in PEST

### Inline Dataset (Simple)
```php
it('validates status', function (string $status) {
    // test logic
})->with(['pending', 'completed', 'failed']);
```

### Named Dataset (Readable)
```php
it('handles payment methods', function (string $method, bool $valid) {
    // test logic
})->with([
    'credit_card' => ['credit_card', true],
    'invalid' => ['invalid_method', false],
]);
```

### Shared Dataset (Reusable)
```php
// tests/Datasets/PaymentMethods.php
dataset('payment_methods', [
    'credit_card' => ['credit_card', true],
    'bank_transfer' => ['bank_transfer', true],
]);

// In test:
it('validates method', function (string $method, bool $valid) {
    // test
})->with('payment_methods');
```

### Bound Dataset (Database Models)
```php
it('retrieves payment', function (Payment $payment) {
    $response = getJson(route('admin.payment.show', $payment));
    expect($response->json('data.id'))->toBe($payment->id);
})->with([
    fn () => Payment::factory()->create(['amount' => 50]),
    fn () => Payment::factory()->create(['amount' => 100]),
]);
```

---

## ✅ Context Passing to Jobs

### 1. Add Context in Middleware

```php
Context::add('trace_id', Str::uuid());
Context::add('user_locale', auth()->user()?->locale);
Context::addHidden('auth_token', auth()->token());
```

### 2. Register Dehydration/Hydration (AppServiceProvider)

```php
Context::dehydrating(function (Repository $context) {
    if ($context->has('user_locale')) {
        $context->addHidden('locale', Config::get('app.locale'));
    }
});

Context::hydrated(function (Repository $context) {
    if ($context->hasHidden('locale')) {
        Config::set('app.locale', $context->getHidden('locale'));
    }
});
```

### 3. Access in Job

```php
public function handle(): void
{
    $traceId = Context::get('trace_id');  // Available!
    Log::info('Processing', ['trace_id' => $traceId]);
}
```

---

## ✅ Create Nested Data with Scribe Docs

### 1. Create Nested Data Class

```php
final class BankTransferPaymentData extends Data
{
    public function __construct(
        public string $transaction_id,
        public string $sender_name,
    ) {}

    public static function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string'],
            'sender_name' => ['required', 'string'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'transaction_id' => ['description' => '...', 'example' => 'TX123'],
            'sender_name' => ['description' => '...', 'example' => 'Ali'],
        ];
    }
}
```

### 2. Use in Parent Data

```php
final class PaymentCreateData extends Data
{
    public function __construct(
        public string $method,
        public ?BankTransferPaymentData $data,
    ) {}

    public static function rules(): array
    {
        return [
            'method' => ['required'],
            'data' => ['nullable', 'array'],
            'data.transaction_id' => ['nullable', 'string'],
            'data.sender_name' => ['nullable', 'string'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'method' => [...],
            'data' => ['description' => '...', 'example' => [...]],
            'data.transaction_id' => [...],  // EXPLICIT
            'data.sender_name' => [...],     // EXPLICIT
        ];
    }
}
```

### 3. Add @codeCoverageIgnore

```php
/**
 * @codeCoverageIgnore
 */
public function bodyParameters(): array
{
    return [/* ... */];
}
```

---

## ✅ Pre-Commit Checklist

```bash
# Format code
vendor/bin/sail pint --dirty

# Run tests
vendor/bin/sail artisan test --compact --filter=FeatureName

# Check for N+1
# Look for: Model::all() without with() in production code

# Validate Data classes
# Check: rules() + bodyParameters() defined
# Check: @codeCoverageIgnore on bodyParameters()

# Verify jobs
# Check: backoff() + failed() defined
# Check: Guard clauses for missing resources
# Check: Tests with Bus::fake()

# Verify state transitions
# Check: canTransition() validates before execute
# Check: Audit logged
# Check: Tests use datasets for multiple scenarios
```

---

## ✅ Common Mistakes to Avoid

❌ **Data Classes**
- Don't use Form Requests (use Data DTOs)
- Don't skip bodyParameters() for nested fields
- Don't use @bodyParam in controller (use Data class rules())

❌ **Jobs**
- Don't forget backoff() method
- Don't forget failed() callback
- Don't rethrow in failed() (causes infinite loop)
- Don't forget guard clauses for missing resources

❌ **State Machines**
- Don't allow direct model->status = value (use Action)
- Don't skip validation before transition
- Don't forget to log status changes

❌ **Testing**
- Don't forget Bus::fake() (jobs actually run!)
- Don't use actingAs() (use AuthTestTrait)
- Don't skip authorization tests (permissions matter)


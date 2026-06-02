# Laravel 12 Best Practices Research
## Spatie Data, Job Dispatching, State Machines & PEST Testing

**Research Date**: June 2, 2026 | **Laravel Version**: 12.x | **Pest**: 4.x | **Spatie Data**: v4

---

## 1. LARAVEL SPATIE DATA PACKAGE (v4)

### 1.1 Nested Data Objects

**Pattern**: Use nested Data classes for complex object hierarchies. Spatie automatically validates and transforms nested structures.

```php
// Nested Data class for bank transfer details
final class BankTransferPaymentData extends Data
{
    public function __construct(
        public string $transaction_id,
        public string $transaction_date,
        public string $sender_name,
        public ?string $notes = null,
    ) {}
}

// Parent Data containing nested object
final class PaymentCreateData extends Data
{
    public function __construct(
        public string $method,
        public ?BankTransferPaymentData $data,
        public ?string $admin_notes,
    ) {}
}

// In controller, both parent and nested are validated automatically
public function store(PaymentCreateData $data, CreatePaymentAction $action)
{
    // $data->data is automatically instantiated as BankTransferPaymentData
    $payment = $action->execute($data);
    return response()->success($payment);
}
```

**Key Points**:
- Nested objects are **type-hinted** directly in constructor
- Validation cascades through the hierarchy
- Each nested class can have its own `rules()` and `bodyParameters()`
- Collections of objects use `DataCollection` for typed arrays

### 1.2 Validation Rules in Data Classes

**Pattern**: Use `rules()` static method for centralized, testable validation logic.

```php
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PaymentCreateData extends Data
{
    // ... constructor ...

    public static function rules(?ValidationContext $context = null): array
    {
        $now = verta()->format('Y-m-d');  // Current date for business logic

        return [
            // Required fields
            'method' => ['required', Rule::enum(PaymentMethodEnum::class)],
            
            // Optional nested object
            'data' => ['nullable', 'array'],
            
            // Nested field validation (use .* for nested objects)
            'data.transaction_id'   => ['nullable', 'string', 'max:255'],
            'data.transaction_date' => [
                'nullable', 
                'jdate:Y-m-d',
                'jdate_before_equal:'.$now.',Y-m-d'  // Custom rule with context
            ],
            'data.sender_name' => ['nullable', 'string', 'max:255'],
            
            // Simple optional field
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

**Best Practices**:
- Use `ValidationContext` parameter for runtime rules (conditions based on route, user, etc)
- Use `Rule::enum()` for enum validation
- Use `.*.` syntax for nested array field validation
- Place business logic constraints in rules (dates, conditional fields, etc)
- Use custom validation rules (like `jdate:`) for domain-specific validation

### 1.3 Scribe Documentation Integration with bodyParameters()

**Pattern**: Define parameter metadata for automatic API documentation generation.

```php
final class PaymentCreateData extends Data
{
    // ... constructor and rules() ...

    /**
     * @codeCoverageIgnore  <- CRITICAL: Exclude from coverage
     * 
     * Scribe reads from this method to generate API docs
     */
    public function bodyParameters(): array
    {
        return [
            // Parent-level parameters
            'method' => [
                'description' => 'Payment method used for the transaction',
                'example'     => 'credit_card',
                // OPTIONAL: 'required' => true,
            ],
            
            // Nested object (parent level)
            'data' => [
                'description' => 'Additional data related to payment',
                'example'     => [
                    'transaction_id' => '123456789',
                    'sender_name'    => 'Ali Rezaei',
                ],
            ],
            
            // CRITICAL: Nested field documentation (each sub-field explicitly)
            'data.transaction_id' => [
                'description' => 'Unique identifier for bank transaction',
                'example'     => 'TX123456789',
            ],
            'data.transaction_date' => [
                'description' => 'Date when transaction occurred (Jalali)',
                'example'     => '1402-01-15',
            ],
            'data.sender_name' => [
                'description' => 'Name of person who sent transfer',
                'example'     => 'Ali Rezaei',
            ],
            'data.notes' => [
                'description' => 'Additional notes about transfer',
                'example'     => 'Payment for order #1234',
            ],
            
            // Simple optional field
            'admin_notes' => [
                'description' => 'Optional notes for admin purposes',
                'example'     => 'Payment received, awaiting confirmation',
            ],
        ];
    }
}
```

**Critical Rules for Scribe**:
1. **@codeCoverageIgnore**: Always add to bodyParameters() method
2. **Nested field documentation**: MUST explicitly define each `.*.` sub-field
3. **Example values**: Provide realistic examples (used in generated docs)
4. **Description**: Clear, action-oriented descriptions for API users
5. **NEVER use @bodyParam in controller** if using Data class - single source of truth

### 1.4 Collection Responses vs Single Resource Responses

**Pattern**: Use appropriate response structures based on data being returned.

```php
// ====== SINGLE RESOURCE RESPONSE ======
final class GetPaymentController
{
    public function __invoke(Payment $payment): JsonResponse
    {
        // GET /payments/{id}
        $paymentData = PaymentShowData::from($payment);
        return response()->success($paymentData);  // Single object
    }
}

// ====== COLLECTION RESPONSE ======
final class ListPaymentsController
{
    public function __invoke(Request $request): JsonResponse
    {
        // GET /payments?page=1&per_page=15
        $payments = Payment::query()
            ->paginate($request->query('per_page', 15));
        
        // Option 1: Use collection from models
        $paymentCollection = PaymentShowData::collect($payments->items());
        
        // Option 2: Custom pagination response with metadata
        return response()->success(
            data: $paymentCollection,
            message: 'Payments retrieved',
            // Add pagination metadata
            metadata: [
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'total' => $payments->total(),
                    'per_page' => $payments->perPage(),
                ],
            ]
        );
    }
}

// ====== DEFINING COLLECTION DATA CLASS ======
final class PaymentShowData extends Data
{
    public function __construct(
        public int $id,
        public string $status,
        public float $amount,
        public string $created_at,
    ) {}

    // OPTIONAL: Custom transformation for collection
    public static function collect(Collection $collection, bool $mapProperty = true): DataCollection
    {
        return new DataCollection(
            self::class,
            $collection->map(fn ($item) => self::from($item)),
        );
    }
}
```

**Best Practices**:
- **Single resource**: Return `response()->success($data)` with Data instance
- **Collections**: Use `Data::collect()` which returns `DataCollection`
- **Pagination metadata**: Add to response metadata, NOT in data structure
- **Lazy loading prevention**: Eager load relationships in collection queries
- **Response wrapping**: JeduShop uses custom response macros (not standard Laravel JSON:Resource)

---

## 2. JOB DISPATCHING PATTERNS

### 2.1 Conditional Job Dispatch Based on Model Attributes

**Pattern**: Use `dispatchIf()` and `dispatchUnless()` for conditional execution.

```php
use App\Jobs\UpdateProductPricingJob;
use Illuminate\Bus\Queueable;

// ====== DISPATCH CONDITIONALLY IN CONTROLLER ======
final class UpdateProductAction
{
    public function execute(Product $product, array $data): Product
    {
        $product->update($data);
        
        // Option 1: Dispatch if condition true
        UpdateProductPricingJob::dispatchIf(
            $product->isDirty('base_price'),  // Condition
            $product->id                       // Job argument
        );
        
        // Option 2: Dispatch unless condition true
        UpdateProductPricingJob::dispatchUnless(
            $product->is_draft,  // If true, DON'T dispatch
            [$product->id]       // If false, dispatch with IDs
        );
        
        return $product;
    }
}

// ====== JOB DEFINITION ======
final class UpdateProductPricingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $productIds,
    ) {}

    public function handle(ProductPriceService $priceService): void
    {
        if (empty($this->productIds)) {
            return;  // Guard against empty payload
        }

        $products = Product::whereIn('id', $this->productIds)
            ->with([
                'productDeliveryOptions' => fn ($q) => $q->where('status', 'published'),
                'productDeliveryOptions.productDeliveryOptionDiscountPrice',
            ])
            ->get();

        if ($products->isEmpty()) {
            Log::warning('No products found', ['ids' => $this->productIds]);
            return;
        }

        $priceService->updatePriceIndexForProducts($products);
        $this->clearCachesForProducts();
    }

    private function clearCachesForProducts(): void
    {
        $invalidationService = app(CacheInvalidationService::class);
        $invalidationService->invalidateForModel(Product::class);
    }
}
```

**Key Points**:
- `dispatchIf(condition, ...args)` - dispatch only if condition is true
- `dispatchUnless(condition, ...args)` - dispatch only if condition is false
- Both methods accept multiple arguments passed to job constructor
- Use for performance: avoid queuing unnecessary jobs

### 2.2 Passing Context to Queued Jobs

**Pattern**: Use Laravel Context facade to preserve request context across queue boundaries.

```php
// ====== IN MIDDLEWARE (Capture Context) ======
use Illuminate\Support\Facades\Context;

class AddContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Add visible context
        Context::add('url', $request->url());
        Context::add('trace_id', Str::uuid()->toString());
        Context::add('user_locale', auth()->user()?->locale ?? 'en');

        // Add hidden context (not logged)
        Context::addHidden('auth_token', auth()->guard('sanctum')->token());

        return $next($request);
    }
}

// ====== DISPATCHING JOB (Context Captured Automatically) ======
ProvisionEnrollmentJob::dispatch($enrollment);
// Context is automatically "dehydrated" and attached to job

// ====== IN DEHYDRATION CALLBACK (AppServiceProvider) ======
use Illuminate\Log\Context\Repository;
use Illuminate\Support\Facades\Context;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Customize what gets serialized with job
        Context::dehydrating(function (Repository $context) {
            // Only include specific context keys
            if ($context->has('user_locale')) {
                $context->addHidden('app_locale', Config::get('app.locale'));
            }
        });

        // Customize how context is restored when job runs
        Context::hydrated(function (Repository $context) {
            if ($context->hasHidden('app_locale')) {
                Config::set('app.locale', $context->getHidden('app_locale'));
            }
        });
    }
}

// ====== ACCESSING CONTEXT IN JOB ======
final class ProvisionEnrollmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Context is automatically hydrated before handle() runs
        $traceId = Context::get('trace_id');  // Available!
        $locale = Context::get('user_locale'); // Available!

        Log::info('Provisioning enrollment', [
            'trace_id' => $traceId,
            'locale' => $locale,
        ]);
        // Metadata automatically includes context
    }
}
```

**Best Practices**:
- Use `Context::add()` for visible metadata (logged with every log entry)
- Use `Context::addHidden()` for sensitive data (not logged, but passed to jobs)
- Jobs automatically receive context - no explicit passing needed
- Use `dehydrating` callback to filter/modify what gets serialized
- Use `hydrated` callback to restore app state (like locale, timezone)

### 2.3 Error Handling and Retry Strategies

**Pattern**: Use backoff arrays, failed callbacks, and job middleware for robust error handling.

```php
final class ProvisionBbbEnrollmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // ====== RETRY CONFIGURATION ======
    public int $tries = 3;  // Max attempts
    public int $timeout = 120;  // Seconds before timeout

    public function __construct(
        public int $enrollmentId,
    ) {}

    // ====== EXPONENTIAL BACKOFF ======
    public function backoff(): array
    {
        // Delays between retries in seconds: 60s, 180s, 600s
        return [60, 180, 600];
    }

    public function handle(
        BbbService $service,
        SettingsService $settingsService,
    ): void {
        $enrollment = Enrollment::find($this->enrollmentId);

        if (! $enrollment) {
            // Early return for deleted enrollments (not an error)
            return;
        }

        try {
            // Validate config exists
            $config = $settingsService->get('integrations.bbb');
            if (! $config['enabled'] || ! $config['base_url'] || ! $config['secret']) {
                throw new RuntimeException('BBB configuration invalid');
            }

            // Main provisioning logic
            $meeting = $service->createMeeting(
                $enrollment->productDeliveryOption->details['meeting_id'],
                $enrollment->productDeliveryOption->name,
                $config['attendee_password'],
                $config['moderator_password'],
            );

            $enrollment->provisioning_data = [
                'providers' => [
                    'bbb' => [
                        'status' => 'success',
                        'data' => $meeting->toArray(),
                    ],
                ],
            ];
            $enrollment->enrollment_status = EnrollmentStatusEnum::ACTIVE;
            $enrollment->save();

        } catch (Throwable $e) {
            // Let job fail, retry will be attempted
            Log::error('BBB provisioning failed', [
                'enrollment_id' => $this->enrollmentId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;  // Rethrow to trigger retry
        }
    }

    // ====== FAILED CALLBACK (Called after all retries exhausted) ======
    public function failed(Throwable $exception): void
    {
        $enrollment = Enrollment::find($this->enrollmentId);

        if (! $enrollment) {
            return;  // Already deleted
        }

        // Mark enrollment as failed
        $enrollment->enrollment_status = EnrollmentStatusEnum::PROVISIONING_FAILED;
        $enrollment->provisioning_data = array_merge(
            $enrollment->provisioning_data ?? [],
            [
                'providers' => [
                    'bbb' => [
                        'status' => 'failed',
                        'last_error' => $exception->getMessage(),
                        'failed_at' => now()->toIso8601String(),
                    ],
                ],
            ]
        );
        $enrollment->save();

        // Send admin notification
        Notification::route('mail', config('admin.email'))
            ->notify(new EnrollmentProvisioningFailedNotification($enrollment));
    }

    // ====== JOB MIDDLEWARE (For cross-cutting concerns) ======
    public function middleware(): array
    {
        return [
            // Prevent parallel executions of same enrollment
            new WithoutOverlapping("enrollment:{$this->enrollmentId}"),
            // Rate limiting
            new RateLimited(),
        ];
    }
}
```

**Backoff Strategy**:
- **Array backoff**: `[60, 180, 600]` = exponential delays (1m, 3m, 10m)
- **Callable backoff**: `fn() => [60 * $this->attempts()]` = dynamic based on attempt count
- **Max retries**: Configure in `$tries` property or via `--tries` flag

**Error Handling**:
- **Guard clauses**: Early return for missing resources (not errors)
- **Validation before work**: Check config/prerequisites upfront
- **Logging context**: Include attempt number, IDs, error details
- **Failed callback**: Final cleanup, marking state, notifications
- **Rethrow for retries**: `throw $e` to trigger backoff retry

---

## 3. STATE MACHINE PATTERNS FOR STATUS TRANSITIONS

### 3.1 Validating Allowed Transitions

**Pattern**: Use enums with transition validation methods.

```php
// ====== ENROLLMENT STATUS ENUM ======
enum EnrollmentStatusEnum: string
{
    use AdvanceEnum;
    
    // Order: Payment flow
    case AWAITING_PAYMENT = 'awaiting_payment';        // Initial state
    case PENDING_PROVISIONING = 'pending_provisioning'; // After payment
    case ACTIVE = 'active';                            // Ready to use
    case SUSPENDED = 'suspended';                      // Temp access block
    case EXPIRED = 'expired';                          // Access period ended
    case CANCELLED = 'cancelled';                      // Permanently revoked
    case PROVISIONING_FAILED = 'provisioning_failed';  // Setup failed

    /**
     * States that occupy a "seat" or count toward usage limits
     */
    public static function occupyingStatuses(): array
    {
        return [
            self::ACTIVE,
            self::PENDING_PROVISIONING,
            self::SUSPENDED,
        ];
    }

    /**
     * States that do NOT count toward usage limits
     */
    public static function nonOccupyingStatuses(): array
    {
        return [
            self::AWAITING_PAYMENT,
            self::CANCELLED,
            self::EXPIRED,
            self::PROVISIONING_FAILED,
        ];
    }
}

// ====== STATE TRANSITION VALIDATOR (Service) ======
final class EnrollmentStateTransitionService
{
    /**
     * Validate if transition is allowed
     */
    public function canTransition(
        Enrollment $enrollment,
        EnrollmentStatusEnum $targetStatus,
    ): bool {
        $currentStatus = $enrollment->enrollment_status;

        // Define allowed transitions
        $allowedTransitions = [
            EnrollmentStatusEnum::AWAITING_PAYMENT => [
                EnrollmentStatusEnum::PENDING_PROVISIONING,
                EnrollmentStatusEnum::CANCELLED,  // Refund before payment
            ],
            EnrollmentStatusEnum::PENDING_PROVISIONING => [
                EnrollmentStatusEnum::ACTIVE,
                EnrollmentStatusEnum::PROVISIONING_FAILED,
            ],
            EnrollmentStatusEnum::ACTIVE => [
                EnrollmentStatusEnum::SUSPENDED,
                EnrollmentStatusEnum::EXPIRED,
                EnrollmentStatusEnum::CANCELLED,  // Refund
            ],
            EnrollmentStatusEnum::SUSPENDED => [
                EnrollmentStatusEnum::ACTIVE,      // Restore access
                EnrollmentStatusEnum::CANCELLED,   // Permanent revoke
            ],
            EnrollmentStatusEnum::PROVISIONING_FAILED => [
                EnrollmentStatusEnum::PENDING_PROVISIONING,  // Retry
                EnrollmentStatusEnum::CANCELLED,
            ],
        ];

        $allowed = $allowedTransitions[$currentStatus] ?? [];
        return in_array($targetStatus, $allowed, strict: true);
    }

    /**
     * Get possible transitions from current state
     */
    public function getPossibleTransitions(Enrollment $enrollment): array
    {
        $allowedTransitions = [
            EnrollmentStatusEnum::AWAITING_PAYMENT => [
                EnrollmentStatusEnum::PENDING_PROVISIONING,
                EnrollmentStatusEnum::CANCELLED,
            ],
            // ... other transitions ...
        ];

        return $allowedTransitions[$enrollment->enrollment_status] ?? [];
    }

    /**
     * Execute transition with business logic
     */
    public function transition(
        Enrollment $enrollment,
        EnrollmentStatusEnum $targetStatus,
        ?string $reason = null,
    ): Enrollment {
        if (! $this->canTransition($enrollment, $targetStatus)) {
            throw new InvalidStateTransitionException(
                "Cannot transition from {$enrollment->enrollment_status->value} to {$targetStatus->value}"
            );
        }

        // Before-transition hook
        $this->beforeTransition($enrollment, $targetStatus);

        // Execute transition
        $enrollment->enrollment_status = $targetStatus;
        $enrollment->status_updated_at = now();
        $enrollment->status_change_reason = $reason;
        $enrollment->save();

        // After-transition hook
        $this->afterTransition($enrollment, $targetStatus);

        return $enrollment->refresh();
    }

    private function beforeTransition(Enrollment $enrollment, EnrollmentStatusEnum $targetStatus): void
    {
        // Pre-transition validation
        if ($targetStatus === EnrollmentStatusEnum::EXPIRED) {
            // Validate access period has actually ended
            if ($enrollment->access_ends_at->isFuture()) {
                throw new InvalidStateTransitionException('Access period has not ended yet');
            }
        }
    }

    private function afterTransition(Enrollment $enrollment, EnrollmentStatusEnum $targetStatus): void
    {
        // Side effects after transition
        match($targetStatus) {
            EnrollmentStatusEnum::ACTIVE => $this->grantAccess($enrollment),
            EnrollmentStatusEnum::SUSPENDED => $this->revokeAccess($enrollment),
            EnrollmentStatusEnum::CANCELLED => $this->handleRefund($enrollment),
            default => null,
        };
    }

    private function grantAccess(Enrollment $enrollment): void
    {
        // Provision access to learning platforms
        ProvisionEnrollmentJob::dispatch($enrollment);
    }

    private function revokeAccess(Enrollment $enrollment): void
    {
        // Remove access from platforms
        RevokeEnrollmentJob::dispatch($enrollment);
    }

    private function handleRefund(Enrollment $enrollment): void
    {
        // Process refund
        ProcessRefundJob::dispatch($enrollment);
    }
}
```

### 3.2 Handling Business Logic During State Changes

**Pattern**: Use Actions to encapsulate transition logic with side effects.

```php
// ====== ACTION: Update Enrollment Status ======
final class UpdateEnrollmentStatusAction
{
    public function __construct(
        private EnrollmentStateTransitionService $stateService,
        private EnrollmentAuditService $auditService,
    ) {}

    public function execute(
        Enrollment $enrollment,
        EnrollmentStatusEnum $newStatus,
        ?string $reason = null,
    ): Enrollment {
        // 1. Validate transition is allowed
        if (! $this->stateService->canTransition($enrollment, $newStatus)) {
            throw new InvalidStateTransitionException(
                "Cannot change enrollment from {$enrollment->enrollment_status->value} to {$newStatus->value}"
            );
        }

        // 2. Store old status for audit
        $oldStatus = $enrollment->enrollment_status;

        // 3. Execute state transition
        $enrollment = $this->stateService->transition($enrollment, $newStatus, $reason);

        // 4. Audit the change
        $this->auditService->logStatusChange(
            enrollment: $enrollment,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            reason: $reason,
            changedBy: auth()->user(),
        );

        // 5. Trigger side effects (event)
        EnrollmentStatusChanged::dispatch($enrollment, $oldStatus, $newStatus);

        return $enrollment;
    }
}

// ====== CONTROLLER: Use action for status updates ======
final class UpdateEnrollmentStatusController
{
    public function __invoke(
        Enrollment $enrollment,
        UpdateEnrollmentStatusRequest $request,
        UpdateEnrollmentStatusAction $action,
    ): JsonResponse {
        Gate::authorize('update', $enrollment);

        $updatedEnrollment = $action->execute(
            $enrollment,
            EnrollmentStatusEnum::from($request->status),
            $request->reason,
        );

        return response()->success(
            EnrollmentShowData::from($updatedEnrollment),
            message: "Enrollment status updated to {$updatedEnrollment->enrollment_status->value}"
        );
    }
}
```

### 3.3 Preventing Invalid State Changes

**Pattern**: Use database constraints + application validation.

```php
// ====== CUSTOM EXCEPTION ======
class InvalidStateTransitionException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}

// ====== DATABASE CONSTRAINT (Migration) ======
Schema::create('enrollments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
    $table->enum('enrollment_status', [
        'awaiting_payment',
        'pending_provisioning',
        'active',
        'suspended',
        'expired',
        'cancelled',
        'provisioning_failed',
    ])->default('awaiting_payment');
    $table->timestamp('status_updated_at')->useCurrent();
    $table->text('status_change_reason')->nullable();
    $table->json('provisioning_data')->nullable();
    
    // Prevent invalid status + policy violations
    $table->check("(enrollment_status IN ('active', 'pending_provisioning', 'suspended') AND order_item_id IS NOT NULL)");
});

// ====== OBSERVER: Additional runtime validation ======
class EnrollmentObserver
{
    public function updating(Enrollment $enrollment): void
    {
        // Prevent status downgrades (except specific allowed transitions)
        if ($enrollment->isDirty('enrollment_status')) {
            $oldStatus = $enrollment->getOriginal('enrollment_status');
            $newStatus = $enrollment->enrollment_status;

            if (! $this->isAllowedTransition($oldStatus, $newStatus)) {
                throw new InvalidStateTransitionException(
                    "Cannot transition from {$oldStatus} to {$newStatus}"
                );
            }
        }
    }

    private function isAllowedTransition(string $from, string $to): bool
    {
        $transitions = [
            'awaiting_payment' => ['pending_provisioning', 'cancelled'],
            'pending_provisioning' => ['active', 'provisioning_failed'],
            // ... etc
        ];

        return in_array($to, $transitions[$from] ?? [], strict: true);
    }
}
```

---

## 4. PEST TESTING WITH LARAVEL

### 4.1 Testing API Endpoints with Custom Auth Traits

**Pattern**: Use `AuthTestTrait` for authentication in tests.

```php
// ====== TRAIT DEFINITION ======
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

trait AuthTestTrait
{
    use RefreshDatabase;

    protected User|Staff $user;

    /**
     * Create authenticated staff user with specific permissions
     */
    protected function authorized_user(
        array $permissions = [],
        string $guard = 'staff'
    ): User|Staff {
        $user = $guard === 'staff' 
            ? Staff::factory()->create()
            : User::factory()->create();

        if ($permissions) {
            $user->givePermissionTo($permissions);
        }

        Sanctum::actingAs($user, ['*']);
        $this->user = $user;

        return $user;
    }

    /**
     * Create authenticated user WITHOUT specific permission
     */
    protected function unauthorized_user(string $guard = 'staff'): User|Staff
    {
        $user = $guard === 'staff'
            ? Staff::factory()->create()
            : User::factory()->create();

        Sanctum::actingAs($user, ['*']);
        $this->user = $user;

        return $user;
    }

    /**
     * Create customer user
     */
    protected function customer(?User $user = null): User
    {
        $user = $user ?? User::factory()->create();
        Sanctum::actingAs($user, ['*']);
        $this->user = $user;

        return $user;
    }

    /**
     * Create admin staff user
     */
    protected function admin_user(): Staff
    {
        return $this->authorized_user();
    }
}

// ====== USAGE IN TESTS ======
use Tests\Support\Traits\AuthTestTrait;
use App\Enums\PermissionEnum;

uses(AuthTestTrait::class);

describe('Payment API', function () {
    describe('Create Payment', function () {
        it('creates payment with proper permissions', function () {
            // Authorize user with specific permission
            $this->authorized_user([PermissionEnum::PAYMENT_CREATE]);

            $response = postJson(route('admin.payment.store'), [
                'method' => 'credit_card',
                'amount' => 150.00,
                'admin_notes' => 'Manual payment entry',
            ]);

            expect($response->status())->toBe(201)
                ->and($this->assertDatabaseHas('payments', ['method' => 'credit_card']));
        });

        it('denies without required permission', function () {
            // Create user WITHOUT PAYMENT_CREATE permission
            $this->unauthorized_user();

            $response = postJson(route('admin.payment.store'), [
                'method' => 'credit_card',
                'amount' => 150.00,
            ]);

            expect($response->status())->toBe(403);
        });

        it('validates required fields', function () {
            $this->authorized_user([PermissionEnum::PAYMENT_CREATE]);

            $response = postJson(route('admin.payment.store'), [
                'method' => '',  // Missing required field
                'amount' => 150.00,
            ]);

            expect($response->status())->toBe(422);
            expect($response->json('errors'))->toHaveKey('method');
        });
    });
});
```

### 4.2 Testing Job Dispatches

**Pattern**: Use PEST's job testing with Bus facade and datasets.

```php
use Illuminate\Support\Facades\Bus;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

describe('Provisioning Jobs', function () {
    beforeEach(function () {
        // Prevent actual job execution for fast tests
        Bus::fake();
    });

    it('dispatches provisioning job when enrollment becomes active', function () {
        $enrollment = Enrollment::factory()
            ->create(['enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT]);

        $action = app(UpdateEnrollmentStatusAction::class);
        $action->execute($enrollment, EnrollmentStatusEnum::ACTIVE);

        // Assert job was dispatched
        Bus::assertDispatched(ProvisionEnrollmentJob::class);

        // Assert job received correct payload
        Bus::assertDispatched(ProvisionEnrollmentJob::class, function ($job) use ($enrollment) {
            return $job->enrollmentId === $enrollment->id;
        });
    });

    it('does not dispatch job if transition fails', function () {
        $enrollment = Enrollment::factory()
            ->create(['enrollment_status' => EnrollmentStatusEnum::EXPIRED]);

        // Try invalid transition
        expect(fn () => 
            app(UpdateEnrollmentStatusAction::class)->execute(
                $enrollment,
                EnrollmentStatusEnum::AWAITING_PAYMENT  // Invalid!
            )
        )->toThrow(InvalidStateTransitionException::class);

        Bus::assertNotDispatched(ProvisionEnrollmentJob::class);
    });

    it('dispatches jobs for multiple enrollments with dataset', function () {
        $enrollments = Enrollment::factory(3)
            ->create(['enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT]);

        collect($enrollments)->each(function ($enrollment) {
            $action = app(UpdateEnrollmentStatusAction::class);
            $action->execute($enrollment, EnrollmentStatusEnum::ACTIVE);
        });

        // Assert 3 jobs dispatched
        Bus::assertDispatchedTimes(ProvisionEnrollmentJob::class, 3);
    });
});
```

### 4.3 Testing Policy Authorization

**Pattern**: Use Gate::authorize in tests to verify policy enforcement.

```php
use Tests\Support\Traits\AuthTestTrait;
use App\Enums\PermissionEnum;

uses(AuthTestTrait::class);

describe('Payment Authorization', function () {
    it('allows admin with permission to create payments', function () {
        $this->authorized_user([PermissionEnum::PAYMENT_CREATE]);

        $response = postJson(route('admin.payment.store'), [
            'method' => 'credit_card',
            'amount' => 100.00,
        ]);

        expect($response->status())->toBe(201);
    });

    it('denies without PAYMENT_CREATE permission', function () {
        // Create staff without permission
        Staff::factory()->create();
        Sanctum::actingAs($this->user, ['*']);

        $response = postJson(route('admin.payment.store'), [
            'method' => 'credit_card',
            'amount' => 100.00,
        ]);

        expect($response->status())->toBe(403);
    });

    it('allows payment update with PAYMENT_UPDATE permission', function () {
        $payment = Payment::factory()->create();
        
        $this->authorized_user([PermissionEnum::PAYMENT_UPDATE]);

        $response = patchJson(route('admin.payment.update', $payment), [
            'status' => 'confirmed',
        ]);

        expect($response->status())->toBe(200);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'confirmed',
        ]);
    });
});
```

### 4.4 Dataset Usage for Multiple Scenarios

**Pattern**: Use PEST's `.with()` for parameterized testing.

```php
use Tests\Support\Traits\AuthTestTrait;
use App\Enums\EnrollmentStatusEnum;

uses(AuthTestTrait::class);

// ====== INLINE DATASETS ======
describe('Enrollment Status Transitions', function () {
    it('validates transition rules', function (
        EnrollmentStatusEnum $currentStatus,
        EnrollmentStatusEnum $targetStatus,
        bool $shouldSucceed
    ) {
        $enrollment = Enrollment::factory()
            ->create(['enrollment_status' => $currentStatus]);

        $stateService = app(EnrollmentStateTransitionService::class);

        if ($shouldSucceed) {
            expect($stateService->canTransition($enrollment, $targetStatus))->toBeTrue();
        } else {
            expect($stateService->canTransition($enrollment, $targetStatus))->toBeFalse();
        }
    })->with([
        // Valid transitions
        [
            EnrollmentStatusEnum::AWAITING_PAYMENT,
            EnrollmentStatusEnum::PENDING_PROVISIONING,
            true,
        ],
        [
            EnrollmentStatusEnum::PENDING_PROVISIONING,
            EnrollmentStatusEnum::ACTIVE,
            true,
        ],
        [
            EnrollmentStatusEnum::ACTIVE,
            EnrollmentStatusEnum::SUSPENDED,
            true,
        ],
        // Invalid transitions
        [
            EnrollmentStatusEnum::EXPIRED,
            EnrollmentStatusEnum::ACTIVE,
            false,
        ],
        [
            EnrollmentStatusEnum::CANCELLED,
            EnrollmentStatusEnum::PENDING_PROVISIONING,
            false,
        ],
    ]);

    // ====== NAMED DATASET ======
    it('handles different payment methods', function (string $method, bool $isValid) {
        $this->authorized_user([PermissionEnum::PAYMENT_CREATE]);

        $response = postJson(route('admin.payment.store'), [
            'method' => $method,
            'amount' => 100.00,
        ]);

        if ($isValid) {
            expect($response->status())->toBe(201);
        } else {
            expect($response->status())->toBe(422);
        }
    })->with([
        'credit_card' => ['credit_card', true],
        'bank_transfer' => ['bank_transfer', true],
        'wallet' => ['wallet', true],
        'invalid_method' => ['invalid_method', false],
        'empty_method' => ['', false],
    ]);
});

// ====== SHARED DATASET (tests/Datasets/PaymentMethods.php) ======
dataset('payment_methods', [
    'credit_card' => ['credit_card', 'Credit Card', true],
    'bank_transfer' => ['bank_transfer', 'Bank Transfer', true],
    'wallet' => ['wallet', 'Wallet', true],
]);

it('validates payment method enum', function (string $value, string $label, bool $valid) {
    expect(PaymentMethodEnum::tryFrom($value) !== null)->toBe($valid);
})->with('payment_methods');

// ====== BOUND DATASET (Database models created per test) ======
it('can retrieve payment for authenticated user', function (Payment $payment) {
    $this->customer($payment->user);

    $response = getJson(route('shop.payment.show', $payment));

    expect($response->status())->toBe(200)
        ->and($response->json('data.id'))->toBe($payment->id);
})->with([
    fn () => Payment::factory()->create(['amount' => 50.00]),
    fn () => Payment::factory()->create(['amount' => 100.00]),
    fn () => Payment::factory()->create(['amount' => 250.00]),
]);
```

---

## 5. ARCHITECTURE PATTERNS IN JEDUSHOP

### Controllers
- Thin, delegation-focused
- Use `Gate::authorize()` for policy checks
- Accept Data DTOs for requests
- Return Data DTOs for responses
- Use response macros: `response()->success()`, `response()->notFound()`

### Actions
- Encapsulate business logic
- Called from controllers
- Injectable dependencies via constructor
- Handle transactions, side effects, notifications
- Can dispatch jobs

### Data Classes
- Request/response DTOs
- `rules()` for validation
- `bodyParameters()` for Scribe docs
- Single source of truth for API contracts

### Jobs
- Async work, long-running operations
- Constructor injection for dependencies
- `backoff()` for exponential retry
- `failed()` callback for error handling
- Use Context for request tracing

### Enums
- Status states (with transition logic)
- Groups/roles (with permission grouping)
- Method enums with labels
- Custom methods for groups (occupying/non-occupying statuses)

---

## REFERENCES

1. **Laravel 12 Documentation**: https://laravel.com/docs/12.x
2. **Spatie Laravel Data v4**: https://spatie.be/docs/laravel-data/v4
3. **Pest Testing v4**: https://pestphp.com/docs/getting-started
4. **JeduShop Project Patterns**:
   - `app/Data/Admin/Payment/` - Data DTOs with validation
   - `app/Actions/` - Business logic encapsulation
   - `app/Jobs/` - Job dispatching and retry
   - `tests/Integration/Jobs/` - Job testing patterns
   - `app/Enums/` - State enums with custom methods

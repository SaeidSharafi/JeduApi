# Backend Developer Guide - Order Creation and Discount System

## Table of Contents
1. [System Architecture Overview](#system-architecture-overview)
2. [Core Components Deep Dive](#core-components-deep-dive)
3. [Discount System Architecture](#discount-system-architecture)
4. [Order Creation Flow](#order-creation-flow)
5. [Payment Processing](#payment-processing)
6. [Database Design](#database-design)
7. [Testing Strategy](#testing-strategy)
8. [Extending the System](#extending-the-system)
9. [Performance Optimization](#performance-optimization)
10. [Debugging and Troubleshooting](#debugging-and-troubleshooting)

---

## System Architecture Overview

### Architectural Patterns

The JeduShop order and discount system follows several key architectural patterns:

1. **Action Pattern** - Single-purpose business logic classes
2. **Service Pattern** - Complex domain logic encapsulation  
3. **Strategy Pattern** - Pluggable discount conditions and actions
4. **Event-Driven Architecture** - Decoupled system reactions
5. **Data Transfer Objects (DTOs)** - Type-safe data validation
6. **Repository Pattern** - Data access abstraction

### High-Level System Flow

```
HTTP Request → Controller → Action → Service → Model → Database
     ↓             ↓          ↓         ↓       ↓        ↓
  Validation   Routing   Business   Domain   ORM   Persistence
                        Logic      Logic
```

### Key Design Principles

- **Single Responsibility** - Each class has one clear purpose
- **Open/Closed** - Easy to extend without modifying existing code
- **Dependency Inversion** - Depend on abstractions, not concretions
- **Type Safety** - Comprehensive use of PHP types and Laravel Data
- **Immutable Data** - DTOs are readonly where possible
- **Event-Driven** - Loose coupling through events

---

## Core Components Deep Dive

### 1. Actions (Business Logic Orchestrators)

Actions are the primary entry points for business operations. They orchestrate complex workflows while maintaining transactional integrity.

#### CreateOrderAction

**Location**: `app/Actions/Admin/Order/CreateOrderAction.php`

**Responsibilities**:
- Coordinate order creation workflow
- Apply business validation rules
- Calculate pricing with discounts
- Ensure data consistency
- Dispatch domain events

**Key Methods**:

```php
public function handle(OrderCreateData $data): Order
{
    // 1. Calculate prices and discounts
    $context = $this->orderCalculationService->calculate($data);
    
    // 2. Validate business rules
    $this->validateNoDuplicatePurchases($context->customer->id, $deliveryOptionIds);
    
    // 3. Create order in transaction
    $order = DB::transaction(function () use ($data, $context): Order {
        // Create order and items
        // Create enrollments
        // Update usage counts
    });
    
    // 4. Dispatch events
    OrderCreatedEvent::dispatch($order);
    
    return $order;
}
```

**Key Validation Methods**:
- `validateItem()` - Product availability, capacity, payment options
- `validateNoDuplicatePurchases()` - Prevent duplicate enrollments
- Transactional safety with database locks

#### CreatePaymentAction

**Location**: `app/Actions/Admin/Payment/CreatePaymentAction.php`

**Key Features**:
- Automatic payment amount calculation
- Pessimistic locking for concurrency safety
- Payment method-specific validation
- Event dispatch for fulfillment triggering

```php
public function handle(Order $order, PaymentCreateData $paymentData, Staff $adminUser): ?Payment
{
    return DB::transaction(function () use ($order, $paymentData, $adminUser): ?Payment {
        // Lock order to prevent concurrent modifications
        $order = Order::lockForUpdate()->findOrFail($order->id);
        
        // Calculate required payment (never trust user input for amounts)
        $amountToPay = $this->calculateRequiredPayment($order);
        
        // Create payment record
        $payment = $this->createPaymentRecordAndDispatchEvents(...);
        
        // Trigger fulfillment if payment completed
        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            PaymentCompletedEvent::dispatch($payment);
        }
        
        return $payment;
    });
}
```

### 2. Services (Domain Logic)

Services contain complex business logic that spans multiple models or requires external systems.

#### OrderCalculationService

**Location**: `app/Services/Discounts/OrderCalculationService.php`

**Purpose**: Calculate order totals with all applicable discounts

**Key Method**: `calculate(OrderCreateData $data): OrderContextData`

**Flow**:
1. **Build Initial Context** - Load products, customers, cached prices
2. **Find Applicable Promotion** - Use PromotionFinder to select best promotion
3. **Check Conditions** - Validate all promotion conditions
4. **Apply Actions** - Execute discount actions on order items
5. **Return Context** - Complete order context with totals

```php
public function calculate(OrderCreateData $data): OrderContextData
{
    // 1. Build initial state (before discounts)
    $context = $this->buildInitialContext($data);
    
    // 2. Find applicable promotion
    $promotion = $this->promotionFinder->findApplicablePromotion($data);
    
    if ($promotion && $promotion->type === DiscountTypeEnum::CART_CHECKOUT) {
        if ($this->allConditionsPass($promotion, $context)) {
            $context->evaluating_promotion = $promotion;
            $context->triggered_by_coupon_code = $data->applied_coupon_code;
            
            // Apply all promotion actions
            $this->applyActions($promotion, $context);
        }
    }
    
    return $context;
}
```

**Pricing Hierarchy** (implemented in `getBasePrice()`):
1. **Product-specific discount price** (cached from promotions)
2. **Featured price** (manual sale price)
3. **Standard price** (default product price)

#### PromotionFinder

**Location**: `app/Services/Discounts/PromotionFinder.php`

**Purpose**: Select the best applicable promotion for an order

**Selection Logic**:
1. Filter active promotions within date range
2. Filter by promotion type (cart vs product)
3. Check coupon code if provided
4. Validate usage limits
5. Return highest priority promotion

---

## Discount System Architecture

### Overview

The discount system uses a **plugin architecture** that allows for extensible conditions and actions without modifying core code.

### Key Components

1. **DiscountHandlerRegistry** - Discovers and manages all handlers
2. **DiscountMetadataService** - Provides metadata for dynamic forms
3. **Conditions** - Determine when discounts apply
4. **Actions** - Execute the actual discount logic
5. **Config Classes** - Type-safe configuration for handlers

### Handler Discovery Process

The system automatically discovers discount handlers using reflection:

```php
// DiscountHandlerRegistry::discoverHandlers()
private function discoverHandlers(): void
{
    $discoveryPaths = config('discounts.discovery_paths', []);
    
    foreach ($discoveryPaths as $baseNamespace => $relativePath) {
        $this->discoverHandlersInPath($baseNamespace, $relativePath);
    }
}
```

**Discovery Rules**:
1. Scan configured directory paths
2. Find classes implementing handler interfaces
3. Check for `#[DiscountHandlerKey('handler_name')]` attribute
4. Register handler with its key
5. Map handler to its configuration class

### Handler Interfaces

#### Condition Interfaces

**Cart Conditions**: `DiscountConditionContract`
```php
interface DiscountConditionContract
{
    public static function getConfigClass(): string;
    public function passes(OrderContextData $context, Data $configuration): bool;
}
```

**Product Conditions**: `ProductDiscountConditionContract`
```php
interface ProductDiscountConditionContract
{
    public static function getConfigClass(): string;
    public function passes(ProductDeliveryOption $option, Data $configuration): bool;
}
```

#### Action Interfaces

**Cart Actions**: `DiscountActionContract`
```php
interface DiscountActionContract
{
    public static function getConfigClass(): string;
    public function apply(OrderContextData $context, Data $configuration): void;
}
```

**Product Actions**: `ProductDiscountActionContract`
```php
interface ProductDiscountActionContract
{
    public static function getConfigClass(): string;
    public function apply(ProductDeliveryOption $option, Data $configuration): int;
}
```

### Current Implementations

#### Cart Conditions

**CartValueCondition** (`cart_value_over`)
```php
#[DiscountHandlerKey('cart_value_over')]
final class CartValueCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return CartValueConditionConfigData::class;
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        if (!$configuration instanceof CartValueConditionConfigData) {
            return false;
        }

        $comparisonValue = $configuration->include_prepayments
            ? $context->subtotal_all_items
            : $context->subtotal_full_payment_items;

        return match ($configuration->operator) {
            MathOperatorEnum::GREATER_THAN_OR_EQUAL => $comparisonValue >= $configuration->value,
            MathOperatorEnum::GREATER_THAN => $comparisonValue > $configuration->value,
            // ... other operators
        };
    }
}
```

**Configuration Class**:
```php
final class CartValueConditionConfigData extends Data
{
    public function __construct(
        public MathOperatorEnum $operator,
        public int $value,
        public bool $include_prepayments,
    ) {}

    public static function rules(): array
    {
        return [
            'operator' => ['required', Rule::enum(MathOperatorEnum::class)],
            'value' => ['required', 'integer', 'min:0'],
            'include_prepayments' => ['boolean'],
        ];
    }

    public static function descriptions(): array
    {
        return [
            'operator' => 'The mathematical operator to use for comparison.',
            'value' => 'The value to compare against the cart total.',
            'include_prepayments' => 'If true, include prepayment items in calculation.',
        ];
    }
}
```

#### Cart Actions

**ApplyPercentageDiscountToItemsAction** (`apply_percentage_off`)
```php
#[DiscountHandlerKey('apply_percentage_off')]
final class ApplyPercentageDiscountToItemsAction implements DiscountActionContract
{
    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (!$configuration instanceof ApplyPercentageDiscountConfigData) {
            return;
        }

        $discountRate = $configuration->percentage / 100;
        $promotionName = $context->evaluating_promotion?->name ?? 'Discount';

        foreach ($context->items as $item) {
            // Skip prepayment items (critical business rule)
            if ($item->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT) {
                continue;
            }

            $discountPerUnit = (int) round($item->price * $discountRate);
            
            // Cannot discount more than item price
            if ($discountPerUnit > $item->price) {
                $discountPerUnit = $item->price;
            }

            // Update item totals
            $item->discount_amount += ($discountPerUnit * $item->qty);
            $item->total = ($item->price * $item->qty) - $item->discount_amount;

            // Add audit trail
            $item->applied_discount_details[] = [
                'promotion_id' => $context->evaluating_promotion?->id,
                'promotion_name' => $promotionName,
                'applied_amount' => ($discountPerUnit * $item->qty),
                'coupon_code' => $context->triggered_by_coupon_code,
            ];
        }
    }
}
```

#### Product-Specific Handlers

**ProductCategoryCondition** (`product_in_category`)
```php
#[DiscountHandlerKey('product_in_category')]
final class ProductCategoryCondition implements ProductDiscountConditionContract
{
    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        if (!$configuration instanceof ProductCategoryConditionConfigData) {
            return false;
        }

        $productId = $option->product->id;
        
        $matching = DB::table('categorizables')
            ->where('categorizable_type', 'product')
            ->where('categorizable_id', $productId)
            ->whereIn('category_id', $configuration->category_ids)
            ->count();

        return $matching > 0;
    }
}
```

**ApplyPercentageDiscountToProductAction** (`apply_percentage_off_product`)
```php
#[DiscountHandlerKey('apply_percentage_off_product')]
final class ApplyPercentageDiscountToProductAction implements ProductDiscountActionContract
{
    public function apply(ProductDeliveryOption $option, Data $configuration): int
    {
        if (!$configuration instanceof ApplyPercentageDiscountConfigData) {
            return $option->price;
        }

        $discountRate = $configuration->percentage / 100;
        $discounted = (int) round($option->price * (1 - $discountRate));
        
        return max($discounted, 0);
    }
}
```

### Metadata Service

**DiscountMetadataService** provides dynamic metadata for frontend form building:

```php
public function getMetadata(): array
{
    return [
        'cart' => [
            'conditions' => $this->getCartConditions(),
            'actions' => $this->getCartActions(),
        ],
        'product' => [
            'conditions' => $this->getProductConditions(),
            'actions' => $this->getProductActions(),
        ],
    ];
}
```

**Configuration Schema Extraction**:
```php
public function extractConfigSchema(string $configClass): array
{
    $reflection = new ReflectionClass($configClass);
    $constructor = $reflection->getConstructor();
    
    $schema = [];
    foreach ($constructor->getParameters() as $parameter) {
        $schema[$parameter->getName()] = [
            'type' => $this->getParameterType($parameter->getType()),
            'required' => !$parameter->isOptional(),
            'description' => $this->generateParameterDescription($parameter),
        ];
        
        // Handle enums
        if ($this->getParameterType($parameter->getType()) === 'enum') {
            $schema[$parameter->getName()]['cases'] = $this->getEnumCases($parameter);
        }
        
        // Handle defaults
        if ($parameter->isDefaultValueAvailable()) {
            $schema[$parameter->getName()]['default'] = $parameter->getDefaultValue();
        }
    }
    
    return $schema;
}
```

---

## Order Creation Flow

### Detailed Flow Analysis

#### Step 1: Order Calculation Preview

**Endpoint**: `POST /api/v1/admin/order-calculation/preview`
**Controller**: `OrderCalculationController::preview()`

```php
public function preview(Request $request, OrderCalculationService $orderCalculationService): JsonResponse
{
    // Basic validation
    $validated = $request->validate([
        'customer_id' => 'required|integer|exists:users,id',
        'items' => 'required|array|min:1',
        'items.*.product_delivery_option_id' => 'required|integer|exists:product_delivery_options,id',
        'items.*.qty_ordered' => 'required|integer|min:1',
        'items.*.payment_type' => 'required|string',
        'applied_coupon_code' => 'nullable|string',
    ]);

    $data = new OrderCreateData(...);
    $context = $orderCalculationService->calculate($data);

    return response()->json([
        'subtotal' => $context->items->sum(fn($i) => $i->price * $i->qty),
        'discount_amount' => $context->items->sum('discount_amount'),
        'grand_total' => $context->items->sum('total'),
        'items' => $context->items,
        'applied_cart_discounts' => $context->applied_cart_discounts,
        'triggered_by_coupon_code' => $context->triggered_by_coupon_code,
    ]);
}
```

#### Step 2: Order Creation

**Endpoint**: `POST /api/v1/admin/orders`
**Action**: `CreateOrderAction::handle()`

**Validation Layers**:
1. **Form Request Validation** - Basic data types and required fields
2. **Business Logic Validation** - Product availability, capacity, duplicates
3. **Payment Type Validation** - Ensure payment options are available

**Transaction Flow**:
```php
$order = DB::transaction(function () use ($data, $context): Order {
    // 1. Create order record
    $order = Order::create([
        'admin_notes' => $data->admin_notes,
        // Other calculated fields
    ]);

    // 2. Create order items
    $orderItemsData = new Collection();
    foreach ($context->items as $calculatedItem) {
        $orderItemsData->push([
            'product_delivery_option_id' => $calculatedItem->product_delivery_option->id,
            'qty_ordered' => $calculatedItem->qty,
            'price' => $calculatedItem->price,
            'discount_amount' => $calculatedItem->discount_amount,
            'total' => $calculatedItem->total,
            'payment_type' => $calculatedItem->payment_type,
            'status' => OrderItemStatusEnum::PENDING,
        ]);
    }
    $order->items()->createMany($orderItemsData->all());

    // 3. Create enrollments
    foreach ($order->items as $item) {
        if ($item->productDeliveryOption->product->type === 'course') {
            Enrolment::create([
                'customer_id' => $order->customer_id,
                'product_delivery_option_id' => $item->product_delivery_option_id,
                'order_item_id' => $item->id,
                'status' => EnrolmentStatusEnum::PENDING,
            ]);
        }
    }

    return $order;
});
```

#### Step 3: Usage Count Updates

```php
private function incrementUsageCounts(OrderContextData $context): void
{
    $promotion = $context->evaluating_promotion;
    $promotion->increment('total_usage_count');

    if ($context->triggered_by_coupon_code) {
        $coupon = $promotion->coupons()
            ->where('code', $context->triggered_by_coupon_code)
            ->first();
        $coupon?->increment('usage_count');
    }
}
```

---

## Payment Processing

### Payment Creation Flow

**Endpoint**: `POST /api/v1/admin/payments`
**Action**: `CreatePaymentAction::handle()`

**Key Features**:
- **Automatic Amount Calculation** - Never trust user input for payment amounts
- **Pessimistic Locking** - Prevent concurrent payment creation
- **Payment Method Validation** - Specific validation per payment method
- **Event-Driven Fulfillment** - PaymentCompletedEvent triggers fulfillment

**Amount Calculation Logic**:
```php
private function calculateRequiredPayment(Order $order): float
{
    $hasCompletedPayments = $order->payments()
        ->where('status', 'completed')
        ->exists();

    if (!$hasCompletedPayments) {
        // First payment: sum of all items
        return $order->items->sum('total');
    }

    // Subsequent payments: remaining balance
    return $order->balance_due;
}
```

**Payment Method Validation**:
```php
private function validateBankTransferDetails(PaymentCreateData $paymentData): void
{
    $rules = [
        'data.transaction_id' => ['required', 'string', 'max:255'],
        'data.transaction_date' => ['required', 'date:Y-m-d', Rule::date()->beforeOrEqual(today())],
        'data.sender_name' => ['required', 'string', 'max:255'],
        'data.notes' => ['nullable', 'string', 'max:1000'],
    ];

    Validator::make(['data' => $paymentData->data?->toArray() ?? []], $rules)->validate();
}
```

---

## Database Design

### Core Tables

#### orders
```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    subtotal INT NOT NULL,
    discount_amount INT NOT NULL DEFAULT 0,
    grand_total INT NOT NULL,
    admin_notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (customer_id) REFERENCES users(id)
);
```

#### order_items
```sql
CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_delivery_option_id BIGINT UNSIGNED NOT NULL,
    qty_ordered INT NOT NULL,
    price INT NOT NULL,
    discount_amount INT NOT NULL DEFAULT 0,
    total INT NOT NULL,
    payment_type ENUM('full_payment', 'pre_payment') NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_delivery_option_id) REFERENCES product_delivery_options(id)
);
```

#### discount_promotions
```sql
CREATE TABLE discount_promotions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type ENUM('product_specific', 'cart_checkout') NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    starts_at TIMESTAMP NULL DEFAULT NULL,
    ends_at TIMESTAMP NULL DEFAULT NULL,
    priority INT NOT NULL DEFAULT 100,
    stop_processing_subsequent_rules BOOLEAN NOT NULL DEFAULT false,
    usage_limit_total INT NULL,
    usage_limit_per_customer INT NULL,
    total_usage_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    INDEX idx_active_dates (is_active, starts_at, ends_at),
    INDEX idx_type_priority (type, priority)
);
```

#### discount_promotion_rules
```sql
CREATE TABLE discount_promotion_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    discount_promotion_id BIGINT UNSIGNED NOT NULL,
    type ENUM('condition', 'action') NOT NULL,
    handler VARCHAR(255) NOT NULL,
    configuration JSON NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (discount_promotion_id) REFERENCES discount_promotions(id) ON DELETE CASCADE,
    INDEX idx_promotion_type (discount_promotion_id, type)
);
```

#### discount_coupons
```sql
CREATE TABLE discount_coupons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    discount_promotion_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    usage_limit INT NULL,
    usage_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (discount_promotion_id) REFERENCES discount_promotions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_code (code),
    INDEX idx_active_code (is_active, code)
);
```

#### product_delivery_option_discount_prices (Caching Table)
```sql
CREATE TABLE product_delivery_option_discount_prices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_delivery_option_id BIGINT UNSIGNED NOT NULL,
    discount_promotion_id BIGINT UNSIGNED NOT NULL,
    original_price INT NOT NULL,
    discounted_price INT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (product_delivery_option_id) REFERENCES product_delivery_options(id) ON DELETE CASCADE,
    FOREIGN KEY (discount_promotion_id) REFERENCES discount_promotions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pdo_promotion (product_delivery_option_id, discount_promotion_id)
);
```

### Important Relationships

1. **Order → OrderItems** (1:N) - Order contains multiple items
2. **OrderItem → ProductDeliveryOption** (N:1) - Each item references a product option
3. **Order → Payments** (1:N) - Order can have multiple payments
4. **DiscountPromotion → Rules** (1:N) - Promotion has multiple conditions/actions
5. **DiscountPromotion → Coupons** (1:N) - Promotion can have multiple coupon codes
6. **OrderItem → Enrolment** (1:1) - Each order item creates an enrollment

---

## Testing Strategy

### Test Structure

The system uses **Pest PHP** for testing with organized test categories:

#### Unit Tests
- **Models** - Test model relationships, accessors, mutators
- **Services** - Test business logic in isolation
- **Actions** - Test action classes with mocked dependencies
- **Enums** - Test enum functionality
- **Handlers** - Test individual discount conditions/actions

#### Feature Tests
- **API Controllers** - Test HTTP endpoints end-to-end
- **Order Creation Flow** - Test complete order workflows
- **Payment Processing** - Test payment creation and validation
- **Discount Application** - Test discount calculations

### Example Test Structure

#### Unit Test Example
```php
// tests/Unit/Services/DiscountMetadataServiceTest.php
describe('DiscountMetadataService', function () {
    beforeEach(function () {
        $this->mockRegistry = $this->mock(DiscountHandlerRegistry::class);
        $this->service = new DiscountMetadataService($this->mockRegistry);
    });

    it('returns correct metadata structure for handlers with config', function () {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')
            ->andReturn(['cart_key' => 'CartCondClass']);
        
        // ... setup mock expectations
        
        $result = $this->service->getMetadata();
        
        expect($result['cart']['conditions'][0]['key'])->toBe('cart_key');
        expect($result['cart']['conditions'][0]['configuration_schema'])
            ->toHaveKey('foo');
    });
});
```

#### Feature Test Example
```php
// tests/Feature/Api/V1/Admin/DiscountPromotionControllerTest.php
describe('DiscountPromotionController', function () {
    beforeEach(function () {
        $this->actingAsStaff();
    });

    describe('POST /api/v1/admin/discount-promotion', function () {
        it('creates a promotion with valid data', function () {
            $promotionData = [
                'name' => 'Test Promotion',
                'type' => 'cart_checkout',
                'is_active' => true,
                'priority' => 100,
                'rules' => [
                    [
                        'type' => 'condition',
                        'handler' => 'cart_value_over',
                        'configuration' => [
                            'operator' => 'greater_than_or_equal',
                            'value' => 10000,
                            'include_prepayments' => false
                        ]
                    ]
                ],
                'coupons' => []
            ];

            $response = $this->postJson('/api/v1/admin/discount-promotion', $promotionData);

            $response->assertCreated();
            $this->assertDatabaseHas('discount_promotions', [
                'name' => 'Test Promotion'
            ]);
        });
    });
});
```

### Testing Best Practices

1. **Arrange-Act-Assert Pattern** - Clear test structure
2. **Mock External Dependencies** - Isolate units under test
3. **Test Edge Cases** - Boundary conditions, error states
4. **Use Factory Classes** - Consistent test data creation
5. **Database Transactions** - Clean state between tests
6. **Test Events** - Verify event dispatching and handling

### Running Tests

```bash
# Run all tests
./vendor/bin/sail pest

# Run specific test suites
./vendor/bin/sail pest --filter=Discount
./vendor/bin/sail pest tests/Unit/Services/
./vendor/bin/sail pest tests/Feature/Api/

# Run with coverage
./vendor/bin/sail pest --coverage

# Run in parallel
./vendor/bin/sail pest --parallel
```

---

## Extending the System

### Adding New Discount Conditions

#### Step 1: Create Condition Class

```php
// app/Services/Discounts/Cart/Conditions/CustomerTierCondition.php

#[DiscountHandlerKey('customer_tier')]
final class CustomerTierCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return CustomerTierConditionConfigData::class;
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        if (!$configuration instanceof CustomerTierConditionConfigData) {
            return false;
        }

        $customer = $context->customer;
        $customerTiers = $customer->tiers->pluck('name')->toArray();

        return match ($configuration->match_policy) {
            'any' => !empty(array_intersect($customerTiers, $configuration->tier_names)),
            'all' => empty(array_diff($configuration->tier_names, $customerTiers)),
            'none' => empty(array_intersect($customerTiers, $configuration->tier_names)),
        };
    }
}
```

#### Step 2: Create Configuration Class

```php
// app/Services/Discounts/Configs/CustomerTierConditionConfigData.php

final class CustomerTierConditionConfigData extends Data
{
    public function __construct(
        public array $tier_names,
        public string $match_policy = 'any', // 'any', 'all', 'none'
    ) {}

    public static function rules(): array
    {
        return [
            'tier_names' => ['required', 'array', 'min:1'],
            'tier_names.*' => ['required', 'string', 'exists:customer_tiers,name'],
            'match_policy' => ['required', 'string', 'in:any,all,none'],
        ];
    }

    public static function descriptions(): array
    {
        return [
            'tier_names' => 'List of customer tier names to check',
            'match_policy' => 'How to match tiers: any (at least one), all (all required), none (must not have any)',
        ];
    }
}
```

#### Step 3: Add Tests

```php
// tests/Unit/Services/Discounts/Cart/Conditions/CustomerTierConditionTest.php

describe('CustomerTierCondition', function () {
    beforeEach(function () {
        $this->condition = new CustomerTierCondition();
    });

    it('passes when customer has any required tier', function () {
        $customer = User::factory()->create();
        $customer->tiers()->attach([1, 2]); // VIP, Premium tiers

        $context = new OrderContextData(
            customer: $customer,
            // ... other required fields
        );

        $config = new CustomerTierConditionConfigData(
            tier_names: ['VIP', 'Gold'],
            match_policy: 'any'
        );

        expect($this->condition->passes($context, $config))->toBeTrue();
    });
});
```

#### Step 4: Update Discovery Configuration

The system will automatically discover the new condition if placed in the configured discovery path (`app/Services/Discounts/`).

### Adding New Discount Actions

#### Example: Fixed Amount Discount Action

```php
// app/Services/Discounts/Cart/Actions/ApplyFixedAmountDiscountAction.php

#[DiscountHandlerKey('apply_fixed_amount_off')]
final class ApplyFixedAmountDiscountAction implements DiscountActionContract
{
    public static function getConfigClass(): string
    {
        return ApplyFixedAmountDiscountConfigData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (!$configuration instanceof ApplyFixedAmountDiscountConfigData) {
            return;
        }

        $totalDiscountRemaining = $configuration->amount;
        $promotionName = $context->evaluating_promotion?->name ?? 'Discount';

        foreach ($context->items as $item) {
            if ($totalDiscountRemaining <= 0) {
                break;
            }

            // Skip prepayment items
            if ($item->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT) {
                continue;
            }

            $itemTotal = $item->price * $item->qty;
            $discountForThisItem = min($totalDiscountRemaining, $itemTotal);

            $item->discount_amount += $discountForThisItem;
            $item->total = $itemTotal - $item->discount_amount;
            $totalDiscountRemaining -= $discountForThisItem;

            $item->applied_discount_details[] = [
                'promotion_id' => $context->evaluating_promotion?->id,
                'promotion_name' => $promotionName,
                'applied_amount' => $discountForThisItem,
                'coupon_code' => $context->triggered_by_coupon_code,
            ];
        }
    }
}
```

### Extending Product-Specific Discounts

Product-specific discounts are cached for performance. When adding new product conditions/actions:

1. **Create the handler classes** (similar to cart handlers)
2. **Implement ProductDiscountIndexer integration**
3. **Test with RegeneratePromotionDiscountPricesJob**

#### Example: Product Price Range Condition

```php
#[DiscountHandlerKey('product_price_range')]
final class ProductPriceRangeCondition implements ProductDiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return ProductPriceRangeConditionConfigData::class;
    }

    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        if (!$configuration instanceof ProductPriceRangeConditionConfigData) {
            return false;
        }

        $price = $option->price;

        return $price >= $configuration->min_price 
            && $price <= $configuration->max_price;
    }
}
```

---

## Performance Optimization

### Caching Strategy

#### 1. Handler Registry Caching
```php
// Production: Cache handler discovery results
Cache::forever(DiscountHandlerRegistry::CACHE_KEY, $handlers);

// Development: Skip cache for dynamic discovery
if (config('app.debug')) {
    $this->discoverAndCacheHandlers();
}
```

#### 2. Product Discount Price Caching
```php
// Pre-calculate product-specific discounts
DB::table('product_delivery_option_discount_prices')
    ->upsert([
        'product_delivery_option_id' => $option->id,
        'discount_promotion_id' => $promotion->id,
        'original_price' => $option->price,
        'discounted_price' => $calculatedPrice,
    ], ['product_delivery_option_id', 'discount_promotion_id']);
```

#### 3. Query Optimization
```php
// Eager load related data to prevent N+1 queries
$deliveryOptions = ProductDeliveryOption::query()
    ->with(['product', 'product.categories'])
    ->whereIn('id', $pdoIds)
    ->get()
    ->keyBy('id');

// Use joins for complex filtering
$promotions = DiscountPromotion::query()
    ->where('is_active', true)
    ->where(function ($query) {
        $query->whereNull('starts_at')
              ->orWhere('starts_at', '<=', now());
    })
    ->orderBy('priority')
    ->limit(1)
    ->first();
```

### Database Indexes

#### Critical Indexes
```sql
-- For promotion lookups
CREATE INDEX idx_active_dates ON discount_promotions (is_active, starts_at, ends_at);
CREATE INDEX idx_type_priority ON discount_promotions (type, priority);

-- For coupon validation
CREATE INDEX idx_active_code ON discount_coupons (is_active, code);

-- For price cache lookups
CREATE INDEX idx_pdo_promotion ON product_delivery_option_discount_prices (product_delivery_option_id, discount_promotion_id);

-- For order queries
CREATE INDEX idx_customer_created ON orders (customer_id, created_at);
CREATE INDEX idx_status_created ON order_items (status, created_at);
```

### Background Job Processing

#### Product Discount Regeneration
```php
// Dispatch after promotion changes
RegeneratePromotionDiscountPricesJob::dispatch($promotion);

// Job implementation
public function handle(): void
{
    $options = ProductDeliveryOption::query()
        ->with(['product'])
        ->where('status', PublicationStatusEnum::PUBLISHED)
        ->whereHas('product', function ($query) {
            $query->where('status', PublicationStatusEnum::PUBLISHED);
        })
        ->get();

    foreach ($options as $option) {
        $calculatedPrice = $this->calculator->calculateFinalDiscountedPrice(
            $option, 
            collect([$this->promotion])
        );

        if ($calculatedPrice < $option->price) {
            // Cache the discounted price
            $this->cacheDiscountPrice($option, $calculatedPrice);
        }
    }
}
```

---

## Debugging and Troubleshooting

### Common Issues and Solutions

#### 1. Discount Not Applying

**Check List**:
- Promotion is active and within date range
- All conditions are passing
- Coupon code is valid and not exceeded usage limits
- Promotion priority order
- `stop_processing_subsequent_rules` flag

**Debug Steps**:
```php
// Add logging to OrderCalculationService::calculate()
Log::info('Evaluating promotion', [
    'promotion_id' => $promotion->id,
    'promotion_name' => $promotion->name,
    'conditions_passed' => $this->allConditionsPass($promotion, $context),
    'coupon_code' => $data->applied_coupon_code,
]);

// Log condition results
foreach ($conditionRules as $rule) {
    $result = $handlerClass->passes($context, $config);
    Log::info('Condition result', [
        'handler' => $rule->handler,
        'config' => $rule->configuration,
        'result' => $result,
    ]);
}
```

#### 2. Price Calculation Inconsistencies

**Check List**:
- Cached discount prices are up to date
- Featured price date ranges
- Product/option publication status
- Currency precision (all prices in cents)

**Debug Steps**:
```php
// Log pricing hierarchy in OrderCalculationService::getBasePrice()
Log::info('Price calculation', [
    'option_id' => $option->id,
    'standard_price' => $option->price,
    'featured_price' => $option->featured_price,
    'is_featured_active' => $this->isFeaturedPriceActive($option),
    'cached_discount' => $precalculatedPrices->get($option->id),
    'final_price' => $finalPrice,
]);
```

#### 3. Payment Amount Mismatches

**Check List**:
- Payment amounts are calculated, not user input
- Order items total correctly
- Previous payments are considered
- Balance due calculation is correct

**Debug Steps**:
```php
// Log payment calculation in CreatePaymentAction
Log::info('Payment calculation', [
    'order_id' => $order->id,
    'items_total' => $order->items->sum('total'),
    'grand_total' => $order->grand_total,
    'previous_payments' => $order->payments->sum('amount'),
    'balance_due' => $order->balance_due,
    'calculated_amount' => $amountToPay,
]);
```

### Performance Debugging

#### Query Analysis
```bash
# Enable query logging
DB::enableQueryLog();

# After operations
$queries = DB::getQueryLog();
foreach ($queries as $query) {
    Log::info('Query executed', [
        'sql' => $query['query'],
        'bindings' => $query['bindings'],
        'time' => $query['time'],
    ]);
}
```

#### Cache Analysis
```php
// Check cache hits
if (Cache::has(DiscountHandlerRegistry::CACHE_KEY)) {
    Log::info('Handler registry cache hit');
} else {
    Log::info('Handler registry cache miss - rebuilding');
}
```

### Useful Artisan Commands

```bash
# Clear discount cache
php artisan cache:forget discounts.handler_registry.cache

# Regenerate all product discount prices
php artisan discounts:regenerate-prices

# View current discount metadata
php artisan tinker
>>> app(DiscountMetadataService::class)->getMetadata()

# Test specific promotion
>>> $promotion = DiscountPromotion::find(1);
>>> $context = app(OrderCalculationService::class)->calculate($orderData);
```

---

## Conclusion

The JeduShop order creation and discount system is built with extensibility, type safety, and performance in mind. Key architectural decisions include:

- **Plugin Architecture** for discount rules
- **Event-Driven Design** for loose coupling
- **Action Pattern** for business logic organization
- **Comprehensive Caching** for performance
- **Type-Safe DTOs** for data validation

When extending the system:
1. Follow established patterns
2. Implement comprehensive tests
3. Consider performance implications
4. Maintain backward compatibility
5. Document new features thoroughly

The system is designed to handle complex e-commerce scenarios while remaining maintainable and extensible for future requirements.

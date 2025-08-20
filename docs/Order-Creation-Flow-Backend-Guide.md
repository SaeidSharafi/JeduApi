# Order Creation Flow - Backend Developer Guide

## Overview
This document provides an in-depth technical guide to the order creation and payment system architecture, focusing on the backend implementation details, data flow, and system design patterns.

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Core Components](#core-components)
3. [Order Creation Deep Dive](#order-creation-deep-dive)
4. [Discount System Architecture](#discount-system-architecture)
5. [Payment Processing Flow](#payment-processing-flow)
6. [Event-Driven Architecture](#event-driven-architecture)
7. [Database Design](#database-design)
8. [Code Organization](#code-organization)
9. [Testing Strategy](#testing-strategy)
10. [Performance Considerations](#performance-considerations)

---

## Architecture Overview

The system follows a **layered architecture** with clear separation of concerns:

```
Controllers → Actions → Services → Models → Database
     ↓           ↓         ↓         ↓         ↓
  HTTP Layer  Business  Domain   Data Access  Storage
             Logic     Logic
```

### Key Design Patterns

1. **Action Pattern** - Single-purpose classes for business operations
2. **Service Pattern** - Complex business logic encapsulation
3. **Event-Driven Architecture** - Decoupled system reactions
4. **Data Transfer Objects (DTOs)** - Type-safe data validation
5. **Strategy Pattern** - Pluggable discount conditions/actions

---

## Core Components

### 1. Actions (Business Logic Entry Points)

#### CreateOrderAction
```php
final readonly class CreateOrderAction
{
    public function __construct(
        protected OrderCalculationService $orderCalculationService
    ) {}

    public function handle(OrderCreateData $data): Order
    {
        // 1. Calculate prices and apply discounts
        $context = $this->orderCalculationService->calculate($data);
        
        // 2. Validate business rules
        $this->validateNoDuplicatePurchases($context->customer->id, $deliveryOptionIds);
        
        // 3. Create order with database transaction
        $order = DB::transaction(function () use ($data, $context): Order {
            // Order creation logic
        });
        
        // 4. Dispatch events
        OrderCreatedEvent::dispatch($order);
        
        return $order;
    }
}
```

**Key Responsibilities:**
- Orchestrate order creation workflow
- Ensure data consistency with database transactions
- Validate business rules (duplicates, capacity, availability)
- Dispatch domain events

#### CreatePaymentAction
```php
final class CreatePaymentAction
{
    public function handle(Order $order, PaymentCreateData $paymentData, Staff $adminUser): ?Payment
    {
        return DB::transaction(function () use ($order, $paymentData, $adminUser): ?Payment {
            // 1. Lock order for concurrent safety
            $order = Order::lockForUpdate()->findOrFail($order->id);
            
            // 2. Calculate required payment amount
            $amountToPay = $this->calculateRequiredPayment($order);
            
            // 3. Create payment record
            $payment = $this->createPaymentRecordAndDispatchEvents();
            
            // 4. Trigger fulfillment if completed
            if ($payment->status === PaymentStatusEnum::COMPLETED) {
                PaymentCompletedEvent::dispatch($payment);
            }
            
            return $payment;
        });
    }
}
```

### 2. Services (Complex Domain Logic)

#### OrderCalculationService
```php
final class OrderCalculationService
{
    private array $conditionHandlers = [
        'cart_value_over' => CartValueCondition::class,
        'product_in_category' => ProductCategoryCondition::class,
    ];

    private array $actionHandlers = [
        'apply_percentage_off' => ApplyPercentageDiscountToItemsAction::class,
    ];

    public function calculate(OrderCreateData $data): OrderContextData
    {
        // 1. Build initial context (prices, items, customer)
        $context = $this->buildInitialContext($data);
        
        // 2. Find applicable promotions
        $promotion = $this->promotionFinder->findApplicablePromotion($data);
        
        // 3. Apply discount logic
        if ($promotion && $this->allConditionsPass($promotion, $context)) {
            $this->applyActions($promotion, $context);
        }
        
        return $context;
    }
}
```

**Key Features:**
- **Extensible Discount System** - Plugin architecture for conditions/actions
- **Price Hierarchy** - Product-specific discounts > Featured prices > Regular prices
- **Context-Driven** - All calculations work on shared context object
- **Type Safety** - Uses DTOs for configuration validation

#### OrderStatusService
```php
class OrderStatusService
{
    public function handlePaymentCompletion(Order $order): void
    {
        // 1. Update individual order items
        foreach ($order->items as $item) {
            $this->completeOrderItemAfterPayment($item);
        }
        
        // 2. Update parent order status
        $this->updateParentOrderStatus($order->fresh());
    }
    
    private function completeOrderItemAfterPayment(OrderItem $item): void
    {
        // Update item status
        $item->status = OrderItemStatusEnum::COMPLETED;
        $item->saveQuietly();
        
        // Update enrollment status
        $this->updateEnrollmentStatus($item);
    }
}
```

---

## Order Creation Deep Dive

### 1. Data Flow Architecture

```
OrderCreateData (DTO)
        ↓
OrderCalculationService::calculate()
        ↓
OrderContextData (enriched with prices/discounts)
        ↓
CreateOrderAction::handle()
        ↓
Database Transaction
        ↓
Order + OrderItems + Enrolments
        ↓
OrderCreatedEvent
```

### 2. Discount Calculation Pipeline

```php
// 1. Initial Context Building
$context = $this->buildInitialContext($data);
// - Loads customer data
// - Resolves product delivery options
// - Calculates base prices (hierarchy: discount > featured > regular)
// - Creates CalculatedOrderItemData objects

// 2. Promotion Discovery
$promotion = $this->promotionFinder->findApplicablePromotion($data);
// - Checks for coupon-based promotions
// - Looks for automatic promotions
// - Validates promotion dates and usage limits

// 3. Condition Evaluation
if ($this->allConditionsPass($promotion, $context)) {
    // Each condition is a separate class implementing DiscountConditionContract
    // Examples: CartValueCondition, ProductCategoryCondition
}

// 4. Action Application
$this->applyActions($promotion, $context);
// Each action mutates the context object
// Examples: ApplyPercentageDiscountToItemsAction
```

### 3. Critical Business Rules

#### Price Calculation Hierarchy
```php
private function getBasePrice(ProductDeliveryOption $option, Collection $precalculatedPrices): int
{
    // 1. Pre-calculated product-specific discount (highest priority)
    if ($precalculatedPrices->has($option->id)) {
        return $precalculatedPrices->get($option->id);
    }
    
    // 2. Active featured price (sale price)
    if ($this->isFeaturedPriceActive($option)) {
        return $option->featured_price;
    }
    
    // 3. Standard price (fallback)
    return $option->price;
}
```

#### Payment Type Logic
```php
// For PRE_PAYMENT items
if ($itemData->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT->value) {
    $initialLineItemTotal = $option->prepayment_amount * $itemData->qty_ordered;
} else {
    // For FULL_PAYMENT items
    $initialLineItemTotal = $startingPriceForCalc * $itemData->qty_ordered;
}
```

#### Discount Application Rules
```php
// CRITICAL: Pre-payment items are excluded from percentage discounts
if ($paymentType === OrderItemPaymentTypeEnum::PRE_PAYMENT) {
    continue; // Skip discount application
}
```

### 4. Concurrency and Data Integrity

#### Pessimistic Locking
```php
// Lock products during order creation to prevent overselling
$deliveryOption = ProductDeliveryOption::query()
    ->where('id', $calculatedItem->product_delivery_option->id)
    ->lockForUpdate()
    ->firstOrFail();
```

#### Database Transactions
```php
$order = DB::transaction(function () use ($data, $context): Order {
    // All order creation happens in a single transaction
    $order = Order::create([...]);
    $order->items()->createMany($orderItemsData->all());
    // Create enrolments
    // Return order
});
```

---

## Discount System Architecture

### 1. Plugin Architecture

The discount system uses a **registry pattern** for extensibility:

```php
class OrderCalculationService 
{
    private array $conditionHandlers = [
        'cart_value_over' => CartValueCondition::class,
        'product_in_category' => ProductCategoryCondition::class,
    ];

    private array $actionHandlers = [
        'apply_percentage_off' => ApplyPercentageDiscountToItemsAction::class,
    ];

    private array $handlerConfigMap = [
        CartValueCondition::class => CartValueConditionConfigData::class,
        ApplyPercentageDiscountToItemsAction::class => ApplyPercentageDiscountConfigData::class,
    ];
}
```

### 2. Condition System

Each condition implements `DiscountConditionContract`:

```php
interface DiscountConditionContract
{
    public function passes(OrderContextData $context, Data $configuration): bool;
}

class CartValueCondition implements DiscountConditionContract
{
    public function passes(OrderContextData $context, Data $configuration): bool
    {
        $config = $configuration; // CartValueConditionConfigData
        
        $cartValue = $config->include_prepayments 
            ? $context->subtotal_all_items 
            : $context->subtotal_full_payment_items;
            
        return match($config->operator) {
            '>=' => $cartValue >= $config->value,
            '>' => $cartValue > $config->value,
            // ... other operators
        };
    }
}
```

### 3. Action System

Each action implements `DiscountActionContract`:

```php
interface DiscountActionContract
{
    public function apply(OrderContextData $context, Data $configuration): void;
}

class ApplyPercentageDiscountToItemsAction implements DiscountActionContract
{
    public function apply(OrderContextData $context, Data $configuration): void
    {
        $discountRate = $configuration->percentage / 100;
        
        foreach ($context->items as $item) {
            // Skip pre-payment items
            if ($item->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT) {
                continue;
            }
            
            // Calculate and apply discount
            $discountPerUnit = (int) round($item->price * $discountRate);
            $item->discount_amount += ($discountPerUnit * $item->qty);
            $item->total = ($item->price * $item->qty) - $item->discount_amount;
        }
    }
}
```

### 4. Configuration Management

Each handler has a corresponding configuration DTO:

```php
class CartValueConditionConfigData extends Data
{
    public function __construct(
        public int $value,
        public string $operator,
        public bool $include_prepayments = false,
    ) {}
}

class ApplyPercentageDiscountConfigData extends Data
{
    public function __construct(
        public float $percentage,
    ) {}
}
```

---

## Payment Processing Flow

### 1. Payment Amount Calculation

```php
private function calculateRequiredPayment(Order $order): float
{
    $hasCompletedPayments = $order->payments()->where('status', 'completed')->exists();
    
    if (!$hasCompletedPayments) {
        // First payment: sum of all item totals
        return $order->items->sum('total');
    } else {
        // Subsequent payment: remaining balance
        return $order->balance_due;
    }
}
```

### 2. Payment Validation

```php
// Check if order needs payment
if ($order->grand_total <= 0) {
    // Free order - create zero-amount completion record
    return $this->createPaymentRecordAndDispatchEvents(/* zero amount */);
}

// Check if already fully paid
if ($order->balance_due <= 0) {
    throw ValidationException::withMessages([
        'payment' => __('messages.order.already_fully_paid')
    ]);
}

// Check for pending payments
if ($order->payments()->where('status', PaymentStatusEnum::PENDING)->exists()) {
    throw ValidationException::withMessages([
        'payment' => __('messages.order.payment_already_pending')
    ]);
}
```

### 3. Multi-Stage Payment Logic

The system supports complex payment scenarios:

**Scenario 1: First Payment**
- Full payment items: Pay complete amount
- Pre-payment items: Pay pre-payment amount only

**Scenario 2: Final Balance Payment**
- Pre-payment items: Pay remaining balance (full_price - prepayment_amount)
- Full payment items: Already paid in first payment

---

## Event-Driven Architecture

### 1. Event Flow

```
Order Created → OrderCreatedEvent
Payment Completed → PaymentCompletedEvent → UpdateStatusesAfterPaymentListener
                                        → ProvisionPaidResourcesListener
Order Status Changed → OrderStatusUpdatedEvent
Refund Completed → RefundCompletedEvent
```

### 2. Key Events

#### PaymentCompletedEvent
```php
class PaymentCompletedEvent
{
    public function __construct(public readonly Payment $payment) {}
}
```

**Listeners:**
- `UpdateStatusesAfterPaymentListener` - Updates order/item statuses
- `ProvisionPaidResourcesListener` - Handles course access provisioning

#### OrderCreatedEvent
```php
final readonly class OrderCreatedEvent
{
    public function __construct(public Order $order) {}
}
```

**Potential Listeners:**
- Send order confirmation emails
- Update inventory
- Trigger external integrations

### 3. Status Update Chain

```php
// PaymentCompletedEvent triggers:
UpdateStatusesAfterPaymentListener::handle()
    ↓
OrderStatusService::handlePaymentCompletion()
    ↓
completeOrderItemAfterPayment() for each item
    ↓
updateEnrollmentStatus() for each enrollment
    ↓
updateParentOrderStatus() for the order
```

---

## Database Design

### 1. Core Tables

#### orders
```sql
- id, increment_id (user-friendly ID)
- status (pending, processing, completed, etc.)
- customer_id, customer_email, customer_phone
- customer_snapshot_json (point-in-time data)
- grand_total, subtotal, discount_amount, tax_amount
- applied_coupon_code
- applied_cart_discounts_json (audit trail)
- admin_notes
```

#### order_items
```sql
- id, order_id
- product_delivery_option_id, vendor_id
- name, sku, price, total
- payment_type (full_payment, pre_payment)
- prepayment_amount
- discount_amount, tax_amount
- qty_ordered, qty_refunded, total_refunded
- status (pending, completed, cancelled, refunded)
- product_data_snapshot_json
- applied_discount_details_json
```

#### payments
```sql
- id, order_id, customer_id
- amount, method, status
- data (payment method specific data)
- admin_notes, created_by
```

#### enrolments
```sql
- id, order_id, order_item_id, customer_id
- product_delivery_option_id
- enrollment_status (pending_provisioning, active, cancelled)
- access_start_date, access_end_date
```

### 2. Key Relationships

```php
// Order relationships
public function items(): HasMany
public function payments(): HasMany  
public function enrolments(): HasMany
public function customer(): BelongsTo

// OrderItem relationships
public function order(): BelongsTo
public function productDeliveryOption(): BelongsTo
public function enrolment(): HasOne
public function refunds(): HasMany

// Payment relationships
public function order(): BelongsTo
public function customer(): BelongsTo
```

---

## Code Organization

### 1. Directory Structure

```
app/
├── Actions/Admin/Order/          # Business logic entry points
│   ├── CreateOrderAction.php
│   ├── UpdateOrderAction.php
│   └── DeleteOrderAction.php
├── Actions/Admin/Payment/        # Payment actions
│   ├── CreatePaymentAction.php
│   └── UpdatePaymentAction.php
├── Services/                     # Complex domain services
│   ├── OrderStatusService.php
│   └── Discounts/
│       ├── OrderCalculationService.php
│       ├── PromotionFinder.php
│       ├── Conditions/
│       └── Actions/
├── Data/Admin/Order/            # DTOs for type safety
│   ├── OrderCreateData.php
│   ├── OrderItemCreateData.php
│   └── OrderData.php
├── Events/                      # Domain events
│   ├── OrderCreatedEvent.php
│   └── PaymentCompletedEvent.php
├── Listeners/                   # Event handlers
├── Models/                      # Eloquent models
├── Http/Controllers/Api/Admin/  # HTTP layer
└── Policies/Admin/             # Authorization
```

### 2. Data Transfer Objects (DTOs)

The system heavily uses Spatie Laravel Data for type-safe data handling:

```php
final class OrderCreateData extends Data
{
    public function __construct(
        public string $status,
        public int $customer_id,
        #[DataCollectionOf(OrderItemCreateData::class)]
        public array $items,
        public ?string $applied_coupon_code = null,
        public ?string $admin_notes = null,
    ) {}
    
    public static function rules(ValidationContext $context): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(OrderStatusEnum::class)],
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            // ... validation rules
        ];
    }
}
```

### 3. Service Provider Registration

```php
// AppServiceProvider or dedicated service provider
$this->app->bind(OrderCalculationService::class, function ($app) {
    return new OrderCalculationService(
        $app->make(PromotionFinder::class)
    );
});
```

---

## Testing Strategy

### 1. Unit Tests

#### Action Tests
```php
it('creates an order with correct totals', function () {
    $data = new OrderCreateData(/* ... */);
    $order = app(CreateOrderAction::class)->handle($data);
    
    expect($order->grand_total)->toBe(15000);
    expect($order->items)->toHaveCount(2);
});
```

#### Service Tests
```php
it('applies percentage discount correctly', function () {
    $context = new OrderContextData(/* ... */);
    $config = new ApplyPercentageDiscountConfigData(percentage: 10);
    
    $action = new ApplyPercentageDiscountToItemsAction();
    $action->apply($context, $config);
    
    expect($context->items->first()->discount_amount)->toBe(1000);
});
```

### 2. Feature Tests

#### API Integration Tests
```php
it('creates order via API endpoint', function () {
    $this->actingAs($adminUser)
         ->postJson('/api/v1/admin/orders', $orderData)
         ->assertStatus(201)
         ->assertJsonStructure(['data' => ['id', 'grand_total']]);
});
```

### 3. Test Data Factories

```php
class OrderFactory extends Factory
{
    public function withCalculatedTotals(array $items): self
    {
        return $this->afterCreating(function (Order $order) use ($items) {
            // Create items and calculate totals
        });
    }
}
```

---

## Performance Considerations

### 1. Database Optimization

#### Query Optimization
```php
// Eager loading to prevent N+1 queries
$orders = Order::with(['items.vendor', 'payments', 'customer'])->get();

// Use select to limit columns
$orders = Order::select(['id', 'increment_id', 'status', 'grand_total'])->get();
```

#### Indexing Strategy
```sql
-- Orders
INDEX(customer_id)
INDEX(status)
INDEX(created_at)

-- Order Items  
INDEX(order_id)
INDEX(product_delivery_option_id)
INDEX(status)

-- Payments
INDEX(order_id)
INDEX(status)
```

### 2. Caching

#### Promotion Caching
```php
// Cache active promotions
$promotions = Cache::remember('active_promotions', 3600, function() {
    return DiscountPromotion::active()->with('rules', 'coupons')->get();
});
```

#### Product Price Caching
```php
// Cache computed discount prices
$discountPrices = Cache::remember("discount_prices_{$promotionId}", 1800, function() {
    return $this->calculateDiscountPrices();
});
```

### 3. Event Queue Processing

```php
// Queue heavy listeners
class ProvisionPaidResourcesListener implements ShouldQueue
{
    use InteractsWithQueue;
    
    public function handle(PaymentCompletedEvent $event): void
    {
        // Heavy provisioning logic runs in background
    }
}
```

### 4. Transaction Optimization

```php
// Keep transactions short and focused
DB::transaction(function() {
    // Only critical operations inside transaction
    $order = Order::create($orderData);
    $order->items()->createMany($itemsData);
    return $order;
});

// Dispatch events outside transaction
OrderCreatedEvent::dispatch($order);
```

---

## Error Handling and Logging

### 1. Validation Exceptions
```php
throw ValidationException::withMessages([
    'items.0' => __('messages.order.item_not_available', ['product' => $productName])
]);
```

### 2. Business Logic Exceptions
```php
if ($order->balance_due <= 0) {
    throw ValidationException::withMessages([
        'payment' => __('messages.order.already_fully_paid', ['order_id' => $order->increment_id])
    ]);
}
```

### 3. Audit Logging
```php
// Order creation audit trail
'applied_cart_discounts_json' => [
    [
        'promotion_id' => 123,
        'promotion_name' => 'Summer Sale',
        'applied_amount' => 5000,
        'coupon_code' => 'SUMMER2023'
    ]
]

// Item-level discount details
'applied_discount_details_json' => [
    [
        'promotion_id' => 123,
        'promotion_name' => 'Summer Sale', 
        'applied_amount' => 2500,
        'coupon_code' => 'SUMMER2023'
    ]
]
```

---

## Extension Points

### 1. Adding New Discount Conditions
```php
// 1. Create condition class
class CustomerSegmentCondition implements DiscountConditionContract { }

// 2. Create config DTO
class CustomerSegmentConditionConfigData extends Data { }

// 3. Register in OrderCalculationService
private array $conditionHandlers = [
    'customer_segment' => CustomerSegmentCondition::class,
];

private array $handlerConfigMap = [
    CustomerSegmentCondition::class => CustomerSegmentConditionConfigData::class,
];
```

### 2. Adding New Payment Methods
```php
// 1. Add enum value
enum PaymentMethodEnum: string 
{
    case PAYPAL = 'paypal';
}

// 2. Create payment data DTO
class PaypalPaymentData extends Data { }

// 3. Add validation in CreatePaymentAction
```

### 3. Adding New Order Statuses
```php
// 1. Add enum value
enum OrderStatusEnum: string
{
    case SHIPPED = 'shipped';
}

// 2. Update OrderStatusService logic
// 3. Add to status transition rules
```

This architecture provides a solid foundation for complex e-commerce order management while remaining extensible and maintainable.

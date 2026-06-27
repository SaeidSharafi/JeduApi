# Refund System Architecture

## Overview

The refund system allows admins to reverse purchases — returning money through the appropriate payment processor, revoking enrollment access, and updating order status. Refunds follow a state machine with strict transitions, a strategy-based processor pattern, and gateway-first architecture for financial safety.

---

## Data Model

```
Order (1) ──→ (N) OrderItem (1) ──→ (N) Refund (N) ──→ (1) Payment
  │               │                    │                    │
  │               ├─ payment_type      ├─ amount            ├─ amount
  │               ├─ total             ├─ deduction_amount  ├─ method
  │               ├─ total_refunded    ├─ status            ├─ status
  │               ├─ qty_refunded      ├─ transaction_details│
  │               ├─ status            │   ├─ receiver_name │
  │               └─ enrollment        │   ├─ card_number   │
  │                                    │   ├─ iban_number   │
  ├─ total_paid (computed)             │   ├─ tracking_code │
  ├─ total_refunded                    │   └─ gateway_tracking_code
  ├─ net_revenue (accessor)            ├─ payment_id (FK)
  └─ balance_due (computed)            ├─ refunded_at
                                       └─ admin_notes


  Payment (1) ──→ (N) PaymentTransaction
                       ├─ gateway_response
                       │   ├─ tracking_code
                       │   ├─ payment_gateway (type)
                       │   └─ delivery_confirmed
                       └─ gateway_request
```

### Key Fields

| Model | Field | Purpose |
|-------|-------|---------|
| `OrderItem` | `payment_type` | `PRE_PAYMENT` or `FULL_PAYMENT` |
| `OrderItem` | `total` | PRE_PAYMENT: prepaid amount. FULL_PAYMENT: full price after discounts |
| `OrderItem` | `total_refunded` | Cumulative amount refunded for this item |
| `OrderItem` | `qty_refunded` | Quantity refunded (currently always = `qty_ordered`) |
| `Order` | `total_paid` | Computed: sum of completed payment amounts |
| `Order` | `total_refunded` | Computed by `UpdateOrderRefundedAmountAction`: sum of completed refund amounts |
| `Order` | `net_revenue` | Accessor: `total_paid - total_refunded` — for dashboards/reports |
| `Order` | `grand_total` | Sum of all `orderItem.total` values |
| `Order` | `full_value_grand_total` | Sum of all items' full values (ignoring prepayment discount) |
| `Order` | `balance_due` | `full_value_grand_total - total_paid` |
| `Payment` | `method` | `digipay`, `wallet`, `bank_transfer`, `mellat_gateway` |
| `PaymentTransaction` | `gateway_response` | Stores `tracking_code`, `payment_gateway` (type), `delivery_confirmed` |
| `Refund` | `status` | PENDING → PROCESSING → COMPLETED / FAILED. Or PENDING → CANCELLED |
| `Refund` | `payment_id` | FK to the oldest completed payment — anchors refund to payment record |
| `Refund` | `receiver_name` | Bank transfer receiver name (flat column, for `RefundOrderAction`) |
| `Refund` | `card_number` | Bank transfer card number (flat column) |
| `Refund` | `iban` | Bank transfer IBAN (flat column) |
| `Refund` | `transaction_details` | JSON: `receiver_name`, `card_number`, `iban_number`, `tracking_code`, `gateway_tracking_code` |

---

## Refund Status State Machine

```
                    ┌──────────────────────────────────┐
                    │           PENDING                │
                    └──────┬────────┬──────────┬───────┘
                           │        │          │
                    ┌──────▼───┐ ┌──▼──────┐ ┌─▼────────┐
                    │PROCESSING│ │COMPLETED│ │CANCELLED │
                    └──┬─────┬─┘ └─────────┘ └──────────┘
                       │     │
                ┌──────▼──┐ ┌▼──────┐
                │COMPLETED│ │FAILED │
                └─────────┘ └───────┘

Terminal states: COMPLETED, FAILED, CANCELLED
```

Implemented in `UpdateRefundStatusAction::validateStatusTransition()`.

---

## Refund Processor Strategy

The system uses a strategy pattern to handle different payment methods. Each processor implements `RefundProcessorInterface`:

```php
interface RefundProcessorInterface
{
    /**
     * Execute the financial reversal.
     * - Digipay: called OUTSIDE the DB transaction (gateway-first).
     * - Wallet: called INSIDE the DB transaction (pessimistic lock).
     * - Manual: no-op, returns null (admin wires money out-of-band).
     *
     * Returns a gateway tracking code, or null.
     */
    public function process(Refund $refund, Order $order, int $amount): ?string;
}
```

| Processor | Payment Methods | Call Location | Behavior |
|-----------|----------------|---------------|----------|
| `DigipayRefundProcessor` | `digipay` | BEFORE DB transaction | Calls Digipay API, propagates `RefundGatewayException` on failure. Includes cumulative cap check and BNPL/CREDIT delivery guard log. |
| `WalletRefundProcessor` | `wallet` | INSIDE DB transaction | Credits wallet balance via `RecordWalletTransactionAction` (pessimistic row lock). |
| `ManualRefundProcessor` | `bank_transfer`, `mellat_gateway` | INSIDE DB transaction | No-op. Logs to `digipay` channel for audit trail. Admin wires money manually. |

**Factory**: `RefundProcessorFactory::make(string $paymentMethod)` resolves the correct processor. Throws `InvalidArgumentException` for unknown methods.

### Gateway-First Architecture (Digipay)

For Digipay refunds, the processor is called **before** any database writes:

```
┌─────────────────────────────────────────────────────────┐
│ OUTSIDE DB TRANSACTION                                  │
│  $trackingCode = $digipayProcessor->process(...)        │
│  → If DigipayException → RefundGatewayException thrown  │
│  → Nothing saved to DB, admin sees error immediately    │
├─────────────────────────────────────────────────────────┤
│ DB::transaction():                                      │
│  1. Create Refund record (status = COMPLETED)           │
│  2. Store gateway_tracking_code in transaction_details  │
│  3. Update OrderItem, Enrollment, Order status          │
│  4. call UpdateOrderRefundedAmountAction                │
│  5. dispatch RefundCompletedEvent                       │
└─────────────────────────────────────────────────────────┘
```

**Wallet** processor runs inside the DB transaction (it is itself a DB write with locking).  
**Manual** processor runs inside the DB transaction (no-op, logs only).

### `skip_gateway` Flag

When `skip_gateway = true` (gated behind `refunds.skip-gateway` permission):

- All gateway calls are skipped entirely
- Refund is marked `COMPLETED` immediately
- Admin notes are appended: `[Gateway skipped by Admin at {timestamp}]`

---

## Refund Creation Flow (Per-Item)

`POST /api/v1/admin/order-item/{orderItem}/refund` → `RefundController@store` → `CreateRefundAction@handle`

### Step 1: Validation (`validateOrderItemIsRefundable`)

| # | Rule | Error Message |
|---|------|---------------|
| 1 | Order must have `total_paid > 0` | `no_completed_payments` |
| 2 | Item status must NOT be `REFUNDED` | `already_refunded` |
| 3 | Item status must NOT be `CANCELLED` | `not_allowed` |
| 4 | No existing non-FAILED refund for this item | `refund_request_exists` |
| 5 | If Digipay + `config('payments.digipay.allow_partial_refund') === false` | `digipay_partial_refund_not_supported` |

> **Rule 5 (Partial Refund Gate)**: When the config flag is `false` (default), per-item Digipay refunds are blocked entirely. The admin must use the full-order refund endpoint (`POST /order/{order}/refund`) instead. Flipping the flag to `true` in `.env` unlocks per-item Digipay refunds without any code change.

### Step 2: Amount Calculation

```php
$amountPaidForItem = calculateAmountPaidForItem($orderItem);
$deductionAmount   = calculateDeductionAmount($data, $orderItem->price);
$refundAmount      = max(0, $amountPaidForItem - $deductionAmount);
```

**`calculateAmountPaidForItem()`** — how much the customer actually paid for this item:

| Condition | Formula |
|-----------|---------|
| `balance_due <= 0` (order fully paid) | `(price - discount + tax) × qty` |
| `balance_due > 0` (not fully paid) | `orderItem->total` |

> **Why this works**: The first payment always equals `sum(items.total)`. For PRE_PAYMENT items, `total = prepayment_amount × qty`. For FULL_PAYMENT items, `total = (price - discount + tax) × qty`. So `item.total` is exactly what was paid toward that item in the first payment. If `balance_due <= 0`, the remaining balance was paid in subsequent payments and the full item value is now covered.

**`calculateDeductionAmount()`** — penalty applied by admin:

| Input | Behavior |
|-------|----------|
| `deduction_amount` only | Fixed deduction in Rials |
| `deduction_percent` only | `floor(price × percent / 100)` — applied to **original item price**, not paid amount |
| Both provided | Must produce the same value (validated), otherwise error |
| Neither | 0 (should not happen — DTO requires one) |

> The percentage is always calculated against the original item price (`orderItem->price`), not the amount paid. If the deduction exceeds the paid amount, `refundAmount = 0` — the customer receives nothing. This is intentional: the deduction is a penalty based on the item's full value.

### Step 3: Processor + Payment Resolution

```php
$payment       = $orderItem->order->payments()->where('status', 'completed')->oldest()->first();
$paymentMethod = $payment?->method->value ?? 'bank_transfer';
$processor     = $this->processorFactory->make($paymentMethod);
```

- Targets the **oldest** completed payment (semantically correct: first payment covers `sum(items.total)`)
- Falls back to `bank_transfer` (manual processor) when no completed payment exists

### Step 4: Gateway-First (Digipay) or Transaction Commit

For **Digipay** with immediate completion and `skip_gateway = false`:
- Call `$processor->process()` before the DB transaction
- On success: commit everything in one transaction
- On failure: `RefundGatewayException` propagates — nothing saved

For **Wallet** / **Manual** / **skip_gateway**:
- Everything committed in a single `DB::transaction()`
- Wallet: credits balance inside the transaction
- Manual: logs inside the transaction

On completion:
- `OrderItem.status = REFUNDED`, `total_refunded = refundAmount`, `qty_refunded = qty_ordered`
- `OrderStatusService::updateEnrollmentStatus()` → enrollment set to `CANCELLED`
- `OrderStatusService::updateParentOrderStatus()` → order status recalculated
- `UpdateOrderRefundedAmountAction::handle()` → syncs `orders.total_refunded`
- `RefundCompletedEvent::dispatch()` → triggers notification listener

---

## Full-Order Refund (`RefundOrderAction`)

`POST /api/v1/admin/order/{order}/refund` → `OrderRefundController@store` → `RefundOrderAction@handle`

Refunds ALL refundable items in an order in one operation. This is the required path when `config('payments.digipay.allow_partial_refund')` is `false` and the order was paid via Digipay.

### Flow

1. **Collect refundable items**: excludes REFUNDED, CANCELLED, and items with existing non-FAILED refunds
2. **Calculate per-item amounts**: applies deductions (same semantics as per-item refund)
3. **Cumulative cap check** (Digipay): `alreadyRefunded + totalRefundAmount ≤ payment.amount`
4. **Gateway-first** (Digipay): call processor before DB writes
5. **Transaction**: creates one Refund per item, updates all items/enrollments/order status, dispatches events

> **Re-enrollment**: No automation. The admin must manually re-enroll the customer for items they wish to keep. This is intentional — forced full-order refund is an edge case, and automating re-enrollment for a subset of items would add complexity not worth the cost.

---

## Cumulative Refund Cap (Digipay)

The `DigipayRefundProcessor` enforces that total refunds against a payment never exceed the original payment amount:

```php
$alreadyRefunded = Refund::where('payment_id', $payment->id)
    ->where('status', RefundStatusEnum::COMPLETED)
    ->sum('amount');

if (($alreadyRefunded + $amount) > $payment->amount) {
    throw new RefundGatewayException('Total refund exceeds original payment amount.');
}
```

This runs **before the gateway call and before any DB writes**. Requires `refunds.payment_id` column.

---

## BNPL / CREDIT Delivery Guard

For payment types requiring delivery confirmation (CREDIT type 5, BNPL type 13), the `DigipayRefundProcessor` logs a warning if refund is attempted before delivery is confirmed:

```
[Digipay] Refund attempted on BNPL/CREDIT payment before delivery confirmation
```

This is a **defensive warning log only** — not a hard block. Delivery confirmation is auto-called after verification in the headless system.

`DigipayAdminService::DELIVERY_REQUIRED_TYPES` constant (now `public`) defines these types: `[5, 13]`.

---

## Refund Management Endpoints

| Endpoint | Action | Constraints |
|----------|--------|-------------|
| `GET /order-item/{id}/refund` | List refunds for item | — |
| `POST /order-item/{id}/refund` | Create refund (per-item) | Item must be refundable; Digipay partial gate applies |
| `GET /order-item/{id}/refund/{refund}` | Show refund details | — |
| `PUT /order-item/{id}/refund/{refund}` | Edit refund | Only PENDING status |
| `DELETE /order-item/{id}/refund/{refund}` | Delete refund | Only PENDING status |
| `PUT /refund/{refund}/status` | Update status | State machine rules; gateway-first on COMPLETED |
| **`POST /order/{order}/refund`** | **Full-order refund** | **New** — refunds all refundable items; `skip_gateway` gated by `refunds.skip-gateway` permission |
| `POST /payment/{payment}/digipay/refund` | Direct Digipay refund | Gateway-level retry |
| `POST /payment/digipay/inquire-refund` | Check refund status | — |
| `POST /payment/{payment}/digipay/reverse` | Instant reversal | Within 25 min window |

---

## Notifications

When a refund completes, `SendRefundCompletedNotification` (auto-discovered via `#[AsEventListener]`) sends:

| Channel | Content |
|---------|---------|
| **Mail** | Refund confirmation with order ID, item name, amount, and payment-method-specific messaging (digipay tracking code / wallet credited / bank transfer pending) |
| **SMS** | Short confirmation: "استرداد وجه سفارش #X به مبلغ Y ریال تأیید شد" |

Both channels are queued (`ShouldQueue`). The listener eager-loads `orderItem.order.customer` before notifying.

---

## Enrollment Revocation

When a refund completes, `OrderStatusService::updateEnrollmentStatus()` maps item status to enrollment status:

| OrderItem Status | Enrollment Status |
|------------------|-------------------|
| `REFUNDED` | `CANCELLED` |
| `CANCELLED` | `CANCELLED` |
| `COMPLETED` | `PENDING_PROVISIONING` |

This ensures the student loses access to the content upon refund.

---

## Order Status Aggregation

`OrderStatusService::determineOrderStatus()` computes the parent order status from its items:

| Item State | Order Status |
|------------|-------------|
| All items `REFUNDED` | `REFUNDED` |
| All items `CANCELLED` | `CANCELLED` |
| Some items `REFUNDED` | `PARTIALLY_REFUNDED` |
| All items `COMPLETED` | `COMPLETED` |
| Otherwise | `PROCESSING` |

---

## Financial Dashboard Support

| Metric | Source | Formula |
|--------|--------|---------|
| `total_paid` | `Order` accessor | Sum of completed payment amounts |
| `total_refunded` | `Order.total_refunded` column | Sum of completed refund amounts (synced by `UpdateOrderRefundedAmountAction`) |
| `net_revenue` | `Order` accessor | `total_paid - total_refunded` |

All financial reports, admin dashboards, and API responses should use `net_revenue` instead of `total_paid` for accurate revenue figures.

---

## Payment Amount Determinism

All payment amounts are computed, never manually entered:

| Context | Amount | Source |
|---------|--------|--------|
| Shop checkout (initial) | `grand_total` | `CreateOrderFromCartAction::processPayment()` |
| Admin first payment | `items.sum('total')` | `CreatePaymentAction::calculateRequiredPayment()` |
| Shop retry | `balance_due` | `RetryOrderPaymentAction::handle()` |
| Admin subsequent | `balance_due` | `CreatePaymentAction::calculateRequiredPayment()` |

Since `grand_total = sum(items.total)`, all paths produce the same first-payment amount. Subsequent payments always cover the remaining `balance_due`.

---

## Permission Model

| Permission | Enum Value | Controls |
|------------|-----------|----------|
| `REFUND_VIEW_ANY` | `refunds.view_any` | List refunds |
| `REFUND_VIEW` | `refunds.view` | View single refund |
| `REFUND_CREATE` | `refunds.create` | Create refund (per-item or full-order) |
| `REFUND_UPDATE` | `refunds.update` | Edit PENDING refund details |
| `REFUND_DELETE` | `refunds.delete` | Delete PENDING refund |
| `REFUND_UPDATE_STATUS` | `refunds.update_status` | Transition refund status |
| **`REFUND_SKIP_GATEWAY`** | **`refunds.skip_gateway`** | **Bypass payment gateway (new)** |

---

## Configuration

```env
# config/payments.php → payments.digipay.allow_partial_refund
# Default: false (safe). Set to true to enable per-item Digipay refunds.
DIGIPAY_ALLOW_PARTIAL_REFUND=false
```

---

## Exception Hierarchy

| Exception | Purpose | HTTP Code | Thrown By |
|-----------|---------|-----------|-----------|
| `RefundValidationException` | Business rule violation (already refunded, partial not supported, no refundable items) | 422 | `CreateRefundAction`, `RefundOrderAction` |
| `RefundGatewayException` | Gateway API failure (Digipay call rejected, cumulative cap exceeded) | 500 | `DigipayRefundProcessor` |
| `DigipayException` | Low-level Digipay HTTP/client error | — | `DigipayClient` (caught by `DigipayRefundProcessor`, re-thrown as `RefundGatewayException`) |
| `ValidationException` (Laravel) | Input validation failures | 422 | DTO validation, state machine |

# Refund System Architecture

## Overview

The refund system allows admins to reverse an `OrderItem` purchase — returning money, revoking enrollment access, and updating order status. Refunds follow a state machine with strict transitions and optional deduction support.

---

## Data Model

```
Order (1) ──→ (N) OrderItem (1) ──→ (N) Refund
  │               │                    │
  │               ├─ payment_type      ├─ amount
  │               ├─ total             ├─ deduction_amount
  │               ├─ total_refunded    ├─ status (RefundStatusEnum)
  │               ├─ status            ├─ transaction_details
  │               └─ enrollment        ├─ refunded_at
  │                                    └─ admin_notes
  └─→ (N) Payment (1) ──→ (N) PaymentTransaction
          │                        │
          ├─ amount                ├─ gateway_response
          ├─ method                │   └─ tracking_code
          └─ status                │   └─ payment_gateway (type)
                                   └─ gateway_request
```

### Key Fields

| Model | Field | Purpose |
|-------|-------|---------|
| `OrderItem` | `payment_type` | `PRE_PAYMENT` or `FULL_PAYMENT` |
| `OrderItem` | `total` | PRE_PAYMENT: prepaid amount. FULL_PAYMENT: full price after discounts |
| `OrderItem` | `total_refunded` | Cumulative amount refunded for this item |
| `Order` | `grand_total` | Sum of all `orderItem.total` values |
| `Order` | `full_value_grand_total` | Sum of all items' full values (ignoring prepayment discount) |
| `Order` | `balance_due` | `full_value_grand_total - total_paid` |
| `Payment` | `method` | `digipay`, `wallet`, `bank_transfer`, `mellat_gateway` |
| `PaymentTransaction` | `gateway_response` | Stores `tracking_code` and `payment_gateway` (type) from Digipay |
| `Refund` | `status` | PENDING → PROCESSING → COMPLETED / FAILED. Or PENDING → CANCELLED |

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

## Refund Creation Flow

`POST /api/v1/admin/order-item/{orderItem}/refund` → `RefundController@store` → `CreateRefundAction@handle`

### Step 1: Validation (`validateOrderItemIsRefundable`)

| # | Rule | Error Message |
|---|------|---------------|
| 1 | Order must have `total_paid > 0` | `no_completed_payments` |
| 2 | Item status must NOT be `REFUNDED` | `already_refunded` |
| 3 | Item status must NOT be `CANCELLED` | `not_allowed` |
| 4 | No existing non-FAILED refund for this item | `refund_request_exists` |

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

### Step 3: Record Creation (DB Transaction)

All wrapped in `DB::transaction()`:

1. **Create Refund record** with calculated amounts and transaction details (bank info: receiver name, card number, IBAN, tracking code)

2. **If status is `COMPLETED`** (immediate completion):
   - `OrderItem.status = REFUNDED`, `total_refunded = refundAmount`
   - `OrderStatusService::updateEnrollmentStatus()` → enrollment set to `CANCELLED`
   - `OrderStatusService::updateParentOrderStatus()` → order status recalculated:
     - All items REFUNDED → `OrderStatus::REFUNDED`
     - Some items REFUNDED → `OrderStatus::PARTIALLY_REFUNDED`
   - `processDigipayRefund()` → sends refund to Digipay gateway
   - `RefundCompletedEvent::dispatch()` → fired (currently no listeners)

### Step 4: Digipay Refund Processing

```php
private function processDigipayRefund(Order $order, int $amount): void
{
    $payment = $order->payments()->where('method', 'digipay')->latest()->first();

    if (! $payment) {
        return; // No Digipay payment — silently skip
    }

    try {
        $this->digipayService->refund($payment, $amount);
        // Logs success
    } catch (DigipayException $e) {
        // Logs error — NON-BLOCKING. Refund record already saved.
    }
}
```

**Digipay API call** (`DigipayClient::refund`):
```
POST {base}/digipay/api/refunds?type={payment_gateway_type}
Body: {
    providerId: "REFUND-{payment_id}-{timestamp}",
    amount: {refund_amount},
    saleTrackingCode: "{original_tracking_code}"
}
```

The `type` parameter comes from `gateway_response.payment_gateway` stored during payment verification:

| Type | Name | Requires Delivery Confirm |
|------|------|---------------------------|
| 0 | IPG (online payment) | No |
| 5 | CREDIT (خرید اعتباری) | **Yes** |
| 11 | Wallet (Digipay wallet) | No |
| 13 | BNPL (خرید اقساطی) | **Yes** |

---

## Refund Management Endpoints

| Endpoint | Action | Constraints |
|----------|--------|-------------|
| `GET /order-item/{id}/refund` | List refunds for item | — |
| `POST /order-item/{id}/refund` | Create refund | Item must be refundable |
| `GET /order-item/{id}/refund/{refund}` | Show refund details | — |
| `PUT /order-item/{id}/refund/{refund}` | Edit refund | Only PENDING status |
| `DELETE /order-item/{id}/refund/{refund}` | Delete refund | Only PENDING status |
| `PUT /refund/{refund}/status` | Update status | State machine rules |
| `POST /payment/{payment}/digipay/refund` | Direct Digipay refund | Gateway-level retry |
| `POST /payment/digipay/inquire-refund` | Check refund status | — |
| `POST /payment/{payment}/digipay/reverse` | Instant reversal | Within 25 min window |

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

## Payment Amount Determinism

All payment amounts are computed, never manually entered:

| Context | Amount | Source |
|---------|--------|--------|
| Shop checkout (initial) | `grand_total` | `CreateOrderFromCartAction::processPayment()` |
| Admin first payment | `items.sum('total')` | `CreatePaymentAction::calculateRequiredPayment()` |
| Shop retry | `balance_due` | `RetryOrderPaymentAction::handle()` |
| Admin subsequent | `balance_due` | `CreatePaymentAction::calculateRequiredPayment()` |

Since `grand_total = sum(items.total)`, all paths produce the same first-payment amount. Subsequent payments always cover the remaining `balance_due`.

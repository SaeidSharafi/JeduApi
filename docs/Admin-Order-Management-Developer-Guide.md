# Admin Order Management — Developer Guide

## Scope
Admin order creation, admin payment registration, manual approval, and refund lifecycle (single-item + full-order).

## Confirmed Domain Rules (authoritative)

1. Digipay refund/delivery uses long HTTP calls. Do **not** keep long DB locks during external calls.
2. Digipay refund policy is constrained business flow (full-order path or one-time allowed item-refund flow by policy).
3. Deduction is always based on product original/main price at purchase time (not paid amount).
4. BNPL/CREDIT delivery guard is warning-oriented operationally; manual out-of-shop handling is acceptable.

---

## 1) Subsystem Flow Diagram

```mermaid
flowchart TB
    A[Admin API] --> B{Operation}

    B --> O1[CreateOrderAction]
    B --> O2[CreatePaymentAction]
    B --> O3[ApproveOrderAction]
    B --> R1[CreateRefundAction]
    B --> R2[RefundOrderAction]
    B --> R3[UpdateRefundStatusAction]

    O2 --> PPF[PaymentProcessorFactory]
    PPF --> BP[BankTransferPaymentProcessor]
    PPF --> WP[WalletPaymentProcessor]
    PPF --> DP[DigipayPaymentProcessor]

    BP --> PCE[PaymentCompletedEvent]
    WP --> PCE
    DP --> PCE
    PCE --> UPS[UpdateStatusesAfterPaymentListener]
    UPS --> OSS[OrderStatusService]
    OSS --> OSE[OrderStatusUpdatedEvent]
    OSE --> OSL[OrderStatusUpdateListener]
    OSL --> PJ[Provisioning Jobs]

    R1 --> RPF[RefundProcessorFactory]
    R2 --> RPF
    R3 --> RPF
    RPF --> DRP[DigipayRefundProcessor]
    RPF --> WRP[WalletRefundProcessor]
    RPF --> MRP[ManualRefundProcessor]

    DRP --> DG[Digipay HTTP API]
    WRP --> WL[Wallet Ledger]
    MRP --> MA[Manual Audit Notes]

    R1 --> RCE[RefundCompletedEvent]
    R2 --> RCE
    R3 --> RCE
    RCE --> RN[Refund Notification]
```

---

## 2) Key Execution Path

```mermaid
sequenceDiagram
    autonumber
    actor Admin
    participant API as Admin Controller
    participant ACT as Refund Action
    participant DB as PostgreSQL
    participant DG as Digipay Processor
    participant GW as Digipay API
    participant OSS as OrderStatusService

    Admin->>API: Submit refund request
    API->>ACT: handle(...)
    ACT->>DB: TX-1 short lock/validate/create PROCESSING claim
    DB-->>ACT: claim token/state
    ACT->>DG: process refund
    DG->>GW: long HTTP call
    GW-->>DG: result
    DG-->>ACT: tracking/failure
    ACT->>DB: TX-2 short finalize by claim token
    ACT->>OSS: update item/enrollment/order status
    ACT-->>API: response
```

---

## 3) State Transitions

```mermaid
stateDiagram-v2
    [*] --> PENDING
    PENDING --> PROCESSING
    PENDING --> COMPLETED
    PENDING --> CANCELLED
    PROCESSING --> COMPLETED
    PROCESSING --> FAILED

    COMPLETED --> [*]
    FAILED --> [*]
    CANCELLED --> [*]
```

---

## 4) Edge Case & Failure Matrix

| Scenario | Expected Behavior | Operational Handling |
|---|---|---|
| Digipay timeout/hang | No long DB lock during external call | Retry/reconcile path |
| Gateway success, DB finalize fail | Emergency signal + recoverable state | Reconciliation job/command |
| Concurrent refund-complete requests | Idempotent token ownership prevents duplicate finalize | Return current terminal state |
| Same payment concurrent item refunds | Payment-scoped guard avoids cap race | Reject second request |
| BNPL/CREDIT without delivery-confirmed | Warning-only policy retained | Admin manual follow-up |
| Deduction with discount/prepayment | Still from original product price | No runtime override |

---

## 5) Developer Guardrails (Strict)

1. Enforce nested resource invariant: `payment.order_id == route order.id`.
2. Never hold long DB lock while waiting on Digipay HTTP.
3. Make refund completion idempotent (claim token + terminal no-op).
4. Merge `transaction_details`; never overwrite with empty payload.
5. Keep reconciliation flow for external-success/internal-failure cases.

# Admin order and refund boundaries

> Supporting architecture note. For contracts and current implementation, use `docs/Digestions/`, code, configuration, and tests.

## Why this note exists

Admin flows can create or repair commercial state, but they must not bypass the same ownership, financial, and concurrency invariants used by storefront checkout.

## Code flow at a glance

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
    OSL --> PJ[Provisioning jobs]

    R1 --> RPF[RefundProcessorFactory]
    R2 --> RPF
    R3 --> RPF
    RPF --> DRP[DigipayRefundProcessor]
    RPF --> WRP[WalletRefundProcessor]
    RPF --> MRP[ManualRefundProcessor]

    DRP --> DG[Digipay API]
    WRP --> WL[Wallet ledger]
    MRP --> MA[Manual audit details]

    R1 --> RCE[RefundCompletedEvent]
    R2 --> RCE
    R3 --> RCE
    RCE --> RN[Refund notification]
```

## Invariants

- An order-scoped payment, item, or refund must belong to the order in the route. Nested binding is not an ownership check by itself.
- Status changes flow through the domain actions and the order status service. Direct model updates can skip reservation release, enrollment revocation, notifications, or parent-order aggregation.
- Refundable amounts are derived from the immutable order snapshot. Current catalog prices must never be used to reconstruct an old purchase.
- A refund may not exceed the completed amount for its payment after prior refunds. Concurrent refunds against the same payment must serialize this decision.
- Gateway policy is not interchangeable: Digipay, wallet, and manual refunds have different external and accounting consequences. Resolve the configured processor instead of branching in controllers.
- Admin approval changes fulfillment state only after the required payment threshold is met. It is not a generic override for unpaid orders.

## External refund boundary

Long-running gateway I/O must not occur while database locks are held.

```mermaid
sequenceDiagram
    participant Action
    participant DB
    participant Gateway

    Action->>DB: Short transaction: lock, validate, claim PROCESSING
    DB-->>Action: Commit claim
    Action->>Gateway: Refund request outside transaction
    Gateway-->>Action: Gateway result
    Action->>DB: Short transaction: finalize claim and statuses
```

The claim/finalize split exists because neither a database rollback nor an HTTP retry can undo a successful external refund. Finalization must be idempotent, and an external-success/internal-failure outcome must remain visible for reconciliation.

```mermaid
stateDiagram-v2
    [*] --> PENDING
    PENDING --> PROCESSING: external refund claimed
    PENDING --> COMPLETED: local/manual completion
    PENDING --> CANCELLED
    PROCESSING --> COMPLETED: gateway and local finalize succeed
    PROCESSING --> FAILED: known gateway failure
    COMPLETED --> [*]
    FAILED --> [*]
    CANCELLED --> [*]
```

## Failure behavior

| Failure | Required result |
|---|---|
| Gateway timeout before a known result | Keep a reconcilable non-terminal record; do not assume success or issue an immediate duplicate refund. |
| Gateway succeeds, local finalization fails | Preserve the processing claim and emit high-severity telemetry for reconciliation. |
| Two refund requests race | Only the owner of the valid claim may call or finalize the external refund. |
| A terminal refund is submitted again | Return the existing outcome without repeating financial side effects. |
| Refund or cancellation revokes a purchased item | Recompute enrollment and parent-order state through the shared status path. |

## Change checklist

- Test nested-resource ownership and authorization separately.
- Test concurrent and repeated refund requests.
- Test the external-success/local-failure path.
- Keep transaction metadata additive; never erase a gateway reference with an empty payload.

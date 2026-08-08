# Checkout, order, and payment boundaries

> Supporting architecture note. For contracts and current implementation, use `docs/Digestions/`, generated API documentation, code, configuration, and tests.

## Boundary model

```mermaid
flowchart LR
    Cart -->|validate and lock| Order
    Order -->|prepare attempt| Payment
    Payment -->|processor or callback verifies| CompletedPayment
    CompletedPayment -->|synchronous state cascade| OrderItems
    OrderItems --> Enrollments
    Enrollments -->|queued| Provisioning
```

The order is a commercial snapshot, the payment is an attempt to settle it, and the enrollment is the fulfillment entitlement. These records have separate lifecycles and must not be collapsed into a single transaction or status flag.

## Completion cascade at a glance

```mermaid
flowchart TD
    C[CreateOrderFromCartAction] -->|lock cart and delivery options| O[CreateOrderAction]
    O -->|reserve seats + snapshot totals| DB[(Order and items)]
    DB --> PP[PreparePendingPaymentAction]
    PP --> PF[PaymentProcessorFactory]

    PF --> WP[WalletPaymentProcessor]
    PF --> BP[BankTransferPaymentProcessor]
    PF --> MP[MellatGatewayPaymentProcessor]
    PF --> DP[DigipayPaymentProcessor]

    MP -->|redirect then callback| VP[VerifyPaymentAction]
    DP -->|redirect then callback| VP
    WP --> PCE[PaymentCompletedEvent]
    BP --> PCE
    VP --> PCE

    PCE --> US[UpdateStatusesAfterPaymentListener]
    US -->|wallet top-up| TW[TopupWalletAction]
    US -->|order payment| OS[OrderStatusService]
    OS --> RS[Consume reservation]
    OS --> EI[Complete item + create enrollment]
    OS --> OU[OrderStatusUpdatedEvent]
    OU --> OSL[Queued OrderStatusUpdateListener]
    OSL --> PJ[Provider provisioning jobs]

    EI --> ES[EnrollmentStatusChanged]
    ES --> EC[UpdateProductDeliveryOptionEnrolledCount after commit]
    EC --> AV[Update availability projection]
```

## Checkout invariants

- A cart is mutable and does not reserve inventory. Order creation revalidates publication, visibility, registration and availability windows, duplicate ownership, quantity rules, and capacity.
- Limited capacity is protected by locking the delivery option and checking `enrolled_count + reserved_count + requested quantity`. Creating a pending order reserves seats; payment consumes the reservation; cancellation or abandonment releases it.
- Prices and applied discounts are copied into immutable order/item snapshots. Later catalog or promotion changes must not rewrite historical totals.
- Ownership is checked against the underlying productable, not only a delivery-option ID, so changing packaging does not allow duplicate entitlement.
- Order creation and seat reservation are one database boundary. Network payment processing begins only after that boundary commits.

## Payment invariants

- Every attempt is represented by a payment before a processor runs. The payment purpose distinguishes order settlement from wallet top-up; an order is not valid ownership for every payment.
- Wallet and free payments can complete synchronously. Redirect gateways remain pending until a verified callback.
- Verification locks the payment and only transitions a valid pending attempt. Repeated callbacks must not create a second completion cascade.
- The gateway callback payload is evidence, not proof. A processor must verify reference, amount, ownership, and gateway result according to its protocol.
- External gateway calls do not belong inside long database transactions.

## Completion and fulfillment

Payment completion first routes by payment purpose. Order payments update item, reservation, enrollment, and parent-order state synchronously; provisioning is queued only after the order reaches its configured fulfillment boundary. Wallet top-ups credit the ledger through their own idempotent path.

The provisioning trigger is a business configuration (`any_payment`, `full_payment`, or manual approval). Do not encode one trigger assumption in a controller, processor, or provider job.

```mermaid
stateDiagram-v2
    [*] --> pending: order created and seats reserved
    pending --> processing: payment started or trigger not met
    pending --> cancelled: customer or abandoned-order cancellation
    processing --> completed: all items completed
    completed --> partially_refunded: some items refunded
    completed --> refunded: all items refunded
    pending --> failed: terminal payment failure
    processing --> failed: terminal payment failure
```

```mermaid
stateDiagram-v2
    [*] --> active: local enrollment created
    active --> pending_provisioning: provider work required
    pending_provisioning --> active: all required providers succeed
    pending_provisioning --> provisioning_failed: provider terminal failure
    provisioning_failed --> pending_provisioning: admin retry
    active --> suspended: admin action
    active --> expired: access window ends
    active --> cancelled: cancellation or refund
    pending_provisioning --> cancelled: cancellation or refund
```

## State consequences

| Event | Required consequences |
|---|---|
| Pending order created | Snapshot totals and reserve limited capacity. |
| Payment verified | Complete the payment once, then run the purpose-specific cascade. |
| Item completed | Consume its reservation and create/update its enrollment. |
| Order cancelled or abandoned | Release outstanding reservations and cancel affected entitlements. |
| Item refunded | Revoke its entitlement and recompute the parent order. |
| Provisioning fails | Keep the commercial payment complete; expose fulfillment failure for retry. |

## Change checklist

- Test the last-seat race and reservation release.
- Test duplicate callback delivery and amount/reference mismatch.
- Test free, wallet, and redirect-gateway completion through the same state consequences.
- Test each provisioning-trigger configuration when changing order completion.
- Never calculate historical financial values from current catalog rows.

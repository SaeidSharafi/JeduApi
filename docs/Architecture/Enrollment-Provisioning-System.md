# Enrollment provisioning boundaries

> Supporting architecture note. For contracts and current implementation, use `docs/Digestions/`, code, integration configuration, and tests.

## Domain boundary

Payment grants a local entitlement; provisioning mirrors that entitlement into external systems. A provider outage must not roll back a completed commercial transaction.

```mermaid
flowchart TD
    P[PaymentCompletedEvent] --> OS[OrderStatusService]
    OS --> E[Local enrollment ACTIVE]
    OS --> OU[OrderStatusUpdatedEvent]
    OU --> L[Queued OrderStatusUpdateListener]

    L -->|per planned provider| ATT[ProvisioningAttemptService::queue]
    ATT --> JOB[ProvisionEnrollmentProviderJob]
    JOB --> REG[ProvisioningProviderRegistry]
    REG --> ADAPTER[Provider adapter]
    ADAPTER --> RESULT[Provider-scoped canonical references]
    RESULT --> AGG{Aggregate provisioning_status}
    AGG -->|all healthy| HEALTHY[HEALTHY]
    AGG -->|unready provider| MANUAL[MANUAL_ACTION_REQUIRED]
    AGG -->|failed provider| DEGRADED[DEGRADED]
```

```mermaid
stateDiagram-v2
    [*] --> awaiting_payment
    awaiting_payment --> active: payment/approval trigger met
    active --> suspended: admin action
    suspended --> active: admin action
    active --> expired: access window ends
    active --> cancelled: cancellation or refund
    awaiting_payment --> cancelled: cancellation or refund
```

## Invariants

- Enrollment creation and local status follow order-item state. Provider jobs do not decide whether an item was paid or refunded.
- Payment grants the local entitlement immediately (`ACTIVE`). Provisioning readiness is owned by the separate `provisioning_status` aggregate plus per-provider outcome status; the lifecycle status never carries provisioning-pending or provisioning-failed states.
- A delivery option can require more than one provider, such as a primary delivery platform plus IMS or a separate Moodle quiz. Success is tracked per provider.
- External identifiers and access data are provider-scoped. Do not store one provider's result in another provider's slot. Global external enrollment identifiers are removed.
- Recoverable failures use queue retries and backoff. Invalid configuration or rejected input fails immediately and remains visible for an admin retry after correction.
- Manual retry targets failed providers. If provisioning was never attempted, it may dispatch the full required set.
- Cancellation/refund revokes the local entitlement even when external revocation needs separate operational recovery.
- Shop and customer access views consume only safe canonical provider results (e.g. `delivery_access.is_ready` derived from provider outcome status); they never null-check raw URL fields.

## Failure behavior

| Failure | Required result |
|---|---|
| Provider is temporarily unavailable | Retry without duplicating an already-created external entitlement. |
| Provider rejects configuration/data | Mark that provider failed and stop automatic retry churn. |
| One of several providers fails | Keep successful provider evidence and mark the aggregate degraded. |
| Queue dispatch never happened | Admin retry can reconstruct the required provider set. |
| Retry is requested for an active/cancelled enrollment | Reject it; retry is a recovery tool, not a status override. |

## Integration adapter contract

Adapters translate provider protocols and classify failures; the generic job owns retry behavior and persistence of provisioning outcome through `ProvisioningAttemptService`. Keep credentials in the settings/configuration layer, redact them from logs, and never return them in enrollment payloads.

## Change checklist

- Test repeated execution against a provider that already contains the user or entitlement.
- Test mixed success/failure for multi-provider delivery options.
- Test configuration-disabled and configuration-invalid cases separately.
- Preserve provider result history when retrying.
- Test that lifecycle status stays ACTIVE (local entitlement) while provisioning health degrades or requires manual action.

# Wallet boundaries

> Supporting architecture note. For contracts and current implementation, use `docs/Digestions/`, code, schema, and tests.

## Ledger model

Wallet balances are cached totals backed by an append-only transaction history. Every financial mutation goes through the shared transaction action; callers describe the source and intent but do not update balances directly.

```mermaid
flowchart TB
    A[TopupWalletAction] -->|wallet-topup:payment id| R[RecordWalletTransactionAction]
    B[WalletRefundProcessor] -->|wallet-refund:refund id| R
    C[TriggerCampaignAllocationAction] -->|deterministic campaign key| R
    D[WalletPaymentProcessor] -->|order payment| R
    E[Admin deposit/withdraw/adjust] --> R

    R --> TX[Database transaction]
    TX --> L[Lock wallet row FOR UPDATE]
    L --> I{Idempotency key already exists?}
    I -->|yes| EXISTING[Return existing ledger entry]
    I -->|no| S{Wallet status permits type?}
    S -->|no| DENY[Reject]
    S -->|yes| SIGN[Normalize credit/debit sign]
    SIGN --> SPLIT[Calculate normal + gift balance split]
    SPLIT --> FUNDS{Sufficient funds?}
    FUNDS -->|no| INSUFFICIENT[Rollback with structured shortfall]
    FUNDS -->|yes| UPDATE[Update cached balances]
    UPDATE --> APPEND[Append wallet transaction + audit metadata]
```

```mermaid
sequenceDiagram
    autonumber
    participant Caller
    participant Action as RecordWalletTransactionAction
    participant Wallet
    participant Ledger as wallet_transactions

    Caller->>Action: execute transaction intent
    Action->>Wallet: lock row
    Action->>Ledger: find deterministic idempotency key
    alt already processed
        Ledger-->>Caller: existing transaction
    else first execution
        Action->>Action: status, sign, split, and funds checks
        Action->>Wallet: update normal/gift balances
        Action->>Ledger: append entry in same transaction
        Ledger-->>Caller: new transaction
    end
```

```mermaid
stateDiagram-v2
    [*] --> ACTIVE
    ACTIVE --> SUSPENDED
    SUSPENDED --> ACTIVE
    ACTIVE --> CLOSED
    SUSPENDED --> CLOSED

    state "Transaction policy" as Policy {
        [*] --> Evaluate
        Evaluate --> Allowed: ACTIVE
        Evaluate --> Allowed: SUSPENDED and REFUND
        Evaluate --> Rejected: SUSPENDED and non-refund
        Evaluate --> Rejected: CLOSED
    }
```

## Invariants

- Lock the wallet row before checking funds and writing both the balance and ledger entry.
- Replayable operations carry deterministic idempotency keys derived from stable business identity, such as a payment, refund, or campaign allocation. Random request IDs do not provide financial idempotency.
- An existing idempotency key returns the existing transaction and performs no second balance change.
- Debits normalize to negative amounts and credits to positive amounts inside the transaction boundary.
- Order payment spends normal balance before gift balance. The split is recorded for audit and later financial reasoning.
- A suspended wallet accepts refunds only; a closed wallet accepts no transactions. Callers do not add local status bypasses.
- A wallet top-up is a payment with wallet-top-up purpose and customer ownership, not a dummy order. Payment completion routes to the ledger by purpose.
- Refunds restore funds through the same ledger action; they never edit the original transaction or balance directly.

## Failure behavior

| Failure | Required result |
|---|---|
| Concurrent debits | Row lock serializes the available-balance check; at most affordable debits succeed. |
| Repeated callback or job | Deterministic key returns the prior ledger entry. |
| Ledger insert fails | Balance update rolls back in the same database transaction. |
| Insufficient combined funds | No partial debit; return structured available/required/shortfall data. |
| Top-up payment belongs to another user | Reject before crediting any wallet. |

## Change checklist

- Test concurrency and idempotency, not only sequential happy paths.
- Preserve normal/gift split metadata for order debits.
- Keep source type and source ID stable enough for reconciliation.
- Never repair balances with a direct database update; use an explicit adjustment transaction.

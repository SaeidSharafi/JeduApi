# Wallet Subsystem Architecture Reference

## 1) Subsystem Flow Diagram

```mermaid
flowchart TB
    A[TopupWalletAction] -->|"build RecordTransactionData + idempotency_key wallet-topup:{payment_id}"| R[RecordWalletTransactionAction]
    B[WalletRefundProcessor] -->|"build RecordTransactionData + idempotency_key wallet-refund:{refund_id}"| R
    C[TriggerCampaignAllocationAction] -->|lock campaign + generate deterministic idempotency key| R
    D[WalletPaymentProcessor] -->|build RecordTransactionData for ORDER payment| R

    R --> E[DB transaction]
    E --> F[lock wallet row FOR UPDATE]
    F --> G{Idempotency key exists?}
    G -->|yes| H[return existing WalletTransaction]
    G -->|no| I{Wallet status allows tx type?}
    I -->|no| X[throw WalletNotActive]
    I -->|yes| J[normalize amount sign]
    J --> K[compute new balance + gift_balance]
    K --> L{insufficient funds?}
    L -->|yes| Y[throw WalletInsufficientBalanceException]
    L -->|no| M[update wallet balances]
    M --> N[create wallet_transactions row + audit metadata]
    N --> O[return WalletTransaction]

    C --> P[increment campaign usage + dispatch event]
```

## 2) Key Execution Path

```mermaid
sequenceDiagram
    autonumber
    participant Caller as Topup/Campaign/Refund/Payment Flow
    participant Action as RecordWalletTransactionAction
    participant DB as Database
    participant WT as wallet_transactions
    participant W as wallets

    Caller->>Action: execute(RecordTransactionData)
    Action->>DB: BEGIN TRANSACTION
    Action->>W: SELECT ... FOR UPDATE (wallet row)
    Action->>WT: SELECT by idempotency_key (if provided)
    alt existing transaction found
        WT-->>Action: existing row
        Action->>DB: COMMIT
        Action-->>Caller: existing WalletTransaction
    else first execution
        Action->>Action: status gate + amount normalization
        Action->>Action: balance math (incl ORDER debit split)
        Action->>W: UPDATE balance, gift_balance
        Action->>WT: INSERT transaction (audit metadata + idempotency_key)
        Action->>DB: COMMIT
        Action-->>Caller: new WalletTransaction
    end
```

## 3) State Transitions

```mermaid
stateDiagram-v2
    [*] --> ACTIVE
    ACTIVE --> SUSPENDED
    SUSPENDED --> ACTIVE
    ACTIVE --> CLOSED
    SUSPENDED --> CLOSED

    state "Transaction Authorization" as TA {
      [*] --> Evaluate
      Evaluate --> Allowed: status=ACTIVE && any type
      Evaluate --> Allowed: status=SUSPENDED && type=REFUND
      Evaluate --> Rejected: status=CLOSED
      Evaluate --> Rejected: status=SUSPENDED && type!=REFUND
    }
```

## 4) Edge Case and Failure Matrix

| Case | Trigger | Current Handling | Result |
|---|---|---|---|
| Replay of same topup/refund/campaign request | Same deterministic `idempotency_key` | Query existing by key before write | Returns prior row, no double credit/debit |
| Concurrent writes to same wallet | Multiple requests in parallel | `DB::transaction` + wallet `lockForUpdate()` | Serialized balance updates |
| Duplicate campaign allocation race | Parallel campaign trigger | Campaign row lock + duplicate checks + idempotency key | Single allocation |
| Wallet suspended | Non-refund tx attempted | `canProcessTransactionForStatus()` | `WalletNotActive` thrown |
| Wallet closed | Any tx attempted | `canProcessTransactionForStatus()` | `WalletNotActive` thrown |
| Insufficient balance for payment/debit | Debit exceeds available funds | Explicit available/shortfall check | `WalletInsufficientBalanceException` |
| ORDER payment split across normal + gift balance | `PAYMENT` + `source_type=ORDER` | Split math (`from_balance`, `from_gift_balance`) | Correct dual-bucket debit |
| MySQL migration compatibility | PG-only partial index syntax | Conditional execute only on `pgsql` | Migration does not fail on MySQL |
| Missing user/wallet | Invalid input or orphan state | Explicit user/wallet existence checks | Domain exceptions thrown |

## 5) Developer Guardrails (Strict Do and Don\'t)

1. **Do** route all balance mutations through `RecordWalletTransactionAction`. **Don\'t** update `wallets.balance` directly in random services/controllers.
2. **Do** provide deterministic `idempotency_key` for replayable external events (payment webhook, campaign trigger, refund). **Don\'t** use random/non-repeatable keys.
3. **Do** keep `DB::transaction` + `lockForUpdate()` boundaries intact. **Don\'t** move reads/writes outside lock scope.
4. **Do** preserve wallet status policy (`active=all`, `suspended=refund-only`, `closed=none`). **Don\'t** add bypass logic per caller path.
5. **Do** keep migration/database changes cross-DB safe (pgsql-specific SQL behind driver checks) and test with Pest idempotency cases. **Don\'t** introduce raw PG-only SQL unguarded or skip regression tests.

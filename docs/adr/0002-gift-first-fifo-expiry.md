# Gift balance is spent first and reclaimed on expiry

A wallet holds a normal balance and a gift balance. Order payments consume **gift balance first** (oldest gift first), then normal balance; each gift carries an `expires_at` and a `remaining_amount`, and a daily job reclaims unspent expired gift as an `EXPIRY` transaction. This is a deliberate flip from the earlier "normal balance first" rule recorded in `docs/Architecture/Wallet.md`.

**Why**: expiring promotional funds must be spent before the customer's own money — the standard across wallet/credit products (promo/referral/refund credits are consumed before top-up funds, oldest first within each). FIFO is what makes "expire N days from receipt" computable when gifts stack.

**Consequences**: `RecordWalletTransactionAction`'s debit split changes (gift FIFO first, then normal); the gift ledger gains per-transaction `remaining_amount` tracking; `docs/Architecture/Wallet.md` invariant must be updated at implementation time.

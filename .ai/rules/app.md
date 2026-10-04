---
paths:
  - 'app/**/{*Payment*,*Refund*}.php'
  - 'app/**/{*Order*,*Cart*,*Checkout*}.php'
  - 'app/**/{*Provision*,*Enrollment*,*Revok*,OrderStatusUpdateListener}.php'
  - 'app/**/{*Wallet*,*Gift*,CampaignEventSubscriber,*CampaignAllocation*,*ThresholdReward*}.php'
---

# App

## Read payment intent before behavior changes
When changing payment initiation, verification/callbacks, retries, transaction references, or refunds, read only applicable headings in docs/Digestions/DIGEST_CORE_LOGIC.md: Gateway Verification Gatekeeper; Payment Actions; affected payment/refund processor; PaymentTransactionReferenceService. Status propagation also requires OrderStatusService and UpdateStatusesAfterPaymentListener. Follow OrderStatusUpdateListener only if provisioning triggers change. Read before changing behavior; reuse sections already read. Formatting or behavior-preserving edits need no digest preload.

## Read checkout intent before behavior changes
When changing checkout eligibility, capacity, reservations, ownership checks, order pricing/snapshots, or cancellation, read only applicable headings in docs/Digestions/DIGEST_CORE_LOGIC.md: Checkout & Payment Actions; Capacity & Concurrency at Checkout; Duplicate Ownership Across Options; Discounts Evaluation & Counters; Price Field Invariant on Order Items; Bundle Pricing Invariants; Discount Snapshots on Orders; ProductReservationService; OrderStatusService. Read before changing behavior; reuse sections already read. Routine formatting or read-only presentation changes need no invariant preload.

## Read provisioning intent before lifecycle changes
When changing enrollment creation/status, provisioning plans, attempts, retries, recovery, provider execution, or revocation, read only applicable headings in docs/Digestions/DIGEST_CORE_LOGIC.md: Provisioning Jobs; Provisioning Provider Adapters; Provisioning Orchestration / OrderStatusUpdateListener; Enrollment Revocation; Admin Enrollment Actions. Read corresponding provider entry under Integration Services when provider behavior changes. Read before changing behavior; reuse sections already read. Formatting or read-only presentation changes need no lifecycle preload.

## Read wallet intent before ledger or campaign changes
When changing wallet debits/credits, idempotency, gift consumption/expiry, campaign eligibility/limits, or event allocation, read only applicable headings in docs/Digestions/DIGEST_CORE_LOGIC.md: Wallet Actions (app/Actions/Wallet/); WalletCampaign Actions; Wallet Campaign Event Dispatch; ReclaimExpiredGiftsCommand. Wallet checkout also requires WalletPaymentProcessor and Wallet Insufficient Balance Flow. Read before changing behavior; reuse sections already read. Formatting or read-only presentation changes need no ledger preload.

## Provisioning section lookup
The admin enrollment heading is Enrollment Actions (app/Actions/Admin/Enrollment/). Plan applicability/readiness changes also require Enrollment Provisioning Plan (app/Services/Enrollment/ProvisioningPlanResolver.php). These are section pointers, not additional full-document reads.

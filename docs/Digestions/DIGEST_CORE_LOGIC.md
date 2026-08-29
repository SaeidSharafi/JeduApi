# Digest: Core Business Logic (Actions/Services)

## Actions Pattern (`app/Actions/`)

### E2E Actions (`app/Actions/Testing/`)

#### ResetE2eEnvironmentAction (`app/Actions/Testing/ResetE2eEnvironmentAction.php`)
- **Purpose:** Rebuilds the isolated E2E database and returns fresh bootstrap identities for black-box tests.
- **Concurrency:** Acquires the distributed `e2e:database-reset` cache lock for five minutes, marks the E2E application as resetting, drains active jobs, and prevents new HTTP/queue work until cleanup is complete; returns `null` when another reset already owns the lock.
- **Functionality:** Terminates E2E Horizon workers, flushes the dedicated E2E Redis queue/cache databases, clears the dedicated E2E media disk, runs `migrate:fresh`, synchronizes staff/user permissions, creates one super-admin staff identity and one complete customer identity, issues Sanctum tokens for both, and waits for a worker heartbeat before returning.
- **Failures:** Cleanup and worker readiness failures are logged with the reset ID and raised as `E2eResetFailedException`; the API exposes only the stable `E2E_RESET_FAILED` code and correlation ID.
- **Output:** Returns a unique `reset_id`, `readiness: ready`, and each bootstrap identity's ID, email, phone, password, and token.

#### SimulatorPaymentProcessor (`app/Services/Payment/SimulatorPaymentProcessor.php`)
- **Purpose:** Provides the browser-facing gateway boundary used by black-box E2E payment scenarios.
- **Availability:** Registered and advertised only when `APP_ENV=e2e` and `PAYMENT_SIMULATOR_ENABLED=true`; direct production use fails closed.
- **Initiation:** Creates one `PaymentTransaction`, sends the exact order/payment references, amount, callback URL, optional `delay_seconds` in the range 0–15, and an HMAC-SHA256 signature to the standalone simulator.
- **Verification:** Validates the signed callback's references, amount, and `success`/`failure` outcome before making a terminal transition. Terminal transactions and repeated callbacks are idempotent; failed payments remain retryable and retries create a new `Payment` for the same `Order`.

### Admin Actions (`app/Actions/Admin/`)

#### Utility Actions
- **GetThumbnailUrlAction** (`app/Actions/Admin/GetThumbnailUrlAction.php`)
  - `handle(array $media): ?string`: Extracts the first cover-tagged media ID and resolves its CDN URL through Mediable. Centralized helper for admin### Jobs (`app/Jobs/`)

#### UpdateProductPricingJob (`app/Jobs/UpdateProductPricingJob.php`)
- **Purpose:** Asynchronous batch pricing index update for products
- **Signature:** `handle(ProductPriceService $priceService): void`
- **Functionality:** Accepts array of product IDs, recalculates pricing data via `ProductPriceService`, upserts to `product_prices` table, updates `price_data_cache` JSON column on products
- **Dispatch:** Triggered by `ProductCacheInvalidated` event listener, admin pricing changes, and scheduled commands
- **Performance:** Processes products in batches to avoid memory exhaustion

#### UpdateProductAvailabilityJob (`app/Jobs/UpdateProductAvailabilityJob.php`)
- **Purpose:** Recomputes denormalized availability snapshots for a batch of products
- **Signature:** `handle(?CacheInvalidationService $cacheInvalidationService): void`
- **Functionality:** Loads products with published delivery options, productable, and term; computes the snapshot columns (`has_published_delivery_option`, `productable_status`, `is_term_active`, `earliest/latest` registration & availability window boundaries, `near_capacity`, `max_capacity_utilization`) where capacity utilization counts committed seats (`enrolled_count + reserved_count`) against `config('products.availability.capacity_threshold', 0.8)`; persists only changed rows via `saveQuietly()`
- **Side effects:** Invalidates product cache patterns and dispatches `ProductSearchIndexInvalidated` for products whose snapshot changed
- **Dispatch:** From `IndexAllProductAvailabilityCommand`, and triggered by availability-affecting mutations (term/productable status flips)

#### SynchronizeProductSearchIndexJob (`app/Jobs/SynchronizeProductSearchIndexJob.php`)
- **Purpose:** Syncs the search engine index (Typesense) for a batch of products
- **Signature:** `handle(): void`
- **Functionality:** Loads products with productable, category slugs, price, delivery options, and term; splits into searchable (`shouldBeSearchable()` true → `searchableUsing()->update()`) and unsearchable (→ `searchableUsing()->delete()`) sets
- **Dispatch:** Listens on `ProductSearchIndexInvalidated` events

### Console Commands (`app/Console/Commands/`)

#### ReclaimExpiredGiftsCommand (`app/Console/Commands/Wallet/ReclaimExpiredGiftsCommand.php`)
- **Purpose:** Reclaims unspent gift balance that has passed its expiry date
- **Signature:** `wallet:reclaim-expired-gifts {--dry-run}`
- **Functionality:** Invokes `ReclaimExpiredGiftsAction`; prints the number of reclaimed gifts or "No expired gift balances found." `--dry-run` reports what would be reclaimed without writing.
- **Usage:** Scheduled daily (`->daily()->withoutOverlapping()`) in `bootstrap/app.php`.

#### PublishPostCommand (`app/Console/Commands/PublishPostCommand.php`)
- **Purpose:** Automated blog post publication for scheduled content
- **Signature:** `post:publish`
- **Functionality:** Publishes blog posts with SCHEDULED status where `published_at` date has passed, updating status to PUBLISHED
- **Usage:** Intended for cron job scheduling to automate content publication workflow

#### CheckStuckPaymentsCommand (`app/Console/Commands/CheckStuckPaymentsCommand.php`)
- **Purpose:** Detects and auto-fails payments stuck in PENDING state with initiated but uncompleted transactions beyond threshold
- **Signature:** `payments:check-stuck {--threshold=30}`
- **Functionality:** Queries payments where latest transaction is `INITIATED` longer than threshold minutes ago without `completed_at`. Logs warnings with payment ID, order info, transaction reference, and stuck duration. Automatically transitions stuck payment status to `FAILED`.
- **Usage:** Intended for cron scheduling (e.g., every 15 minutes) to clean up abandoned gateway payments.

#### IndexAllProductPricesCommand (`app/Console/Commands/IndexAllProductPricesCommand.php`)
- **Purpose:** Batch re-index or initialize pricing index for products
- **Signature:** `prices:index-all {--queue=default} {--sync} {--missing-only}`
- **Functionality:** Dispatches `UpdateProductPricingJob` for published products in chunks of 200; supports sync execution, custom queue, and missing-only mode for initial setup
- **Options:**
  - `--missing-only`: Only process products without price index entries (useful for initial setup)
  - `--sync`: Run jobs synchronously for immediate updates
  - `--queue`: Specify queue name for job dispatch
- **Locking:** Uses distributed lock `price-indexing` with 30-minute timeout to prevent concurrent executions
- **Usage:** `php artisan prices:index-all --missing-only` for first run, `php artisan prices:index-all` for full refresh

#### IndexAllProductAvailabilityCommand (`app/Console/Commands/IndexAllProductAvailabilityCommand.php`)
- **Purpose:** Batch recompute denormalized availability snapshots for all products
- **Signature:** `products:index-availability {--queue=default} {--sync}`
- **Functionality:** Dispatches `UpdateProductAvailabilityJob` for product IDs in chunks of 200; supports sync execution and custom queue
- **Locking:** Uses distributed lock `product-availability-indexing` with 60-minute timeout to prevent concurrent runs
- **Usage:** Run after deployment or when availability snapshots need full refresh

#### CancelAbandonedOrdersCommand (`app/Console/Commands/CancelAbandonedOrdersCommand.php`)
- **Purpose:** Cancels abandoned pending orders that never received any payment attempt
- **Signature:** `orders:cancel-abandoned {--timeout=30} {--dry-run}`
- **Functionality:** Selects PENDING orders older than `--timeout` minutes with no payment records at all (orders with failed/pending attempts are excluded so users can retry). For each order, within a DB transaction: sets status to CANCELLED, releases reserved seats via `ProductReservationService::release()` per item, cancels AWAITING_PAYMENT enrollments, and dispatches `OrderStatusUpdatedEvent`.
- **Options:**
  - `--timeout`: Minutes after which a pending order is considered abandoned (default 30)
  - `--dry-run`: Show orders that would be cancelled without making changes
- **Usage:** Intended for cron scheduling (e.g., every 30 minutes) to free capacity held by abandoned checkouts.

#### EncryptSettingSecretsCommand (`app/Console/Commands/EncryptSettingSecretsCommand.php`)
- **Signature:** `settings:encrypt-secrets {key?} {--dry-run}`
- **Purpose:** Batch encryption of integration setting secret fields at rest. Scans all `Setting` records matching integration `SettingKeyEnum` values, encrypts any plaintext secret fields using `Crypt::encryptString()`. Supports targeting a specific setting key and dry-run mode for previewing which secrets would be encrypted.

#### CheckExpiredFeaturedPricesCommand (`app/Console/Commands/CheckExpiredFeaturedPricesCommand.php`)
- **Purpose:** Automated cleanup of expired featured prices with price index synchronization
- **Signature:** `prices:check-expired-featured {--dry-run} {--queue=default}`
- **Functionality:** Finds delivery options with expired `featured_price_end_date`, dispatches `UpdateProductPricingJob` for affected products to recalculate index
- **Options:**
  - `--dry-run`: Preview affected products without making changes
  - `--queue`: Specify queue for job dispatch
- **Locking:** Uses same distributed lock as index command to prevent conflicts
- **Usage:** Intended for scheduled task (e.g., daily at midnight) to automatically update pricing when featured prices expireses that need lightweight thumbnail references without hydrating full media relations.

#### Order Actions (`app/Actions/Admin/Order/`)
  - `handle(OrderCreateData $data): Order`: Delegates all totals to `OrderCalculationService`, locks delivery options while validating requested payment types/quantities against live capacity (`enrolled_count + reserved_count`), **validates registration window (`registration_start_date`/`registration_end_date`) and availability window (`available_from`/`available_to`)**, reserves capacity via `ProductReservationService::reserve()` for each item, snapshots product data per item, increments promotion usage counts when coupon-driven contexts are present, and populates `pricing_metadata` JSON on each order item via `ProductPriceService::getPriceDataForOption()`. The `pricing_metadata` stores `{original_price, discount_type, discount_amount, discount_percentage}` — with zero discount values for `PRE_PAYMENT` items. The `price` field on order items is always set to `product_delivery_option.price` (base price) without any discounts applied. Enrollments are not created by this action — they are created by `OrderStatusService` after payment completion.
  - `handle(OrderUpdateData $data, Order $order): Order`: Updates existing order details and status
  - `handle(Order $order): void`: Handles order deletion and cleanup
- **ApproveOrderAction** (`app/Actions/Admin/Order/ApproveOrderAction.php`)
  - `handle(Order $order): Order`: Manually approves order for fulfillment/provisioning. Wraps the flow in `SmartCache::lock("approve_order_{$order->id}", 15)->block(5, ...)` to prevent concurrent approvals. Validates: order not already completed/cancelled/refunded, sufficient payment coverage (considering prepayment amounts per item). The external Digipay `deliver` call runs outside the DB transaction; on failure the order does not reach COMPLETED. Transactionally marks order as COMPLETED, completes each item, triggers enrollment provisioning via `OrderStatusService`, and consumes product reservations via `ProductReservationService`. Permission-gated via `OrderPolicy::approve()` using `PermissionEnum::ORDER_APPROVE`.

#### Payment Actions (`app/Actions/Admin/Payment/`)
- **CreatePaymentAction** (`app/Actions/Admin/Payment/CreatePaymentAction.php`)
  - `handle(PaymentCreateData $data): Payment`: Processes payment applications to orders. For free orders (grand total 0) delegates to `CompleteFreeOrderPaymentAction`.
- **UpdatePaymentAction** (`app/Actions/Admin/Payment/UpdatePaymentAction.php`)
  - `handle(PaymentUpdateData $data, Payment $payment): Payment`: Handles payment status updates and cascading effects
- **DeletePaymentAction** (`app/Actions/Admin/Payment/DeletePaymentAction.php`)
  - `handle(Payment $payment): void`: Removes payment records
- **GetNextPaymentDetailsAction** (`app/Actions/Admin/Payment/GetNextPaymentDetailsAction.php`)
  - `handle(Order $order): NextPaymentDetailsData`: Calculates next payment requirements for orders

#### Product Actions (`app/Actions/Admin/Product/`)
- **CreateProductAction** (`app/Actions/Admin/Product/CreateProductAction.php`)
  - `handle(ProductCreateData $data): Product`: Creates new sellable products with polymorphic relationships
- **UpdateProductAction** (`app/Actions/Admin/Product/UpdateProductAction.php`)
  - `handle(ProductUpdateData $data, Product $product): Product`: Updates product details and delivery options
- **DeleteProductAction** (`app/Actions/Admin/Product/DeleteProductAction.php`)
  - `handle(Product $product): void`: Handles product deletion and archival

#### RelatedProduct Actions (`app/Actions/Admin/RelatedProduct/`)
- **CreateRelatedProductAction** (`app/Actions/Admin/RelatedProduct/CreateRelatedProductAction.php`)
  - `handle(Product $product, RelatedProductSyncData $data): void`: Syncs related products for a specific relation type by replacing all existing relations of that type; validates that a product cannot be related to itself; performs transactional delete and bulk attach operations
- **DeleteRelatedProductAction** (`app/Actions/Admin/RelatedProduct/DeleteRelatedProductAction.php`)
  - `handle(Product $product, RelationTypeEnum $relationType, ?Product $relatedProduct = null): void`: Removes related product relationships filtered by relation type; optionally removes a specific related product or all products of the given type

#### Course Actions (`app/Actions/Admin/Course/`)
- **CreateCourseAction**: Creates new course instances with content structure
- **UpdateCourseAction**: Updates course metadata and structure
- **DeleteCourseAction**: Handles course archival and cleanup

#### DigitalAsset Actions (`app/Actions/Admin/DigitalAsset/`)
- **CreateDigitalAssetAction**: Creates new digital asset products
- **UpdateDigitalAssetAction**: Updates digital asset metadata and files
- **DeleteDigitalAssetAction**: Handles digital asset removal

#### Seminar Actions (`app/Actions/Admin/Seminar/`)
- **CreateSeminarAction**: Creates new seminar events
- **UpdateSeminarAction**: Updates seminar scheduling and details
- **DeleteSeminarAction**: Handles seminar cancellation and cleanup

#### ProductDeliveryOption Actions (`app/Actions/Admin/ProductDeliveryOption/`)
- **CreateProductDeliveryOptionAction** (`app/Actions/Admin/ProductDeliveryOption/CreateProductDeliveryOptionAction.php`)
  - `handle(ProductDeliveryOptionCreateData $data, Product $product): ProductDeliveryOption`: Creates new delivery methods for products with automatic SKU generation via `SkuGeneratorService` when SKU not provided in request data
- **UpdateProductDeliveryOptionAction** (`app/Actions/Admin/ProductDeliveryOption/UpdateProductDeliveryOptionAction.php`)
  - `handle(ProductDeliveryOptionUpdateData $data, ProductDeliveryOption $option): ProductDeliveryOption`: Updates delivery option pricing and terms
- **DeleteProductDeliveryOptionAction** (`app/Actions/Admin/ProductDeliveryOption/DeleteProductDeliveryOptionAction.php`)
  - `handle(ProductDeliveryOption $option): void`: Removes delivery options
- **GetDeliveryDetailsValidationRulesAction** (`app/Actions/Admin/ProductDeliveryOption/GetDeliveryDetailsValidationRulesAction.php`)
  - `handle(string $deliveryType): array`: Provides validation rules for different delivery option types

#### Discount Actions (`app/Actions/Admin/Discounts/`)
- **CreateDiscountPromotionAction** (`app/Actions/Admin/Discounts/CreateDiscountPromotionAction.php`)
  - `handle(DiscountPromotionCreateData $data): DiscountPromotion`: Creates new discount promotions with complex rules
- **UpdateDiscountPromotionAction** (`app/Actions/Admin/Discounts/UpdateDiscountPromotionAction.php`)
  - `handle(DiscountPromotionUpdateData $data, DiscountPromotion $promotion): DiscountPromotion`: Updates discount promotion rules and conditions
- **DeleteDiscountPromotionAction** (`app/Actions/Admin/Discounts/DeleteDiscountPromotionAction.php`)
  - `handle(DiscountPromotion $promotion): void`: Removes discount promotions and related rules

#### Category Actions (`app/Actions/Admin/Category/`)
- **CreateCategoryAction**: Creates new product categories with media attachments
- **UpdateCategoryAction**: Updates category details and hierarchy
- **DeleteCategoryAction**: Handles category removal and reassignment
- **SetGoodForStartAction**: Flags categories as "good for start" recommendations

#### Wallet Actions (`app/Actions/Admin/Wallet/`)
- **CreateWalletAction** (`app/Actions/Admin/Wallet/CreateWalletAction.php`)
  - `handle(User $user): Wallet`: Initializes a wallet for a user (one per user). Invoked via the `users/{user}/wallet` singleton store endpoint.
- **GetWalletBalanceAction**: Retrieves current wallet balance and history
- **DepositToWalletAction** (`app/Actions/Admin/Wallet/DepositToWalletAction.php`)
  - `handle(WalletDepositData $data, Wallet $wallet): WalletTransaction`: Adds credits via `RecordWalletTransactionAction`.
- **WithdrawFromWalletAction** (`app/Actions/Admin/Wallet/WithdrawFromWalletAction.php`)
  - `handle(WalletWithdrawalData $data, Wallet $wallet): WalletTransaction`: Removes credits via `RecordWalletTransactionAction`; enforces sufficient balance.
- **AdjustWalletAction** (`app/Actions/Admin/Wallet/AdjustWalletAction.php`)
  - `handle(WalletAdjustmentData $data, Wallet $wallet): WalletTransaction`: Applies signed balance adjustments (positive credit / negative debit).

#### WalletCampaign Actions (`app/Actions/Admin/WalletCampaign/`)
- **TriggerCampaignAllocationAction**: Allocates a campaign gift to a user (manual or event-driven). Idempotent (deterministic key `wallet-campaign:{campaign}:user:{user}:trigger:{type}:event:{event}`), dedupes event triggers on `metadata.trigger_event`, honors campaign activity/date-range/per-user/total limits, and resolves the gift expiry deadline from campaign config: relative `metadata.expiry_days` (days from receipt) wins over absolute `ends_at`; no config = no expiry.
- **BulkCampaignAllocationAction**: Manages bulk wallet credit campaigns
- **CreateWalletCampaignAction**: Sets up new wallet campaigns
- **UpdateWalletCampaignAction**: Modifies campaign parameters
- **DeleteWalletCampaignAction**: Cancels and cleans up campaigns
- **EvaluateThresholdRewardAction** (`app/Actions/Admin/WalletCampaign/EvaluateThresholdRewardAction.php`)
  - `handle(User $user, WalletCampaign $campaign): ?WalletTransaction`: Evaluates a payment-completed event against a `loyalty_reward` or `milestone_reward` campaign. `loyalty_reward` measures the user's cumulative paid order total (`metadata.threshold_amount`, rials); `milestone_reward` measures the user's paid order count (`metadata.threshold_order_count`). A paid order is one with a completed ORDER-purpose payment (wallet top-ups and non-completed payments never count). Measurement honors `threshold_scope`: `lifetime` measures all history, `windowed` bounds by the campaign's `starts_at`..`ends_at`. Returns the allocation when the measured value crosses the threshold, null otherwise. Refire protection comes from the shared allocation action (duplicate `payment_completed` trigger-event check + deterministic idempotency key) plus the campaign's per-user limit.

#### Wallet Campaign Event Dispatch (`app/Subscribers/CampaignEventSubscriber.php`)
- **CampaignEventSubscriber**: Single explicit subscriber (registered via `Event::subscribe` in `EventServiceProvider::boot` — the codebase's first subscriber, a deliberate deviation from auto-discovered one-listener-per-event). Maps domain events to active campaigns of a type and allocates through `TriggerCampaignAllocationAction`.
  - `ProfileCompletedEvent` → all active, in-date-range `registration_bonus` campaigns. Ineligible campaigns (inactive/expired/limits) and wallet-less users are skipped, never breaking the event flow.
  - `PaymentCompletedEvent` → active `loyalty_reward` and `milestone_reward` campaigns, evaluated via `EvaluateThresholdRewardAction` (only ORDER-purpose payments; wallet top-ups are ignored). Threshold not yet crossed → no allocation; crossed → gift allocated once.
- **ProfileCompletedEvent** (`app/Events/ProfileCompletedEvent.php`): Dispatched by `UpdateProfileAction` only on the first false→true transition of `User::profileCompleted()` (requires first_name, last_name, email, civil_id, date_of_birth, father_name). Already-complete profiles and repeated updates never re-fire.

#### Staff Actions (`app/Actions/Admin/Staff/`)
- **CreateStaffAction**: Creates new admin user accounts
- **UpdateStaffAction**: Updates staff details and permissions
- **DeleteStaffAction**: Removes staff access and archives records

#### User Actions (`app/Actions/Admin/User/`)
- **CreateUserAction** (`app/Actions/Admin/User/CreateUserAction.php`)
  - `handle(UserCreateData $data): User`: Creates new customer accounts; supports avatar media attachment.
- **UpdateUserAction** (`app/Actions/Admin/User/UpdateUserAction.php`)
  - `handle(UserUpdateData $data, User $user): User`: Updates customer profile information; supports avatar media attachment.
- **DeleteUserAction**: Handles customer account deactivation

#### Teacher Actions (`app/Actions/Admin/Teacher/`)
- **CreateTeacherAction** (`app/Actions/Admin/Teacher/CreateTeacherAction.php`)
  - `handle(CreateTeacherData $data): Teacher`: Creates new instructor profiles with UUID auto-generation, avatar support, and social media links
- **UpdateTeacherAction** (`app/Actions/Admin/Teacher/UpdateTeacherAction.php`)
  - `handle(UpdateTeacherData $data, Teacher $teacher): Teacher`: Updates instructor information including bio, avatar, rate, and social links
- **DeleteTeacherAction** (`app/Actions/Admin/Teacher/DeleteTeacherAction.php`)
  - `handle(Teacher $teacher): void`: Removes instructor profiles

#### Vendor Actions (`app/Actions/Admin/Vendor/`)
- **CreateVendorAction**: Creates new vendor/department records
- **UpdateVendorAction**: Updates vendor information
- **DeleteVendorAction**: Removes vendor relationships

#### Term Actions (`app/Actions/Admin/Term/`)
- **CreateTermAction**: Sets up academic terms and periods
- **UpdateTermAction**: Modifies term schedules and details
- **DeleteTermAction**: Archives completed terms

#### Blog Category Actions (`app/Actions/Admin/Blog/Category/`)
- **CreateBlogCategoryAction** (`app/Actions/Admin/Blog/Category/CreateBlogCategoryAction.php`)
  - `handle(BlogCategoryCreateData $data): BlogCategory`: Creates new blog categories with hierarchical structure and media attachments
- **UpdateBlogCategoryAction** (`app/Actions/Admin/Blog/Category/UpdateBlogCategoryAction.php`)
  - `handle(BlogCategory $category, BlogCategoryUpdateData $data): BlogCategory`: Updates blog category details and icon management
- **DeleteBlogCategoryAction** (`app/Actions/Admin/Blog/Category/DeleteBlogCategoryAction.php`)
  - `handle(BlogCategory $category): void`: Removes blog categories and associated media

#### Blog Post Actions (`app/Actions/Admin/Blog/Post/`)
- **CreateBlogPostAction** (`app/Actions/Admin/Blog/Post/CreateBlogPostAction.php`)
  - `handle(BlogPostCreateData $data, ?Staff $staff = null): BlogPost`: Creates new blog posts with publication workflow, read time calculation, and content relationships
- **UpdateBlogPostAction** (`app/Actions/Admin/Blog/Post/UpdateBlogPostAction.php`)
  - `handle(BlogPost $post, BlogPostUpdateData $data): BlogPost`: Updates blog post content, status, and relationships
- **DeleteBlogPostAction** (`app/Actions/Admin/Blog/Post/DeleteBlogPostAction.php`)
  - `handle(BlogPost $post): void`: Removes blog posts and cleans up media attachments

#### Setting Actions (`app/Actions/Admin/Setting/`)
- **StoreHomePageBlockAction** (`app/Actions/Admin/Setting/StoreHomePageBlockAction.php`)
  - `handle(HomePageBlockCreateData $data): HomePageBlock`: Creates new homepage content blocks
- **UpdateHomePageBlockAction** (`app/Actions/Admin/Setting/UpdateHomePageBlockAction.php`)
  - `handle(HomePageBlockUpdateData $data, HomePageBlock $block): HomePageBlock`: Updates homepage block content and positioning
- **DeleteHomePageBlockAction** (`app/Actions/Admin/Setting/DeleteHomePageBlockAction.php`)
  - `handle(HomePageBlock $block): void`: Removes homepage blocks

#### Enrollment Actions (`app/Actions/Admin/Enrollment/`)
- **ChangeEnrollmentStatusAction** (`app/Actions/Admin/Enrollment/ChangeEnrollmentStatusAction.php`)
  - `handle(Enrollment $enrollment, EnrollmentStatusChangeData $data): Enrollment`: Validates status transitions against an allowed transition matrix (`awaiting_payment → cancelled`, `active → suspended|expired|cancelled`, `suspended → active|cancelled`). Every transition triggers `ProvisioningAttemptService::recordAccessReconciliation()` so applicable providers receive remote reconciliation attempts and unsupported ones become manual-action-required. Appends timestamped, staff-attributed status change notes to the enrollment record. Throws `ValidationException` on invalid transitions.
- **DeleteEnrollmentAction** (`app/Actions/Admin/Enrollment/DeleteEnrollmentAction.php`)
  - `handle(Enrollment $enrollment): void`: Deletes enrollment only if status is not `ACTIVE` — prevents deletion of active enrollments.
- **RetryProvisioningAction** (`app/Actions/Admin/Enrollment/RetryProvisioningAction.php`)
  - `handle(Enrollment $enrollment): void`: Re-dispatches canonical provisioning jobs when `provisioning_data` contains failed/manual-action providers, or dispatches the full required set when provisioning was never attempted (`provisioning_data` null). Retry eligibility is owned by the provisioning state, not the lifecycle status.
- **UpdateEnrollmentAction** (`app/Actions/Admin/Enrollment/UpdateEnrollmentAction.php`)
  - `handle(EnrollmentUpdateData $data, Enrollment $enrollment): Enrollment`: Updates enrollment metadata including access dates, notes, and survey completion status. Access-date changes trigger `recordAccessReconciliation()` (remote re-enrollment with new dates for providers supporting it, manual-action otherwise) and append a staff-attributed audit note when a reason is supplied.

#### Enrollment Provisioning Plan (`app/Services/Enrollment/ProvisioningPlanResolver.php`)
- Resolves the sole canonical provider matrix at Enrollment creation. IMS applies when `ims_course_code` is present; the delivery method selects Moodle, SpotPlayer, BBB, or Skyroom; a separate numeric `moodle_quiz_course_id` selects Moodle Quiz for non-Moodle delivery methods.
- Each applicable provider records `ready`, `disabled`, or `invalid` readiness. Disabled or invalid required providers remain visible and produce aggregate `manual_action_required` health instead of being omitted.
- The persisted aggregate status is `healthy`, `ready`, `in_progress`, `degraded`, or `manual_action_required`. Provider adapters update this aggregate through the shared attempt lifecycle.
- Payment grants the local entitlement (`ACTIVE`) immediately; no-provider paid enrollments are already active. Lifecycle status is `awaiting_payment | active | suspended | expired | cancelled` — provisioning-pending and provisioning-failed lifecycle cases are removed; aggregate provisioning health owns that concern. Occupying statuses are `ACTIVE` and `SUSPENDED`.

#### Provisioning Attempt Lifecycle

`ProvisioningAttemptService` records queued, running, succeeded, retry-scheduled, failed, and manual-action-required states for provider executions. `ProvisionEnrollmentProviderJob` runs Moodle and IMS through provider adapters and `ProvisioningProviderRegistry`; lifecycle transitions and enrollment snapshot merges lock fresh rows in short transactions, with external calls outside those locks. Failure metadata is whitelisted and canonical provider references are persisted without raw provider payloads.

BBB/Niliroom and Skyroom also run through `ProvisionEnrollmentProviderJob` using dedicated adapters. Their adapters consume only the canonical plan and staff-created room references (`meeting_id`/`nili_room_id` or `room_id`); they never create provider rooms. Missing or invalid references become manual-action-required attempt failures. The legacy live-session jobs are no longer dispatched by order completion or retry flows.

Authorized staff can manually resolve or waive a canonical provider through `ManualProvisioningRecoveryAction`. Resolution requires provider-specific safe references and a reason; waiver requires a separate permission and reason. Both append staff-attributed manual attempts and recalculate aggregate health. Plan rebuilds expose an explicit provider diff, require confirmation, increment the plan version, and preserve the prior snapshot and all attempts.

#### Access Reconciliation

Administrative status and access-date changes reconcile deliberately with applicable providers while local `Enrollment` state stays authoritative. `recordAccessReconciliation()` (in `ProvisioningAttemptService`) marks `provisioning_data.reconciliation.status` and creates one attempt per planned provider: providers whose adapter `supportsAccessReconciliation()` and whose references resolve dispatch `ProvisionEnrollmentProviderJob` with `failure_metadata.kind = access_reconciliation`; the rest become manual-action-required. The job's `reconcileOrProvision()` routes to `reconcileAccess()` for such attempts; `MoodleProvisioningProvider::reconcileAccess()` re-enrolls on `active` and un-enrolls on `suspended`/`expired`/`cancelled` (Moodle `unenrollUser`), while IMS and others report `supportsAccessReconciliation() = false` so they stay manual. Reconciliation outcomes update `reconciliation.status` (`in_progress`, `succeeded`, `failed`, `manual_action_required`, `not_applicable`) but never flip the local lifecycle status — aggregate health distinguishes initial provisioning failures from later access-reconciliation problems.

#### Student Story Actions (`app/Actions/Admin/Setting/StudentStory/`)
- **CreateStudentStoryAction** (`app/Actions/Admin/Setting/StudentStory/CreateStudentStoryAction.php`)
  - `handle(StudentStoryCreateData $data): StudentStory`: Creates new student success stories
- **UpdateStudentStoryAction** (`app/Actions/Admin/Setting/StudentStory/UpdateStudentStoryAction.php`)
  - `handle(StudentStoryUpdateData $data, StudentStory $story): StudentStory`: Updates student story content and media
- **DeleteStudentStoryAction** (`app/Actions/Admin/Setting/StudentStory/DeleteStudentStoryAction.php`)
  - `handle(StudentStory $story): void`: Removes student stories

#### Slider Actions (`app/Actions/Admin/Slider/`)
- **CreateSliderAction**: Wraps slider creation with media synchronization for hero imagery
- **UpdateSliderAction**: Updates slider copy, media, and ordering metadata
- **UpdateSliderStatusAction**: Applies publication state changes using `ChangeStatusData`, ensuring enum-safe transitions
- **DeleteSliderAction**: Removes sliders and detaches associated media assets

#### Review Actions (`app/Actions/Admin/Review/`)
- **ApproveReviewAction**: Approves customer reviews for publication
- **RejectReviewAction**: Rejects inappropriate reviews
- **UpdateReviewFeaturedStatusAction**: Manages featured review selection

#### Refund Actions (`app/Actions/Admin/Refund/`)
- **CreateRefundAction** (`app/Actions/Admin/Refund/CreateRefundAction.php`)
  - `handle(RefundCreateData $data): Refund`: Creates refund records with SmartCache locking (`refund_order_item_{id}`, 15s timeout) to prevent double-refund race conditions. Calculates `amountPaid` vs `deductionAmount` for each item. Wraps all work in a DB transaction and catches `Throwable` to surface gateway failures as domain errors. Delegates gateway-specific processing logic to `RefundProcessorFactory` after creation. Updates `OrderItem.total_refunded` and re-evaluates parent order status via `UpdateOrderRefundedAmountAction`. Dispatches `RefundCompletedEvent` on completion.
- **RefundOrderAction** (`app/Actions/Admin/Refund/RefundOrderAction.php`)
  - `handle(Order $order, RefundCreateData $data): void`: Orchestrates full-order refund by iterating over refundable order items, calling `CreateRefundAction` per item. Merges existing `transaction_details` on the refund instead of overwriting them.
- **UpdateOrderRefundedAmountAction** (`app/Actions/Admin/Refund/UpdateOrderRefundedAmountAction.php`)
  - `handle(Order $order): void`: Recalculates `total_refunded` across all order items and updates parent order status accordingly.
- **UpdateRefundAction** (`app/Actions/Admin/Refund/UpdateRefundAction.php`)
  - `handle(RefundUpdateData $data, Refund $refund): Refund`: Updates refund metadata and transaction details.
- **UpdateRefundStatusAction** (`app/Actions/Admin/Refund/UpdateRefundStatusAction.php`)
  - `handle(RefundStatusUpdateData $data, Refund $refund): Refund`: Transitions refund status with validation of allowed transitions (e.g., PENDING → COMPLETED/FAILED). Uses a two-phase safe-completion pattern: locks the refund to a PREP state inside the DB transaction, performs the gateway call (e.g., Digipay refund) outside the DB transaction, and marks success atomically; on gateway failure marks the refund FAILED via `markRefundFailed`. The finalize step is idempotent and blocks on terminal states. On `COMPLETED`, updates item/parent order status and dispatches `RefundCompletedEvent`.

#### Audit Actions (`app/Actions/Admin/Audit/`)
- **DetectSuspiciousActivityAction**: Analyzes admin actions for security risks; report window bounds (start/end dates) are validated and normalized.
- **GenerateComplianceReportAction**: Creates audit and compliance reports; respects report window parameters and applies audit-log redaction rules.

#### Role Actions (`app/Actions/Admin/Role/`)
- **CreateRoleAction** (`app/Actions/Admin/Role/CreateRoleAction.php`)
  - `handle(RoleCreateData $data): Role`: Creates a new role; guards against reserved role names and syncs permissions.
- **UpdateRoleAction** (`app/Actions/Admin/Role/UpdateRoleAction.php`)
  - `handle(RoleUpdateData $data, Role $role): Role`: Updates role metadata and permission assignments; `UpdateRoleData` includes validation rules for role name uniqueness and permission existence.
- **DeleteRoleAction**: Removes unused roles
- **OutputPermissionsAction**: Generates permission reports

#### Partner Actions (`app/Actions/Admin/Partner/`)
- **CreatePartnerAction**: Persists partner showcase cards, linking uploaded media and deriving alt text automatically
- **UpdatePartnerAction**: Updates partner metadata and resyncs media while handling nullified assets
- **DeleteCPartnerAction**: Performs transactional deletion and cleans up linked media assets

#### AdviceRequest Actions (`app/Actions/Admin/AdviceRequest/`)
- **UpdateAdviceRequestAction**: Records staff notes and marks handlers while keeping existing status intact
- **UpdateAdviceRequestStatusAction**: Transitions advice request status (e.g., pending → completed) and stamps handler attribution

### Shop Actions (`app/Actions/Shop/`)

#### Customer Profile & Utilities
- **UpdateProfileAction** (`app/Actions/Shop/UpdateProfileAction.php`)
  - `handle(UpdateProfileData $data, User $user): User`: Transactionally updates customer profile fields, respecting immutable civil ID constraints and returning a fresh model instance.
- **UploadFileAction** (`app/Actions/Shop/UploadFileAction.php`)
  - `handle(UploadedFile $file, bool $isPublic = true): Media`: Streams uploaded attachments into Mediable storage with duplicate-safe filenames, reused by form actions.

#### Home Page Composition
- **GetHomePageBlocksListAction** (`app/Actions/Shop/GetHomePageBlocksListAction.php`)
  - `handle(): Collection<HomePageBlockListData>`: Fetches active blocks ordered by location/order for lightweight listings.
- **GetHomePageBlockAction** (`app/Actions/Shop/GetHomePageBlockAction.php`)
  - `handle(HomePageBlock $block): HomePageBlockData`: Hydrates individual blocks (curated, dynamic, banner, webinar) by preloading products/categories using `ProductQueryService` for unified querying, leveraging `RequestDataCacheService` + `ProductPriceService` to avoid N+1 pricing calculations. Dynamic lists support popular/featured sorting via ProductQueryService methods.

#### Shop Form Actions (`app/Actions/Shop/Forms/`)
- **CreateCollaborationRequestAction** (`app/Actions/Shop/Forms/CreateCollaborationRequestAction.php`)
  - `handle(CreateCollaborationRequestData $data): void`: Stores collaboration enquiries, optionally persisting private attachments via `UploadFileAction`.
- **StoreContactUsRequestAction** (`app/Actions/Shop/Forms/StoreContactUsRequestAction.php`)
  - `handle(ContactUsRequestData $data): void`: Persists contact form submissions for staff follow-up.
- **StoreAdviceRequestAction** (`app/Actions/Shop/Forms/StoreAdviceRequestAction.php`)
  - `handle(AdviceRequestCreateData $data): void`: Records phone numbers from users requesting educational consultation callbacks.

#### Student Dashboard Actions (`app/Actions/Shop/Student/`)
- **GetEnrollmentDetailAction** (`app/Actions/Shop/Student/GetEnrollmentDetailAction.php`)
  - `handle(User $user, Enrollment $enrollment): EnrollmentDetailData`: Returns enriched enrollment detail with typed block DTOs per delivery method, SSO URLs (Moodle via `MoodleService`, Skyroom via `SkyroomService`), certificate info, review info, and survey block status. Block types: `DigitalAssetBlockData`, `InPersonBlockData`, `LiveSessionBbbBlockData`, `LiveSessionSkyroomBlockData`, `LmsMoodleBlockData`, `VideoPlatformSpotplayerBlockData`. Each block type carries delivery-specific data (join URLs, file downloads, etc.).
- **GetJoinUrlAction** (`app/Actions/Shop/Student/GetJoinUrlAction.php`)
  - `handle(Enrollment $enrollment): string`: Lazy-generates join URL for enrollment based on delivery method (BBB, Skyroom, SpotPlayer, Moodle). URLs generated on demand rather than pre-computed.
- **CancelOrderByCustomerAction** (`app/Actions/Shop/Student/CancelOrderByCustomerAction.php`)
  - `execute(Order $order, int $userId): Order`: Allows customers to cancel their own pending orders. Validates: order belongs to user, order is PENDING, no completed payments exist. Transactionally cancels order and associated enrollments.

#### Checkout & Payment Actions (`app/Actions/Shop/*`)
- **CreateOrderFromCartAction** (`app/Actions/Shop/CreateOrderFromCartAction.php`)
  - `handle(CheckoutData $checkoutData, User $user): PaymentProcessResultData`: Wraps the entire checkout pipeline—loads/validates the active cart with `lockForUpdate` (capacity, **registration window, availability window**, publication, duplicate ownership, order velocity), converts it into `OrderCreateData` inside a DB transaction, reuses `CreateOrderAction`. Deletes the cart inside the transaction, then dispatches the selected payment processor **outside** the transaction. Uses `PreparePendingPaymentAction` to create a PENDING Payment before calling `processor->process($payment)`. Free orders (grand total 0) complete immediately through `CompleteFreeOrderPaymentAction` (creates a COMPLETED `NO_PAYMENT` payment record and dispatches `PaymentCompletedEvent` inside the transaction). Returns redirect info for multi-step gateways or finalizes wallet/no-payment flows.
- **TopupWalletAction** (`app/Actions/Shop/Wallet/TopupWalletAction.php`)
  - `handle(Payment $payment): void`: Credits wallet from a completed `WALLET_TOPUP` payment. Validates payment purpose and status. Creates wallet if missing. Records DEPOSIT transaction linked to the payment.
- **RetryOrderPaymentAction** (`app/Actions/Shop/RetryOrderPaymentAction.php`)
  - `handle(Order $order, PaymentMethodEnum $method, ?int $amount = null): PaymentProcessResultData`: Allows customers to retry failed/pending orders, validating outstanding balance and order status before reissuing a processor-specific payment request (partial amounts supported via `amount`).
- **VerifyPaymentAction** (`app/Actions/Shop/Payment/VerifyPaymentAction.php`)
  - `handle(GatewayCallbackData $data): Payment`: Locks the pending payment by UUID, resolves the correct processor via `PaymentProcessorFactory`, and delegates gateway-specific verification/settlement workflows (Mellat, etc.), surfacing validation errors if the payment is no longer pending.


### Auth Actions (`app/Actions/Auth/`)
- **GenerateOtpAction** (`app/Actions/Auth/GenerateOtpAction.php`)
  - `handle(string $identifier): string`: Creates time-limited verification codes
- **InitiateAuthAction** (`app/Actions/Auth/InitiateAuthAction.php`)
  - `handle(AuthInitiateData $data): AuthResponseData`: Starts authentication process for both guards. Creates the user/staff record race-safely (unique-constraint race recovery) when the identifier does not exist yet.
  - **Registration velocity caps:** When creating a new customer user (guard `user`, phone identifier), `RegistrationVelocityService::assertWithinLimits()` checks daily caps per IP address and per device hash (sha256 of IP + user-agent). Exceeding `config('registration_velocity.max_per_day', 3)` throws `RegistrationVelocityExceededException` (429, `messages.auth.register.throttled`). On successful creation, `record()` inserts the device fingerprint row. Existing-user logins (SIGNIN), staff auth, and email identifiers are unaffected.
- **PasswordLoginAction** (`app/Actions/Auth/PasswordLoginAction.php`)
  - `handle(PasswordLoginData $data): AuthTokenData`: Manages password-based login flows
- **VerifyOtpAction** (`app/Actions/Auth/VerifyOtpAction.php`)
  - `handle(OtpVerifyData $data): AuthTokenData`: Handles OTP-based authentication for both guards
- **RequestOtpAction** (`app/Actions/Auth/RequestOtpAction.php`)
  - `handle(OtpRequestData $data): OtpResponseData`: Manages OTP generation and delivery
- **ForgotPasswordAction** (`app/Actions/Auth/ForgotPasswordAction.php`)
  - `handle(ForgotPasswordData $data): PasswordResetResponseData`: Initiates password reset process
- **ResetPasswordAction** (`app/Actions/Auth/ResetPasswordAction.php`)
  - `handle(ResetPasswordData $data): PasswordResetCompleteData`: Completes password reset with OTP verification
- **AuthenticateUserAction** (`app/Actions/Auth/AuthenticateUserAction.php`)
  - `handle(AuthenticationData $data): AuthenticatedUserData`: General user authentication handler
- **AuthAction** (`app/Actions/Auth/AuthAction.php`)
  - `handle(AuthData $data): AuthResultData`: Generic authentication action wrapper
- **ChangePasswordAction** (`app/Actions/Auth/ChangePasswordAction.php`)
  - `handle(Staff|User $authenticatable, ChangePasswordRequest $request): Staff|User`: Validates `current_password` (required when the account has a password set) and updates the hash. Mismatched current password surfaces the `validation.password.current_password_does_not_match` message. Shared by the staff and customer change-password endpoints.

### Admin Profile Actions (`app/Actions/Admin/`)
- **UpdateStaffProfileAction** (`app/Actions/Admin/UpdateStaffProfileAction.php`)
  - `handle(UpdateStaffProfileData $data, Staff $staff): Staff`: Updates the authenticated staff member's own profile fields (name, email, avatar).

### Payment Actions (`app/Actions/Payment/`)
- **PreparePendingPaymentAction** (`app/Actions/Payment/PreparePendingPaymentAction.php`)
  - `handle(actor, customerId, method, purpose, amount, ?order, ?adminNotes, ?data): Payment`: Creates a PENDING Payment record with `attempt_count`, `last_attempted_at`, `ip_address`, `user_agent` tracking. Used by both shop and admin payment flows before handing off to processor.
- **CompleteFreeOrderPaymentAction** (`app/Actions/Payment/CompleteFreeOrderPaymentAction.php`)
  - `handle(Order $order, ?Authenticatable $actor, ?string $adminNotes): PaymentProcessResultData`: Completes an order with grand total 0 by creating a COMPLETED `NO_PAYMENT` payment record and dispatching `PaymentCompletedEvent` inside the DB transaction. Shared by `CreateOrderFromCartAction` and `CreatePaymentAction` so free orders follow one path.

### Wallet Actions (`app/Actions/Wallet/`)
- **RecordWalletTransactionAction** (`app/Actions/Wallet/RecordWalletTransactionAction.php`)
  - `execute(RecordTransactionData $data): WalletTransaction`: Single entry point for all wallet ledger writes. Runs inside a DB transaction, locks the wallet row (`lockForUpdate`), rejects duplicate `idempotency_key` values, and enforces wallet status (throws `WalletNotActive` on inactive wallets). For `PAYMENT`/`ORDER` transactions debits gift balance first (before regular balance) consuming gift credits oldest-first (FIFO by receipt) via each gift's `remaining_amount`, then normal balance, and tracks the split in `wallet_debit_split` metadata (including per-gift `gift_consumptions`). For `EXPIRY` transactions (with `gift_transaction_id`) reclaims a gift's unspent `remaining_amount` as a negative debit: reduces `gift_balance`, zeroes the gift's remaining slice, and never reclaims more than the unspent amount (clamped); throws `GiftAlreadyFullyReclaimedException` when there is nothing left to reclaim. Gift/bonus credits record their full amount as `remaining_amount`.
- **ReclaimExpiredGiftsAction** (`app/Actions/Wallet/ReclaimExpiredGiftsAction.php`)
  - `execute(bool $dryRun = false): array{reclaimed: int, skipped: int}`: Daily sweep that reclaims every expired, unspent gift/bonus credit through an `EXPIRY` ledger debit. Candidates are gift/bonus credits with `remaining_amount > 0` and past `expires_at` on ACTIVE wallets, excluding gifts that already have an EXPIRY transaction (idempotent, deterministic key `wallet-gift-expiry:{gift_id}`). Fully-spent, not-yet-expired, and non-gift credits are never candidates. Gifts consumed concurrently between query and lock are counted as skipped. Supports `--dry-run` counting.

## Services Pattern (`app/Services/`)

### PaymentProcessorFactory (`app/Services/Payment/PaymentProcessorFactory.php`)
- **Purpose:** Resolves the appropriate `PaymentProcessorContract` implementation for a requested `PaymentMethodEnum`
- **Mechanism:** Receives the tagged processor list from `PaymentServiceProvider`, iterates over `canHandle()` implementations, and surfaces meaningful exceptions when no processor supports the requested method

### WalletPaymentProcessor (`app/Services/Payment/WalletPaymentProcessor.php`)
- **Purpose:** Handles immediate wallet debits without redirects
- **Workflow:** Injects `PaymentTransactionReferenceService`. Generates transaction reference, validates sufficient balance (throws `InsufficientWalletBalanceException` with available/required/shortfall details on failure), records debit via `RecordWalletTransactionAction`, creates Payment + PaymentTransaction (COMPLETED) with wallet metadata (wallet_id, available_balance, new_balance), sets `last_gateway_reference`/`attempt_count`/`last_attempted_at` on Payment, and dispatches `PaymentCompletedEvent`. `verify()` is unsupported and throws by design.

### BankTransferPaymentProcessor (`app/Services/Payment/BankTransferPaymentProcessor.php`)
- **Purpose:** Supports both staff-recorded confirmations and customer-initiated bank transfers
- **Workflow:**
  - Staff flows require `BankTransferPaymentData` metadata and immediately complete the payment + dispatch completion events
  - Customer flows create a pending payment awaiting offline review; validation errors raise `ValidationException`
- **Redirect:** Not required; entirely manual/async.

### MellatGatewayPaymentProcessor (`app/Services/Payment/MellatGatewayPaymentProcessor.php`)
- **Purpose:** Implements the multi-step Mellat (بانک ملت) online gateway with per-attempt transaction tracking
- **Process:**
  - `process()`: Generates unique transaction reference via `PaymentTransactionReferenceService`. Creates Payment + PaymentTransaction (INITIATED) records with full gateway request/response capture. Uses transaction reference (not order increment_id) as gateway `orderId`. Tracks attempt count, IP address, user agent.
  - `verify()`: Starts with a verification gatekeeper — if the payment is already `COMPLETED`, returns early; if the order has any other completed payment, throws `RuntimeException` preventing double-verification. Loads latest transaction for the payment. Maps `ResCode` to error messages for failures. On success (`ResCode === '0'`): performs `bpVerifyRequest` + `bpSettleRequest`. Both must succeed before marking transaction as COMPLETED and dispatching `PaymentCompletedEvent`. Failure at any step (verification fail, settlement fail, SOAP fault) updates transaction to FAILED with error details, error codes, timestamps. Settlement code 45 (already settled) treated as success.
  - **Transaction Lifecycle:** Every gateway interaction creates a `PaymentTransaction` record tracking `initiated_at`, `completed_at`, `gateway_request`, `gateway_response`, `error_code`, `error_message`. This provides full audit trail per payment attempt.

### DigipayPaymentProcessor (`app/Services/Payment/DigipayPaymentProcessor.php`)
- **Purpose:** Implements the multi-step Digipay (دیجی‌پی) REST gateway with token-based authentication, callback verification, delivery confirmation, and refund operations
- **Process:**
  - `process()`: Requests access token via `DigipayAuthenticator` (client_credentials grant with `client_id`/`client_secret`). Creates a payment request via `DigipayClient::createTransaction()` with amount, providerId (order UUID), callback URL, and optional PSP selection. Returns redirect URL for customer.
  - `verify()`: Starts with a verification gatekeeper — if the payment is already `COMPLETED`, returns early; if the order has any other completed payment, throws `RuntimeException` preventing double-verification. Validates callback payload via `CallbackPayload::fromRequest()`. On `SUCCESS` result, performs settlement via `DigipayClient::verify()` with tracking code. Completes the payment and dispatches `PaymentCompletedEvent`.
- **Data Objects:** `CallbackPayload` (amount, providerId, trackingCode, result, rrn, psp, pspCode, pspName), `VerifyResponse`, `DeliverResponse`, `RefundResponse`, `RefundInquiryResponse`, `ReverseResponse`, `TicketResponse`.
- **Admin Operations** (`DigipayAdminService`): `refund(Payment $payment, int $amount)` — initiates gateway reversal; `deliver(Payment $payment)` — confirms digital goods delivery; `reverse(Payment $payment)` — voids unsettled transaction; `inquireRefund(string $trackingCode)` — checks refund status.

### RefundProcessorFactory (`app/Services/Payment/Refund/RefundProcessorFactory.php`)
- **Purpose:** Resolves the appropriate `RefundProcessorInterface` implementation by `PaymentMethodEnum`
- **Available Processors:**
  - `DigipayRefundProcessor` — processes refunds through Digipay gateway API with cumulative cap validation (prevents over-refund)
  - `ManualRefundProcessor` — records manual/offline refunds without gateway interaction (used for MELLAT_GATEWAY and BANK_TRANSFER)
  - `WalletRefundProcessor` — credits refund amount back to customer wallet
- **Resolution:** Uses `PaymentMethodEnum::tryFrom()` for type-safe method matching.

### SoapClientFactory (`app/Services/Payment/SoapClientFactory.php`)
- **Purpose:** Minimal helper that instantiates `SoapClient` instances from remote or local WSDL endpoints; wrapped to simplify mocking in unit tests

### OrderStatusService (`app/Services/OrderStatusService.php`)
- **Purpose:** Centralized order and enrolment status management with provisioning trigger awareness
- **Public Methods:**
  - `handlePaymentCompletion(Order $order): void`: Reads `config('order.provisioning.trigger')` to determine auto-provisioning behavior:
   - `any_payment` (default): Immediately completes items/enrollments
   - `full_payment`: Provisions only when `balance_due <= 0`
   - `manual_approval`: Never auto-provisions — sets order to PROCESSING, requiring staff to call `ApproveOrderAction`
   - `updateEnrollmentStatus(OrderItem $item): void`: Updates enrolment access based on order item status changes (completed items set enrolments to `ACTIVE`, setting `access_start_date` when first activated; refunded/cancelled items set `CANCELLED`). Uses `save()` to fire model events for `enrolled_count` synchronization.
  - `completeOrderItemAfterPayment(OrderItem $item): void`: Internal method for item-level status updates. Creates enrollment via `firstOrCreate()` if none exists (status `ACTIVE`), then calls `updateEnrollmentStatus()`.
  - `updateParentOrderStatus(Order $order): void`: Determines parent order status from collective item states: all refunded → REFUNDED, all cancelled → CANCELLED, any refunded → PARTIALLY_REFUNDED, all completed → COMPLETED, default → PROCESSING
- **Reservations:** Depends on `ProductReservationService` — consumes reservations (`consume()`) for items reaching a paid/completed state so `enrolled_count + reserved_count` stays within capacity.

### ProductReservationService (`app/Services/ProductReservationService.php`)
- **Purpose:** Manages capacity reservations on `product_delivery_options.reserved_count` for PENDING orders
- **Public Methods:**
  - `reserve(int $deliveryOptionId, int $qty): void`: Atomically increments `reserved_count` (guarded against exceeding remaining capacity)
  - `consume(int $deliveryOptionId, int $qty): void`: Decrements `reserved_count` when a payment completes (seat converts to `enrolled_count`)
  - `release(int $deliveryOptionId, int $qty): void`: Decrements `reserved_count` when an order is cancelled/abandoned
- **Lifecycle:** `reserve` on order creation (`CreateOrderAction`), `consume` on payment completion (`OrderStatusService`, `ApproveOrderAction`), `release` on cancellation/abandonment (`CancelOrderByCustomerAction`, `DeleteOrderAction`, `CancelAbandonedOrdersCommand`). Decrements never go below zero.

### CartService (`app/Services/CartService.php`)
- **Purpose:** Single façade for cart lifecycle management across authenticated and guest flows
- **Key Capabilities:**
  - `findOrCreateCart(?User $user = null, bool $lockForUpdate = false): Cart`: Resolves carts via the `CartIdentifier` contract (user or guest token) and eagerly loads delivery options/products. Supports `lockForUpdate` for transactional checkout flows.
  - `addItem`, `updateItem`, `removeItem`: Validate capacity/payment type constraints before mutating cart rows
  - `applyCoupon(ApplyCouponData $data): CartData`: Validates coupon codes via `PromotionService::findPromotionByCoupon()`, checks condition gates via `checkPromotionConditions()`, tracks them on the cart, and recalculates totals through `OrderCalculationService`
  - `buildCartDataWithTotals(Cart $cart): CartData`: Hydrates DTOs with current pricing/discount context for API responses
- **Internal:** `resolveCart()` implements the find-or-create pattern with unique constraint race recovery for concurrent requests.
- **Special Notes:** Enforces an order velocity limit (5 orders/hour) during checkout and delegates cart persistence cleanup post-successful conversion

### RequestCartIdentifier (`app/Services/Cart/RequestCartIdentifier.php`)
- **Purpose:** HTTP-scoped implementation of `CartIdentifier` that decides whether to use an authenticated user ID or a persistent guest token (via `X-Guest-Token` header)
- **Responsibilities:** Exposes `userId()`, `guestToken()`, and `ensureGuestToken()` helpers used by `CartService` and middleware to keep carts consistent across sessions. Bound as a scoped binding; `userId()` and `guestToken()` check auth on each call, ensuring dynamic auth state reflection across a request lifecycle. `isGuest()` delegates directly to `auth->check()`.

### Discount Services (`app/Services/Discounts/`)

#### PromotionService (`app/Services/Discounts/PromotionService.php`)
- **Purpose:** Central promotion matching, coupon resolution, and order-context building for cart and product discounts
- **Public Methods:**
  - `findPromotionByCoupon(string $couponCode): ?DiscountPromotion`: Resolves an active promotion from a coupon code, enforcing `is_active`, `starts_at`/`ends_at` window, and `usage_limit_total`
  - `findAllApplicableCartPromotions(OrderContextData $context, DiscountTypeEnum $type = CART_CHECKOUT): Collection`: Returns all promotions whose conditions pass, sorted by `priority` descending
  - `buildOrderContext(OrderCreateData $data, bool $useFreshData = false): OrderContextData`: Normalizes order data into the shared calculation context
  - `promotionConditionsPass(DiscountPromotion $promotion, OrderContextData $context): bool`: Evaluates all rules of a promotion against the context
  - `checkPromotionConditions(DiscountPromotion $promotion, OrderCreateData $data): bool`: Convenience wrapper used by `CartService::applyCoupon()`

#### OrderCalculationService (`app/Services/Discounts/OrderCalculationService.php`)
- **Purpose:** Comprehensive discount and pricing calculation engine integrated with ProductPriceService
- **Public Methods:**
  - `calculate(OrderCreateData $data): OrderContextData`: Builds the order context via `promotionService->buildOrderContext()`, collects all applicable CART_CHECKOUT promotions (`findAllApplicableCartPromotions`), and applies their actions in priority order — respecting `stop_processing_subsequent_rules` to prevent stacking. Uses ProductPriceService for consistent pricing hierarchy.
- **Dependencies:** `PromotionService`, `ProductPriceService`, `DiscountHandlerRegistry` for action execution

#### DiscountHandlerRegistry (`app/Services/Discounts/DiscountHandlerRegistry.php`)
- **Purpose:** Registry that auto-discovers discount condition/action handlers by contract
- **Mechanism:** Scans registered handlers grouped by interface — `DiscountConditionContract` (cart conditions), `DiscountActionContract` (cart actions), `ProductDiscountConditionContract` (product conditions), `ProductDiscountActionContract` (product actions). Resolves handlers by rule `key` from each group; results cached under `discounts.handler_registry.cache` via the `CACHE_KEY` constant.
- **Consumers:** `OrderCalculationService`, `ProductDiscountIndexer`, `DiscountMetadataService`

#### DiscountMetadataService (`app/Services/Discounts/DiscountMetadataService.php`)
- **Purpose:** Exposes discount rule metadata and configuration schema for the admin discount-builder UI
- **Public Methods:**
  - `getMetadata(): array` / `getConditions()` / `getActions()` / `getOperators()` / `getTypes()`: Catalog endpoints (back `DiscountInfoController`)
  - `extractConfigSchema(string $configClass, string $key, array $visited = []): array`: Introspects a config Data class into a schema tree
  - `getConfigurationClass(string $handlerClass): ?string` / `getParameterType(...)`: Resolve config class and parameter types for a handler
- **i18n:** Label/description strings resolve through the `discount.php` language file (en/fa) via an auto-resolver, so handler labels localize without code changes.

#### ProductDiscountIndexer (`app/Services/Discounts/ProductDiscountIndexer.php`)
- **Purpose:** Indexes products for efficient discount application
- **Public Methods:**
  - `indexProduct(Product $product): void`: Adds product to discount index
  - `reindexAll(): void`: Rebuilds complete discount index
  - `getActivePromotions(Product $product, ?User $user = null): Collection`: Returns promotions whose `starts_at`/`ends_at` window is active (window enforcement lives in the indexer)

#### ProductDiscountPriceCalculator (`app/Services/Discounts/ProductDiscountPriceCalculator.php`)
- **Purpose:** Calculates discounted prices for individual products
- **Public Methods:**
  - `calculateDiscountedPrice(Product $product, User $user = null): float`: Calculates final price after discounts
  - `getApplicableDiscounts(Product $product): Collection`: Returns all applicable discounts for product

#### Cart Action Handlers (`app/Services/Discounts/Cart/Actions/`)
- `AddGiftCreditAction`, `AddWalletCreditAction`, `ApplyFixedAmountOffAction`, `ApplyPercentageDiscountToItemsAction`, `ApplyTieredPercentageOffAction`, `GiftProductAction` — mutate cart totals per rule config.

#### Cart Condition Handlers (`app/Services/Discounts/Cart/Conditions/`)
- `CartItemCountOverCondition`, `CartValueCondition`, `FirstOrderOnlyCondition`, `ProductCategoryCondition`, `SpecificProductsInCartCondition`, `UserNeverPurchasedCategoryCondition` — gate cart-level rule eligibility.

#### Product Action Handlers (`app/Services/Discounts/Product/Actions/`)
- `ApplyFixedDiscountToProductAction`, `ApplyFixedPriceProductAction`, `ApplyPercentageDiscountToProductAction`, `ApplyTieredPercentageOffProductAction` — adjust product pricing.

#### Product Condition Handlers (`app/Services/Discounts/Product/Conditions/`)
- `DeliveryMethodIsCondition`, `LowCapacityRemainingCondition`, `PriceBetweenCondition`, `ProductCategoryCondition`, `RegistrationClosingSoonCondition`, `VendorIsCondition` — gate product-level rule eligibility.

#### Config Data Classes (`app/Services/Discounts/Configs/`)
- Per-handler configuration DTOs (`ApplyTieredPercentageOffData`, `DeliveryMethodIsData`, `GiftProductData`, `ProductCategoryConditionConfigData`, `SpecificProductsInCartData`, `TierData`, `UserNeverPurchasedCategoryData`, etc.) define the typed `rules[].config` payload validated against each handler's schema.

### ProductPriceService (`app/Services/ProductPriceService.php`)
- **Purpose:** Centralized product pricing logic with hierarchy support and caching
- **Public Methods:**
  - `getPriceDataForProduct(Product $product, ?int $selectedDeliveryOptionId = null): ProductPriceData`: Returns comprehensive pricing data following pricing hierarchy (product-specific discounts > featured prices > standard prices)
  - `getMinCurrentPrice(Product $product, ?int $selectedDeliveryOptionId = null): int`: Gets minimum effective price for product
  - `getPriceDataForOption(ProductDeliveryOption $option): ProductDeliveryOptionPriceData`: Gets pricing data for specific delivery option
  - `getPriceDataForProducts(Collection $products): Collection`: Efficiently processes multiple products
  - `hasActiveDiscount(Product $product, ?int $selectedDeliveryOptionId = null): bool`: Checks if product has active discounts
  - `getPriceRangeForProduct(Product $product): array`: Returns min/max price range
  - `getHighestDiscountPercentage(Product $product, ?int $selectedDeliveryOptionId = null): float`: Calculates maximum discount percentage
- **Dependencies:** `RequestDataCacheService` for performance optimization

### GatewayService (`app/Services/Payment/GatewayService.php`)
- **Purpose:** Centralized gateway settings resolution with config fallback for shop-facing and admin-facing endpoints
- **Public Methods:**
  - `getShopActiveGateways(): array`: Returns array of active gateway method values (e.g., `['mellat', 'wallet', 'bank_transfer']`) for checkout validation.
  - `getShopActiveGatewaysDetials(): array`: Returns array of `GatewayData` DTOs for shop gateway listing (enabled + shop_enabled).
- **Mechanism:** Resolves each `PaymentMethodEnum` with a `settingKey()` against `SettingsService`, falling back to `PaymentMethodEnum::defaultConfig()` (reads from `config/payments.php`) when no stored settings exist.

### SettingSecretRedactor (`app/Services/SettingSecretRedactor.php`)
- **Purpose:** Redacts secret field values from integration setting arrays before API responses and audit logging
- **Methods:**
  - `redact(string $settingKey, mixed $value): mixed`: Replaces known secret field values with `***REDACTED***`
  - `hasSecrets(string $settingKey): bool`: Whether a setting key has any secret fields
- **Secret fields per key:** IMS (`api_key`), Moodle (`token`, `auth_userkey_token`), BBB (`secret`, `default_attendee_password`, `default_moderator_password`), SpotPlayer (`api_key`)
- **Usage:** Applied in `SettingData::fromModel()`, `SettingsService::auditIntegrationWrite()`, and during `set()` for placeholder detection

### Integration Services (`app/Services/Integrations/`)

#### AbstractIntegrationService (`app/Services/Integrations/AbstractIntegrationService.php`)
- **Purpose:** Abstract base class providing shared configuration resolution, HTTP error handling, and lifecycle guards for all integration services
- **Abstract Methods:**
  - `getSettingKey(): SettingKeyEnum` — which settings key holds credentials
  - `getConfigFallbackPath(): string` — config fallback path
  - `validateConfig(): bool` — validates mandatory config fields
- **Concrete Methods:**
  - `isEnabled(): bool` — checks `config['enabled']`
  - `assertConfigured(): void` — throws `UnrecoverableProvisioningException` if config invalid
  - `isReady(): bool` — combines `isEnabled()` + `validateConfig()`
  - `resolveConfig(): void` — merges stored settings with config fallback
  - `handleHttpErrors(Response $response, string $endpoint): void` — standardized error handler for JSON REST integrations (throws `RecoverableProvisioningException` for 5xx, `UnrecoverableProvisioningException` for 4xx)
- **Subclasses:** ImsService, MoodleService, SpotPlayerService, BbbService, SkyroomService all extend this base

#### SkyroomClientContract (`app/Contracts/Integrations/SkyroomClientContract.php`)
- **Purpose:** Narrow boundary shared by the real Skyroom API client and the deterministic E2E simulated client.
- **Consumers:** Skyroom provisioning and Jedu-side join URL generation depend on this contract.

#### SkyroomService (`app/Services/Integrations/SkyroomService.php`)
- **Purpose:** Skyroom video conferencing API client for meeting management and user provisioning
- **Methods:**
  - `createLoginUrl(string $userId, string $username): string`: Generates SSO login URL for Skyroom platform
  - `findOrCreateUser(User $user): array`: Finds existing Skyroom user by email or creates a new account
  - `addUserToRoom(int $roomId, int $skyroomUserId, string $role = 'normal'): void`: Enrolls user in a Skyroom room with specified role

#### ImsService (`app/Services/Integrations/ImsService.php`)
- **Purpose:** Real IMS (Internal Management System) REST API client implementing `ImsClientContract` for student & enrollment CRUD operations and teacher dashboard data
- **Methods:**
  - `setConfig(array $config): void`: Injects runtime configuration (credentials, endpoint)
  - `storeStudent(array $payload): array`: Creates student record via POST `/api/v2/student`
  - `storeEnrollment(User $user, array $payload): array`: Creates enrollment record via POST `/api/v2/enrolment/{civil_id}`
  - `getTeacherCourses(array $payload): array`: Lists courses taught by a teacher (drives teacher dashboard)
  - `getAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $queryParams): array`: Reads attendance sessions/records for a course
  - `storeAttendance(...)`, `updateAttendance(...)`, `destroyAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): void`: Creates/updates/deletes attendance records
  - `getGrades(...)`, `storeGrade(...)`, `storeBulkGrades(...)`: Course grade read/write operations
- **Security:** PII redaction in logs (email, phone via `sanitizeBody()`); credentials resolved via `SettingsService`

#### ImsClientContract (`app/Contracts/Integrations/ImsClientContract.php`)
- **Purpose:** Shared boundary for IMS provisioning and teacher dashboard operations. The real client is used in normal environments; `FakeImsService` implements the same contract in E2E with stable, credential-free identifiers and no outbound requests.

#### MoodleClientContract (`app/Contracts/Integrations/MoodleClientContract.php`)
- **Purpose:** Narrow client boundary shared by the real Moodle Web Services client and the deterministic E2E simulated client. Provisioning, read-side consumers, SSO, quizzes, progress sync, and access reconciliation depend on this contract.

#### MoodleService (`app/Services/Integrations/MoodleService.php`)
- **Purpose:** Real Moodle Web Services API client implementing `MoodleClientContract` for user management, enrollment, grades, and SSO
- **Methods:**
  - `setConfig(array $config): void`: Injects runtime configuration
  - `findOrCreateUser(User $user): array`: Finds or creates Moodle user → returns `[moodleUserId, moodleUsername]`
  - `isCourseCompleted(int $moodleCourseId, int $moodleUserId): bool`: Checks course completion status
  - `getActivityCompletionStatus(int $moodleCourseId, int $moodleUserId): array`: Returns per-activity completion states
  - `getGrades(int $moodleCourseId, int $moodleUserId): array`: Returns course grade + activity-level grades
  - `getCourse(int $moodleCourseId): LmsMoodleBlockData`: Fetches course content structure
  - `enrollUser(int $moodleUserId, int $moodleCourseId, ?int $startTime, ?int $endTime, int $roleId = 5): void`: Manual enrollment
  - `createUserKey(string $username, ?string $token = null): string`: Generates SSO login URL key

#### FakeMoodleService (`app/Services/Fakes/FakeMoodleService.php`)
- **Purpose:** Deterministic, credential-free Moodle client used only when `APP_ENV=e2e`; returns stable user/course/login references and implements the same `MoodleClientContract` without outbound requests.

#### SpotPlayerService (`app/Services/Integrations/SpotPlayerService.php`)
- **Purpose:** Real SpotPlayer video platform client implementing `SpotPlayerClientContract` for license provisioning
- **Methods:**
  - `issueLicense(string $spotId, User $user): array`: Issues license → returns `{license_key, player_url, raw}`

#### SpotPlayerClientContract (`app/Contracts/Integrations/SpotPlayerClientContract.php`)
- **Purpose:** Shared boundary for SpotPlayer provisioning and readiness checks. The real client is used in normal environments; `FakeSpotPlayerService` implements the same contract in E2E with stable, credential-free license references and no outbound requests.

#### BbbService (`app/Services/Integrations/BbbService.php`)
- **Purpose:** Real BigBlueButton API client implementing `BbbClientContract` for meeting management and join URL generation (SHA1 checksum auth)
- **Methods:**
  - `createMeeting(string $meetingId, string $name, ?string $attendeePw, ?string $moderatorPw): void`: Creates BBB meeting
  - `buildJoinUrl(string $meetingId, string $fullName, ?string $password): string`: Generates attendee/moderator join URL

#### BbbClientContract (`app/Contracts/Integrations/BbbClientContract.php`)
- **Purpose:** Shared boundary for BBB provisioning, join URL generation, and readiness checks. The real client is used in normal environments; `FakeBbbService` implements the same contract in E2E with stable, credential-free meeting URLs and no outbound requests.

### Provisioning Jobs (`app/Jobs/Provisioning/`)

#### ProvisionEnrollmentProviderJob (`app/Jobs/Provisioning/ProvisionEnrollmentProviderJob.php`)
- **Purpose:** Generic canonical provisioning worker. Starts a `ProvisioningAttempt`, resolves the provider adapter via `ProvisioningProviderRegistry`, runs `provision()` (or `reconcileAccess()` for attempts tagged `kind = access_reconciliation`), then records success, schedules a retry, or fails the attempt. Tries: 3, backoff: [60, 180, 600]s, unique per attempt.

#### SyncMoodleProgressJob (`app/Jobs/Provisioning/SyncMoodleProgressJob.php`)
- **Purpose:** Syncs Moodle course completion, activity statuses, and grades into enrollment `provisioning_data.providers.<key>.sync`. Triggered on enrollment detail view (rate-limited to 5-min throttle per enrollment). This is a background sync task, not a provisioning task.

#### Provisioning Provider Adapters (`app/Services/Provisioning/Providers/`)
- **MoodleProvisioningProvider:** Finds/creates the Moodle user, validates the course, and enrolls them; returns safe canonical references (`moodle_user_id`, `moodle_user_name`, `moodle_course_id`, `course_url`, `login_path`, `provisioned_at`). Supports access reconciliation (re-enroll on `active`, un-enroll on `suspended`/`expired`/`cancelled`).
- **ImsProvisioningProvider:** Creates/updates the IMS student and stores the enrollment with payment details; ambiguous outcomes require manual verification. Does not support access reconciliation.
- **SpotPlayerProvisioningProvider:** Issues a SpotPlayer licence from the canonical delivery-option plan and returns safe licence references; uncertain issuance outcomes require manual verification.
- **MoodleQuizProvisioningProvider:** Finds/creates the Moodle user and enrolls them in the canonical quiz course without a date window.
- **BbbProvisioningProvider / SkyroomProvisioningProvider:** Consume only the canonical plan and staff-created room references; they never create provider rooms. Missing or invalid references become manual-action-required attempt failures.

### Provisioning Orchestration

#### OrderStatusUpdateListener (`app/Listeners/OrderStatusUpdateListener.php`)
- **Purpose:** Dispatches provisioning after Order completion through the canonical entry point
- **Trigger:** Listens on `OrderStatusUpdatedEvent` (queued)
- **Logic:** For each order item with completed status, resolves the canonical plan, then for each planned provider calls `ProvisioningAttemptService::queue()` and dispatches `ProvisionEnrollmentProviderJob`:
  - `ims_course_code` in details → IMS adapter
  - `LMS_MOODLE` delivery → Moodle adapter
  - `VIDEO_PLATFORM_SPOTPLAYER` delivery → SpotPlayer adapter
  - applicable `moodle_quiz_course_id` → Moodle Quiz adapter
  - `LIVE_SESSION_BBB` delivery → BBB adapter
  - `LIVE_SESSION_SKYROOM` delivery → Skyroom adapter (join URL generated lazily via `GetJoinUrlAction` at request time)
  - No planned providers → `activateIfNoProvisioningRequired()` (keeps `provisioning_status` healthy)
- The legacy per-provider jobs (ProvisionMoodleEnrollmentJob, ProvisionImsEnrollmentJob, ProvisionSpotPlayerEnrollmentJob, ProvisionBbbEnrollmentJob, ProvisionSkyroomEnrollmentJob, ProvisionMoodleQuizJob), `AbstractProvisioningJob`, and the `HandlesProvisioningStatus` trait are removed — provisioning runs exclusively through the canonical job and adapters.

#### UpdateStatusesAfterPaymentListener (`app/Listeners/UpdateStatusesAfterPaymentListener.php`)
- **Purpose:** Routes payment completion events based on payment purpose:
  - `WALLET_TOPUP`: Calls `TopupWalletAction::handle()` to credit the customer's wallet.
  - `ORDER`: Calls `OrderStatusService::handlePaymentCompletion()` to complete order/enrollment lifecycle.

### RequestDataCacheService (`app/Services/RequestDataCacheService.php`)
- **Purpose:** Request-scoped caching service to prevent duplicate database queries and calculations
- **Public Methods:**
  - `hasProduct(int $id): bool`: Checks if product is cached
  - `getProduct(int $id): ?Product`: Retrieves cached product
  - `storeProducts(Collection $products): void`: Stores multiple products in cache
  - `hasPriceData(int $productId): bool`: Checks if price data is cached
  - `getPriceDataForProduct(int $productId): ?ProductPriceData`: Retrieves cached price data
  - `storeProductPriceData(int $productId, ProductPriceData $priceData): void`: Caches price calculations
- **Pattern:** Singleton service registered in AppServiceProvider for request lifecycle management

---

### Exception Hierarchy
- `ExternalProvisioningException` — abstract base; subclasses: `RecoverableProvisioningException` (transient 5xx errors), `UnrecoverableProvisioningException` (permanent 4xx/config errors).
- `Gateway\BankException` — abstract base for gateway-specific bank exceptions; subclasses include `MellatException`.
- `Gateway\DigipayException` — Digipay gateway exception in `App\Exceptions\Gateway` namespace.
- `Payment\DuplicatePaymentException` — thrown when attempting to create a duplicate payment.
- `Payment\InvalidPaymentPurposeException` — thrown when payment purpose does not match expected context.
- `Payment\OrderFullyPaidException` — thrown when calculating next payment for an already fully paid order.
- `Payment\PaymentException` — base payment domain exception implementing `PaymentExceptionContract`.
- `Payment\PaymentTransactionNotFoundException` — thrown when a payment transaction is not found.
- `Wallet\WalletException` — abstract base for wallet domain exceptions.
- `Wallet\WalletNotActive` — thrown when attempting to use an inactive wallet.
- `Wallet\WalletNotFoundException` — thrown when wallet does not exist for a user.
- `Wallet\WalletUserNotFoundException` — thrown when user not found for wallet operations.
- `Wallet\WalletInsufficientBalanceException` — thrown when wallet balance is insufficient; includes `availableBalance`, `requiredBalance`, `shortfall`, `sourceType`, `sourceId`.
- `ResourceNotProvisionedException` — thrown when a requested provisioning resource is unavailable.
- Order cancellation throws `ValidationException`.
- Wallet/user creation errors use domain-specific wallet exceptions.

### PermissionEnum
- `PermissionEnum` cases organized by resource domain. See `config/permission-generator.php` for full list. Run `sail artisan permission:sync` to synchronize enums with permissions.
- `ENROLLMENT_RETRY_PROVISION` permission gates retry provisioning authorization.

## Behavior Clarifications

### Gateway Verification Gatekeeper
- Verification starts with a gatekeeper in each processor: if `Payment.status` is already `COMPLETED`, returns early. If the order has any other completed payment, throws `RuntimeException` preventing double-verification.
- Only `PENDING` payments transition to `COMPLETED`.

### Capacity & Concurrency at Checkout
- Capacity is enforced at checkout time; cart additions do not reserve capacity.
- In last-spot race scenarios, the first successful checkout wins; subsequent checkouts receive validation errors on `items.0`.

### Duplicate Ownership Across Options
- Ownership is defined by `(productable_type, productable_id)` and prevents repurchase of the same underlying productable via different delivery options.

### Discounts Evaluation & Counters
- Promotions/coupons are evaluated at checkout time; expired promotions at checkout are ignored even if applied earlier.
- `DiscountPromotion.total_usage_count` and `DiscountCoupon.usage_count` increment only on successful checkout.

### Wallet Insufficient Balance Flow
- Wallet checkout with insufficient funds returns a `wallet_balance` validation error; retry after top-up completes normally.

### Price Field Invariant on Order Items
- `order_items.price` always stores the base price from `product_delivery_option.price` at order creation. It never includes any discounts (product-level or cart-level). Discount information is tracked separately: product-level discounts in `pricing_metadata`, cart-level discounts in `discount_amount`.

### Discount Snapshots on Orders
- `Order.applied_cart_discounts_json` and `OrderItem.applied_discount_details_json` persist the applied discounts as immutable snapshots of checkout state.
- `OrderItem.pricing_metadata` stores the product-level discount breakdown (`original_price`, `discount_type`, `discount_amount`, `discount_percentage`) for each order item. Pre-payment items receive zero discount metadata.
- Two-layer discount tracking: product-level discounts (featured prices, auto-promotions) are stored in `pricing_metadata` and exposed via `productDiscountAmount` accessor; cart-level discounts (coupons) are stored in the `discount_amount` column. The `total_discount_amount` accessor combines both layers.

### SkuGeneratorService (`app/Services/SkuGeneratorService.php`)
- **Purpose:** Automatic SKU generation for product delivery options based on product type, term, and delivery method
- **Public Methods:**
  - `generateBaseSku(ProductDeliveryOptionCreateData $data, Product $product): string`: Generates SKU following pattern: `{PRODUCT_CODE}-{TERM_CODE}-{FULFILLMENT_CODE}-{DELIVERY_CODE}` (e.g., "PYT-F1402-OFF-VID" for Python course, Fall 1402, Offline, Video)
- **SKU Structure:**
  - Product code: 3-char abbreviation from short_name
  - Term code: Academic year + season (F/W/S/SU/X for Fall/Winter/Spring/Summer/Unknown)
  - Fulfillment: OFF/ONL/DIG/INP/OTH
  - Delivery: VID/DL (Video platform/Direct download)
- **Dependencies:** Integrated into `CreateProductDeliveryOptionAction` for automatic SKU assignment

### ProductQueryService (`app/Query/ProductQueryService.php`)
- **Purpose:** Central query layer for all shop product listings with smart Typesense → database fallback and score-aware ordering
- **Key Capabilities:**
  - `getCourseList()`, `getSeminarList()`, `getDigitalAssetList()`: Shared pagination pipelines driven by `ProductListRequestData`
  - `globalSearch(ProductListRequestData $requestData)`: Uses Scout/Typesense multi-field search when available, automatically falling back to SQL full-text and ordered scoring
  - `globalSearchProductsDatabase()` / `globalSearchProductsScout()`: Driver-specific search implementations used by the primary entry point
  - `availableProducts()`: Ensures published product, productable, delivery options, and active term status
  - `availableNow()`: Filters to delivery options currently within registration and availability windows
  - `registrationWindow(Carbon $from, Carbon $to)` / `availabilityWindow(Carbon $from, Carbon $to)`: Overlap-aware date filtering for storefront scheduling needs (supports direct date range params and scope usage)
  - `availabilityStatus(AvailabilityStatusEnum)`: Filters products by temporal state — `PAST` (available_to < now), `UPCOMING` (available_from > now), `ONGOING` (within window). Applied as deferred relationship constraint on `productDeliveryOptions`
  - `nearingCapacity(float $threshold = 0.8)`: Filters to products where at least one delivery option has `enrolled_count / capacity >= threshold`
  - `withoutFullProducts()`: Excludes delivery options where `capacity IS NOT NULL AND capacity <= enrolled_count`
  - `sortByCapacityUtilization(float $threshold = 0.8)`: Uses `LEFT JOIN LATERAL` subquery to compute `max_ratio` and `near_capacity_flag` in a single pass; orders by near-capacity first, then utilization ratio descending. `sortBy` accepts `capacity_utilization` parameter
  - `inCategories()` / `inCategoryIds()`: Deferred category constraints using collected relationship callbacks
  - `goodForStart(array $categorySlugs)`: Limits to course productables flagged as `good_for_start` within the pivot table
  - `byCourseLevel()` / `byFulfillmentTypes()`: Filters using enums for consistent DTO integration
  - `withDiscounts()` / `priceRange()`: Joins the price index when required without duplicating joins
  - `sortBy()` & `query->orderByScore()`: Supports deterministic ordering with optional PGroonga scoring metadata. New allowed sort field: `capacity_utilization`
  - Terminal methods (`paginate`, `get`, `first`, `getQuery`) execute after deferred relationship constraints are applied
- **Pattern:** Maintains the deferred constraint collector ensuring `whereHas`/`whereHasMorph` consolidation, preventing redundant joins and enabling reusable query presets.
- **Scout fallback:** When `capacity_utilization` sorting or capacity-related filters are used, automatically falls back to database query (Typesense cannot handle these natively)

### CategoryQueryService (`app/Query/CategoryQueryService.php`)
- **Purpose:** Category-focussed product loader that reuses the shared query engine and hydrates pricing in bulk
- **Public Methods:**
  - `getProductsForCategory(Category $category, ProductableEnum $type, int $limit, bool $paginate = false)`: Returns limited or paginated product card DTO collections for a category/type combination, reusing `ProductQueryService` filters and `ProductPriceService` batch hydration
- **Integration:** Backing service for category detail endpoints and curated block hydration (e.g., "good for start" lists)

### ProductAvailabilityFilter (`app/Query/ProductAvailabilityFilter.php`)
- **Purpose:** Static filter suite for product availability, each method transparently switching between denormalized snapshot columns and relationship-based queries based on `config('products.availability.use_denormalized')`
- **Key Methods:**
  - `applyPublishedAndVisible`: PUBLISHED + `is_visible`
  - `applyHasPublishedDeliveryOption`: snapshot flag vs `whereHas` published delivery options
  - `applyPublishedProductable`: snapshot `productable_status` vs `whereHasMorph` published productable
  - `applyActiveTerm`: snapshot `is_term_active` vs term null-or-ACTIVE
  - `applyAvailableNow`: snapshot date windows vs per-option registration + availability date windows (null = unbounded)
  - `applyContentAvailableNow`: content-availability-only variant (availability window only, no registration)
  - `applyEventStatus` / `applyEventNotEnded`: delegates to Product `availabilityStatus` / `eventNotEnded` scopes
  - `applyRegistrationWindow` / `applyAvailabilityWindow`: overlap-aware date range filtering (snapshot or relationship mode)
  - `applyNearCapacity(float $threshold = 0.8)`: snapshot `max_capacity_utilization >= threshold` vs raw ratio on `(enrolled_count + reserved_count) / capacity`

### ProductListing (`app/Query/ProductListing.php`)
- **Purpose:** Static facade over shared listing scopes and sorting used across listing/search entry points
- **Methods:** `forListing()` / `forDetail()` delegate to Product scopes; `sortBy` validates field against `ProductSortFieldEnum::ALLOWED` and routes `capacity_utilization` / `price` to dedicated paths; `sortByCapacityUtilization`; `popular` (orderItems count); `paginate` (with query string preservation)

### ProductSearch (`app/Services/ProductSearch.php`)
- **Purpose:** Product listing/search orchestrator with Typesense → database graceful fallback
- **Methods:**
  - `search(ProductListRequestData $requestData): LengthAwarePaginator`: Entry point; uses Scout/Typesense when available (with warning-logged fallback on exceptions), otherwise database pipeline
  - `searchDatabase(ProductListRequestData $requestData)`: Applies `ProductAvailabilityFilter` chain, `ProductListing::forListing`, category/price/difficulty/fulfillment filters, availability filters, scoring + `orderByScore()` for queries, price/capacity sort paths
  - `searchScout(ProductListRequestData $requestData)`: Typesense query via `Product::scoutSearch()` with filterable fields (`category_slugs`, `difficulty_level`, `fulfillment_types`, price range, `has_discount`, `max_capacity_utilization`) and timestamp-based availability filters (`*_ts` fields)
- **Typesense gating:** `config('scout.driver') === 'typesense'` with a non-empty API key and not running unit tests; injectable closure override for tests

### GlobalSearchService (`app/Services/GlobalSearchService.php`)
- **Purpose:** Multi-model search façade that unifies products and blog posts with Scout, Typesense, and SQL fallbacks
- **Public Methods:**
  - `search(SearchData $searchData): LengthAwarePaginator`: Performs union searches with optional Typesense multi-search, hydrates models in the returned order, and logs analytics
  - `suggest(string $query, int $limit = 5): array`: Returns SWR-cached autosuggest strings leveraging Typesense when available
- **Implementation Notes:** Automatically builds faceted filters from `ProductFilterData`, respects `result_types`, and streams results through DTO transformers in controllers

### SWRCacheService (`app/Services/SWRCacheService.php`)
- **Purpose:** Provides Stale-While-Revalidate caching helpers on top of SmartCache
- **Public Methods:**
  - `remember(string $key, Closure $callback, int $freshSeconds = 300, int $staleSeconds = 900)`: Core SWR wrapper returning fresh or stale payloads while refreshing asynchronously
  - `rememberHomepageContent(string $key, Closure $callback)`: Preset for homepage fragments (5 min fresh / 15 min stale)
  - `rememberHomepageContent(string $key, Closure $callback)`: Preset for homepage fragments (5 min fresh / 15 min stale); keys encode filter hashes (e.g., student story course/category slug combos) with wildcard invalidation support to keep variant caches consistent.
  - `rememberSearchSuggestions(string $key, Closure $callback)`: Preset for search autocomplete (1 hour fresh / 4 hours stale)
  - `rememberTrendingContent(string $key, Closure $callback)`: Preset for trending widgets (10 min fresh / 30 min stale)
- **Usage:** Powers search suggestions and homepage listings to balance freshness with perceived performance

### CacheInvalidationService (`app/Services/CacheInvalidationService.php`)
- **Purpose:** Central cache eviction utility invoked by `InvalidationObserver`
- **Public Method:**
  - `invalidateForModel(string|Model $model, array $invalidationConfig): void`: Iterates configured keys/patterns, calling `SmartCache::forget()` and `SmartCache::flushPatterns()` with exception-safe logging
- **Configuration:** Consumes `config/cache_invalidation.php` entries that mix `CacheKeysEnum` values, literal keys, and wildcard patterns (e.g., StudentStory flushes `student_stories:*` variants whenever testimonials change)

### PgroongaService (`app/Services/PgroongaService.php`)
- **Purpose:** Lightweight helper to detect PGroonga availability on PostgreSQL connections
- **Public Method:**
  - `isPgroongaEnabled(): bool`: Cached probe that inspects `pg_extension` and gracefully handles connection failures, allowing search macros to choose the correct strategy

### SettingsService (`app/Services/SettingsService.php`)
- **Purpose:** SmartCache-backed facade over `Setting` models powering CMS content payloads and integration credentials
- **Public Methods:**
  - `get(SettingKeyEnum $key, mixed $default = null): mixed`: Reads a single setting from cached collection. Skips `witImages()` for integration keys (SKIP_MEDIA optimization — IMS, Moodle, BBB, SpotPlayer) to avoid unnecessary media queries. Automatically tries decryption of registered secret fields via `Crypt::decryptString()` on read.
  - `set(SettingKeyEnum $key, mixed $value): bool`: Persists value. Encrypts registered secret fields via `Crypt::encryptString()` before write. Preserves existing secrets when `***REDACTED***` placeholder is sent. Creates audit log entries for integration key writes via `SettingSecretRedactor`.
  - `forget(): void`: Exposes cache invalidation hook used by observers/actions to refresh settings payloads
- **SKIP_MEDIA Optimization:** Four integration keys (IMS, Moodle, BBB, SpotPlayer) skip `witImages()` media hydration since they store credentials, not content with media references
- **Encryption on Write:** Secret fields defined by `SettingKeyEnum::secretFields()` are encrypted at rest using Laravel's `Crypt::encryptString()`
- **Decryption on Read:** Encrypted values are transparently decrypted when retrieved via `get()`, with graceful fallback for legacy plaintext
- **Audit Logging:** Integration setting writes are logged via `AdminActionLog` with secrets redacted, risk level "high"
- **Implementation Notes:** Caches the full settings collection forever using `SmartCache` keyed by `CacheKeysEnum::Settings`, ensuring single query hydration per deploy cycle

## Observers, Events & Async Processing

### SettingObserver (`app/Observers/SettingObserver.php`)
- **Purpose:** Clears settings cache on Setting model save/delete events.
- **Mechanism:** Calls `SettingsService::forget()` on `saved` and `deleted` events to keep cached setting payloads consistent.

### InvalidationObserver (`app/Observers/InvalidationObserver.php`)
- **Purpose:** Global Eloquent observer that translates model save/delete events into cache invalidations.
- **Mechanism:** Reads `config/cache_invalidation.php` to map model classes (Product, Slider, Partner, HomePageBlock, Setting, etc.) to lists of `CacheKeysEnum`, literal keys, or wildcard patterns and delegates eviction to `CacheInvalidationService` (`SmartCache::forget` + `flushPatterns`).
- **Usage:** Registered for multiple CMS/content models to keep SmartCache payloads (home page content, partner lists, settings, good-for-start lists) fresh without manual cache calls.

### ProductableAvailabilityObserver (`app/Observers/ProductableAvailabilityObserver.php`)
- **Purpose:** Keeps availability snapshots and search index in sync when productable (Course/Seminar/DigitalAsset) content changes
- **Mechanism:** On `updated`:
  - If `status` changed (either direction — PUBLISHED↔non-published both flip availability): dispatches `ProductAvailabilityCacheInvalidated` for all linked product IDs
  - If any searchable field changed (`full_name`, `short_name`, `description`, `difficulty_level`, `slug`): dispatches `ProductSearchIndexInvalidated` for linked product IDs

### TermAvailabilityObserver (`app/Observers/TermAvailabilityObserver.php`)
- **Purpose:** Recomputes availability when a Term's status flips
- **Mechanism:** On `updated` with a `status` change (ACTIVE↔non-active both flip availability), chunks all products linked via the term and dispatches `ProductAvailabilityCacheInvalidated` per chunk

### CategorySearchIndexObserver (`app/Observers/CategorySearchIndexObserver.php`)
- **Purpose:** Re-indexes products when a Category slug changes (slug is a search filter field)
- **Mechanism:** On `updated` with a `slug` change, chunks linked products and dispatches `ProductSearchIndexInvalidated` per chunk

### Availability & Search Invalidation Events
- `ProductAvailabilityCacheInvalidated` (`app/Events/ProductAvailabilityCacheInvalidated.php`): carries `productIds`; dispatched after DB commit (`ShouldDispatchAfterCommit`); listeners invalidate availability snapshot caches so storefront availability reflects term/productable status changes
- `ProductSearchIndexInvalidated` (`app/Events/ProductSearchIndexInvalidated.php`): carries `productIds`; dispatched after DB commit; listener runs `SynchronizeProductSearchIndexJob` to push/remove products from the Typesense index

### Review Aggregation Pipeline
- **Event:** `ReviewableAggregatesChanged` (`app/Events/ReviewableAggregatesChanged.php`) carries the reviewable ID/type whenever reviews change.
- **Listener:** `RecalculateReviewableAggregates` (`app/Listeners/RecalculateReviewableAggregates.php`) runs on the queue, filters to models using the `HasReview` trait, and recomputes `review_count` & `average_rating` from approved reviews.
- **Impact:** Keeps course/seminar/digital asset review snapshots synchronized for storefront queries without heavy joins.

### Product Price Cache Refresh
- **Event:** `ProductCacheInvalidated` (`app/Events/ProductCacheInvalidated.php`) is dispatched when pricing-sensitive data mutates.
- **Listener:** `QueueProductPriceCacheUpdate` (`app/Listeners/QueueProductPriceCacheUpdate.php`) asynchronously dispatches `UpdateProductPriceCacheJob` with the affected product ID.
- **Job:** `UpdateProductPriceCacheJob` (`app/Jobs/UpdateProductPriceCacheJob.php`) recalculates price data via `ProductPriceService`, persists it to `price_data_cache`, and clears related SmartCache keys per the invalidation map.
- **Result:** Ensures shop endpoints read precomputed pricing snapshots while remaining consistent after admin edits.

### FullTextSearchProvider (`app/Providers/FullTextSearchProvider.php`)
- **Purpose:** Registers database-agnostic full-text search macros for Eloquent builders.
- **Macros:**
  - `fullTextSearch(array|string $columns, string $value, ?string $scoreAs = null)`: Chooses PGroonga, native PostgreSQL, MySQL MATCH AGAINST, or LIKE fallbacks, optionally selecting a score column
  - `orFullTextSearch(...)`: Convenience wrapper for grouped OR full-text clauses
  - `orderByScore(string $column = 'score', string $direction = 'desc')`: Adds score ordering when PGroonga is active
  - `selectScore(string $column = 'score', string $table = '')`: Appends score selection for PGroonga-powered queries
- **Dependency:** Uses `PgroongaService::isPgroongaEnabled()` to determine when advanced scoring is available; defaults to no-op ordering otherwise.

### Payment Services (`app/Services/Payment/`)

#### PaymentTransactionReferenceService (`app/Services/PaymentTransactionReferenceService.php`)
- **Purpose:** Generates unique numeric payment transaction references with concurrency safety
- **Method:**
  - `generate(): string`: Uses DB row-locking (`lockForUpdate()`) on the latest `PaymentTransaction` row to calculate the next sequential number. Starts from `config('payments.transaction_reference.start_from')` (default: 200000001). Returns as string.
- **Usage:** Injected into all payment processors (Mellat, Wallet, BankTransfer) for unified transaction reference generation.

#### PaymentProcessorContract (`app/Contracts/Payment/PaymentProcessorContract.php`)
- **`process(Payment $payment): PaymentProcessResultData`**: Processes a pre-created Payment record. Payment is created by `PreparePendingPaymentAction` before processing. Returns redirect info or completion result.
- **`verify(Payment $payment, array $callbackData): Payment`**: Verifies payment after gateway callback.

### OtpManagerService (`app/Services/OtpManagerService.php`)
- **Purpose:** Manages OTP generation, validation, and delivery
- **Public Methods:**
  - `generateOtp(string $identifier): string`: Creates time-limited OTP codes
  - `validateOtp(string $identifier, string $otp): bool`: Validates submitted OTP codes
  - `resendOtp(string $identifier): void`: Handles OTP resending with rate limiting
- **Hardening:**
  - Rate limiting per identifier (resend wait time from `config('otp.waiting_time')`, default 10 seconds)
  - OTP validity window (`ttl_seconds`, default 300) and successful-use marker TTL (`marker_ttl_seconds`, default 900)
  - OTP codes are one-time-use; successful validation invalidates the code and records a marker preventing immediate regeneration
  - Resend limits enforced via `Illuminate\Support\RateLimiter`

### InsufficientWalletBalanceException (`app/Exceptions/Payment/InsufficientWalletBalanceException.php`)
- **Purpose:** Domain exception for wallet balance validation failures
- **Properties:** `availableBalance`, `requiredBalance`, `shortfall`, `orderIncrementId` (nullable)
- **Thrown by:** `WalletPaymentProcessor::process()` when wallet balance is insufficient for payment amount
- **Message:** Localized via `validation.custom.insufficient_wallet_balance` language key
- **Response Metadata:** Includes `error_code`, `available_balance`, `required_balance`, `shortfall`, and `order_id` (when `orderIncrementId` is set) so frontend can retry the failed payment.

### DefaultOtpGenerator (`app/Services/DefaultOtpGenerator.php`)
- **Purpose:** Default implementation of OTP generation
- **Public Methods:**
  - `generate(): string`: Generates random OTP codes
  - `setLength(int $length): self`: Sets OTP code length

### IpPanelSmsService (`app/Services/IpPanelSmsService.php`)
- **Purpose:** SMS delivery service integration
- **Public Methods:**
  - `sendSms(string $phone, string $message): bool`: Sends SMS messages via IP Panel service
  - `sendOtpSms(string $phone, string $otp): bool`: Specialized OTP SMS delivery

### ResponseService (`app/Services/ResponseService.php`)
- **Purpose:** Centralized API response builder (`apiResponse()->success()`, etc.)
- **Methods:**
  - `success(mixed $data = null, string $message = 'Success', int $code = 200, array $extra = []): JsonResponse` — wraps data in standardized `{data, message, ...extra}` envelope
  - `created(mixed $data = null, string $message = 'Created'): JsonResponse` — shorthand for 201 responses
  - `noContent(): JsonResponse` — 204 No Content
  - `error(string $message, int $code = 400, ?array $errors = null): JsonResponse` — standardized error responses with optional field-level error map
  - `notFound(?string $message = null): JsonResponse` — 404 shorthand
  - `forbidden(?string $message = null): JsonResponse` — 403 shorthand
  - `unauthenticated(?string $message = null): JsonResponse` — 401 shorthand
- **Usage:** All controllers use this service (via `apiResponse()` helper).

## Middleware (`app/Http/Middleware/`)

### AdminAuditMiddleware (`app/Http/Middleware/AdminAuditMiddleware.php`)
- **Purpose:** Comprehensive audit trail for all admin actions, writing `AdminActionLog` records with rich metadata
- **Mechanism:**
  - Snapshots `auth('staff')->id()` before the request runs so endpoints that mutate auth state (logout, token revocation) still resolve the correct actor
  - Skips logging for: `*.index` routes, `admin.select-option.*`, health/status endpoints, GET requests (only POST/PUT/PATCH/DELETE are logged)
  - Resolves resource info from model-bound route parameters (implicit binding covers every model); falls back to explicit param→model mappings (user, wallet, walletCampaign, staff, category, course, teacher, term, seminar, discountPromotion)
  - Action types: `deposit`, `withdrawal`, `adjustment`, `allocation` for wallet routes; CRUD `create`/`bulk_create`/`update`/`delete`/`view` otherwise
- **Risk assessment:** `high` for 5xx responses, all DELETEs, and wallet amounts > 10M Toman; `medium` for wallet amounts > 1M Toman, bulk operations, and actions outside business hours (7 AM–10 PM); `low` otherwise
- **Redaction:** Recursively sanitizes request data (any depth) replacing sensitive keys (`password`, `password_confirmation`, `current_password`, `new_password`, `token`, `api_key`, `secret`) with `[REDACTED]`; uploaded files logged as `[FILE: name]`; payloads over 10KB truncated
- **Metadata:** execution time (ms), memory usage, timestamp, request/response sizes
- **Failure safety:** Logging errors are caught and logged (never break the request)

### EnsureAdminNumericIdsMiddleware (`app/Http/Middleware/EnsureAdminNumericIdsMiddleware.php`)
- **Purpose:** Prevents non-numeric IDs on admin routes from triggering DB queries with invalid primary keys
- **Mechanism:** For `api/v1/admin/*` routes, inspects each model type-hinted route parameter; if the parameter binds to the primary key of an integer-keyed model and the value is non-numeric, aborts with 404 before any database query runs

### ProfileCheckMiddleware (`app/Http/Middleware/ProfileCheckMiddleware.php`)
- **Purpose:** Enforces customer profile completion for protected actions
- **Mechanism:** When an authenticated customer has an incomplete profile (`! User::profileCompleted()` — requires `is_profile_completed` flag AND civil ID), returns 403 with localized message and `error_code: PROFILE_INCOMPLETE`; unauthenticated users pass through

## Traits & Utilities

### FakeMediaTrait (`tests/Support/Traits/FakeMediaTrait.php`)
- **Purpose:** Reusable test trait that seeds real uploaded media files from `resources/seed-media/` into the database
- **Behavior:** Copies seed media files to `public/fake-media/`, imports via `MediaUploader::importPath()`, creates 8 media entries (3 video, 5 image variants) for use in feature tests
- **Usage:** Applied in test classes that need real media attachments rather than mock IDs

### ProvisionEnrollmentProviderJob (`app/Jobs/Provisioning/ProvisionEnrollmentProviderJob.php`)
- **Purpose:** Generic canonical provisioning worker — starts a `ProvisioningAttempt`, resolves the provider adapter via `ProvisioningProviderRegistry`, runs `provision()` or `reconcileAccess()`, then records success/failure through `ProvisioningAttemptService`.

### HasMedia Trait — `getAllMedia()` 
- **Method:** `getAllMedia(bool $urlOnly = false, array $onlyTags = []): array` — optional `$onlyTags` parameter filters media by specific tags (e.g., BlogPostCardData uses cover only).

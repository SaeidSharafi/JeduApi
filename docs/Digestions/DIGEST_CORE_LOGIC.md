# Digest: Core Business Logic (Actions/Services)

## Actions Pattern (`app/Actions/`)

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

### Console Commands (`app/Console/Commands/`)

#### PublishPostCommand (`app/Console/Commands/PublishPostCommand.php`)
- **Purpose:** Automated blog post publication for scheduled content
- **Signature:** `post:publish`
- **Functionality:** Publishes blog posts with SCHEDULED status where `published_at` date has passed, updating status to PUBLISHED
- **Usage:** Intended for cron job scheduling to automate content publication workflow

#### CheckStuckPaymentsCommand (`app/Console/Commands/CheckStuckPaymentsCommand.php`)
- **Purpose:** Detects payments stuck in PENDING state with initiated but uncompleted transactions beyond threshold
- **Signature:** `payments:check-stuck {--threshold=30}`
- **Functionality:** Queries payments where latest transaction is `INITIATED` longer than threshold minutes ago without `completed_at`. Logs warnings with payment ID, order info, transaction reference, and stuck duration for manual review.
- **Usage:** Intended for cron scheduling (e.g., every 15 minutes) to alert support of abandoned gateway payments.

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
  - `handle(OrderCreateData $data): Order`: Delegates all totals to `OrderCalculationService`, locks delivery options while validating requested payment types/quantities against live capacity (`enrolled_count`), **validates registration window (`registration_start_date`/`registration_end_date`) and availability window (`available_from`/`available_to`)**, snapshots product data per item, seeds enrollments in `AWAITING_PAYMENT`, and increments promotion usage counts when coupon-driven contexts are present
  - `handle(OrderUpdateData $data, Order $order): Order`: Updates existing order details and status
  - `handle(Order $order): void`: Handles order deletion and cleanup
- **ApproveOrderAction** (`app/Actions/Admin/Order/ApproveOrderAction.php`)
  - `handle(Order $order): Order`: Manually approves order for fulfillment/provisioning. Validates: order not already completed/cancelled/refunded, sufficient payment coverage (considering prepayment amounts per item). Transactionally marks order as COMPLETED, completes each item, triggers enrollment provisioning via `OrderStatusService`. Permission-gated via `OrderPolicy::approve()` using `PermissionEnum::ORDER_APPROVE`.

#### Payment Actions (`app/Actions/Admin/Payment/`)
- **CreatePaymentAction** (`app/Actions/Admin/Payment/CreatePaymentAction.php`)
  - `handle(PaymentCreateData $data): Payment`: Processes payment applications to orders
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
- **CreateWalletAction**: Initializes new user wallets
- **GetWalletBalanceAction**: Retrieves current wallet balance and history
- **DepositToWalletAction**: Adds credits to user wallets
- **WithdrawFromWalletAction**: Removes credits from user wallets
- **WalletTransactionAction**: Handles wallet credit/debit operations

#### WalletCampaign Actions (`app/Actions/Admin/WalletCampaign/`)
- **CampaignAllocationAction**: Manages bulk wallet credit campaigns
- **CreateWalletCampaignAction**: Sets up new wallet campaigns
- **UpdateWalletCampaignAction**: Modifies campaign parameters
- **DeleteWalletCampaignAction**: Cancels and cleans up campaigns

#### Staff Actions (`app/Actions/Admin/Staff/`)
- **CreateStaffAction**: Creates new admin user accounts
- **UpdateStaffAction**: Updates staff details and permissions
- **DeleteStaffAction**: Removes staff access and archives records

#### User Actions (`app/Actions/Admin/User/`)
- **CreateUserAction**: Creates new customer accounts
- **UpdateUserAction**: Updates customer profile information
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

#### Role Actions (`app/Actions/Admin/Role/`)
- **CreateRoleAction**: Creates new permission roles
- **UpdateRoleAction**: Modifies role permissions
- **DeleteRoleAction**: Removes unused roles
- **OutputPermissionsAction**: Generates permission reports

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
- **CreateRefundAction**: Processes refund requests
- **UpdateRefundAction**: Updates refund status and details
- **DeleteRefundAction**: Cancels refund requests

#### Audit Actions (`app/Actions/Admin/Audit/`)
- **DetectSuspiciousActivityAction**: Analyzes admin actions for security risks
- **GenerateComplianceReportAction**: Creates audit and compliance reports

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

#### Checkout & Payment Actions (`app/Actions/Shop/*`)
- **CreateOrderFromCartAction** (`app/Actions/Shop/CreateOrderFromCartAction.php`)
  - `handle(CheckoutData $checkoutData, User $user): PaymentProcessResultData`: Wraps the entire checkout pipeline—loads/validates the active cart (capacity, **registration window, availability window**, publication, duplicate ownership, order velocity), converts it into `OrderCreateData`, reuses `CreateOrderAction`, and then dispatches the selected payment processor. Returns redirect info for multi-step gateways or finalizes wallet/no-payment flows before clearing the cart.
- **RetryOrderPaymentAction** (`app/Actions/Shop/RetryOrderPaymentAction.php`)
  - `handle(Order $order, PaymentMethodEnum $method, ?int $amount = null): PaymentProcessResultData`: Allows customers to retry failed/pending orders, validating outstanding balance and order status before reissuing a processor-specific payment request (partial amounts supported via `amount`).
- **VerifyPaymentAction** (`app/Actions/Shop/Payment/VerifyPaymentAction.php`)
  - `handle(GatewayCallbackData $data): Payment`: Locks the pending payment by UUID, resolves the correct processor via `PaymentProcessorFactory`, and delegates gateway-specific verification/settlement workflows (Mellat, etc.), surfacing validation errors if the payment is no longer pending.
- **CancelOrderByCustomerAction** (`app/Actions/Shop/Order/CancelOrderByCustomerAction.php`)
  - `execute(Order $order, int $userId): Order`: Allows customers to cancel their own pending orders. Validates: order belongs to user, order is PENDING, no completed payments exist. Transactionally sets order to CANCELLED, cancels associated enrollments (fires model events for `enrolled_count` tracking via `each()` + `save()`).

### Auth Actions (`app/Actions/Auth/`)
- **GenerateOtpAction** (`app/Actions/Auth/GenerateOtpAction.php`)
  - `handle(string $identifier): string`: Creates time-limited verification codes
- **InitiateAuthAction** (`app/Actions/Auth/InitiateAuthAction.php`)
  - `handle(AuthInitiateData $data): AuthResponseData`: Starts authentication process for both guards
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

### Wallet Actions (`app/Actions/Wallet/`)
- **RecordWalletTransactionAction**: Records all wallet transaction activities

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
  - `verify()`: Loads latest transaction for the payment. Maps `ResCode` to error messages for failures. On success (`ResCode === '0'`): performs `bpVerifyRequest` + `bpSettleRequest`. Both must succeed before marking transaction as COMPLETED and dispatching `PaymentCompletedEvent`. Failure at any step (verification fail, settlement fail, SOAP fault) updates transaction to FAILED with error details, error codes, timestamps. Settlement code 45 (already settled) treated as success.
  - **Transaction Lifecycle:** Every gateway interaction creates a `PaymentTransaction` record tracking `initiated_at`, `completed_at`, `gateway_request`, `gateway_response`, `error_code`, `error_message`. This provides full audit trail per payment attempt.

### SoapClientFactory (`app/Services/Payment/SoapClientFactory.php`)
- **Purpose:** Minimal helper that instantiates `SoapClient` instances from remote or local WSDL endpoints; wrapped to simplify mocking in unit tests

### OrderStatusService (`app/Services/OrderStatusService.php`)
- **Purpose:** Centralized order and enrolment status management with provisioning trigger awareness
- **Public Methods:**
  - `handlePaymentCompletion(Order $order): void`: Reads `config('order.provisioning.trigger')` to determine auto-provisioning behavior:
    - `any_payment` (default): Immediately completes items/enrollments (same as previous behavior)
    - `full_payment`: Provisions only when `balance_due <= 0`
    - `manual_approval`: Never auto-provisions — sets order to PROCESSING instead, requiring staff to call `ApproveOrderAction`
  - `updateEnrollmentStatus(OrderItem $item): void`: Updates enrolment access based on order item status changes (completed items move enrolments into `PENDING_PROVISIONING`). Now uses `save()` instead of `saveQuietly()` to fire model events for `enrolled_count` synchronization.
  - `completeOrderItemAfterPayment(OrderItem $item): void`: Internal method for item-level status updates
  - `updateParentOrderStatus(Order $order): void`: Determines parent order status from collective item states: all refunded → REFUNDED, all cancelled → CANCELLED, any refunded → PARTIALLY_REFUNDED, all completed → COMPLETED, default → PROCESSING

### CartService (`app/Services/CartService.php`)
- **Purpose:** Single façade for cart lifecycle management across authenticated and guest flows
- **Key Capabilities:**
  - `findOrCreateCart(?User $user = null): Cart`: Resolves carts via the `CartIdentifier` contract (user or guest token) and eagerly loads delivery options/products
  - `addItem`, `updateItem`, `removeItem`: Validate capacity/payment type constraints before mutating cart rows
  - `applyCoupon(ApplyCouponData $data): CartData`: Validates coupon codes via `PromotionFinder`, tracks them on the cart, and recalculates totals through `OrderCalculationService`
  - `buildCartDataWithTotals(Cart $cart): CartData`: Hydrates DTOs with current pricing/discount context for API responses
- **Special Notes:** Enforces an order velocity limit (5 orders/hour) during checkout and delegates cart persistence cleanup post-successful conversion

### RequestCartIdentifier (`app/Services/Cart/RequestCartIdentifier.php`)
- **Purpose:** HTTP-scoped implementation of `CartIdentifier` that decides whether to use an authenticated user ID or a persistent guest token (via `X-Guest-Token` header)
- **Responsibilities:** Exposes `userId()`, `guestToken()`, and `ensureGuestToken()` helpers used by `CartService` and middleware to keep carts consistent across sessions. Refactored from singleton to scoped binding — no longer caches auth state at construction; `userId()` and `guestToken()` check auth on each call, ensuring dynamic auth state reflection across a request lifecycle. `isGuest()` delegates directly to `auth->check()`.

### Discount Services (`app/Services/Discounts/`)

#### OrderCalculationService (`app/Services/Discounts/OrderCalculationService.php`)
- **Purpose:** Comprehensive discount and pricing calculation engine integrated with ProductPriceService
- **Public Methods:**
  - `calculate(OrderCreateData $data): OrderContextData`: Applies all discount rules, promotions, and coupons to order data, uses ProductPriceService for consistent pricing hierarchy
  - `validateDiscountEligibility(): bool`: Checks discount rule conditions
  - `applyDiscountActions(): array`: Executes discount actions (percentage, fixed amount, etc.)
- **Dependencies:** `ProductPriceService` for standardized pricing calculations

#### DiscountHandlerRegistry (`app/Services/Discounts/DiscountHandlerRegistry.php`)
- **Purpose:** Registry pattern for discount rule handlers
- **Public Methods:**
  - `registerHandler(string $type, DiscountHandlerInterface $handler): void`: Registers new discount handlers
  - `getHandler(string $type): DiscountHandlerInterface`: Retrieves appropriate handler for discount type

#### DiscountMetadataService (`app/Services/Discounts/DiscountMetadataService.php`)
- **Purpose:** Manages discount rule metadata and configuration
- **Public Methods:**
  - `getAvailableConditions(): array`: Returns available discount conditions
  - `getAvailableActions(): array`: Returns available discount actions
  - `validateRuleConfiguration(array $rules): bool`: Validates discount rule syntax

#### ProductDiscountIndexer (`app/Services/Discounts/ProductDiscountIndexer.php`)
- **Purpose:** Indexes products for efficient discount application
- **Public Methods:**
  - `indexProduct(Product $product): void`: Adds product to discount index
  - `reindexAll(): void`: Rebuilds complete discount index

#### ProductDiscountPriceCalculator (`app/Services/Discounts/ProductDiscountPriceCalculator.php`)
- **Purpose:** Calculates discounted prices for individual products
- **Public Methods:**
  - `calculateDiscountedPrice(Product $product, User $user = null): float`: Calculates final price after discounts
  - `getApplicableDiscounts(Product $product): Collection`: Returns all applicable discounts for product

#### PromotionFinder (`app/Services/Discounts/PromotionFinder.php`)
- **Purpose:** Finds and matches promotions to orders and products
- **Public Methods:**
  - `findApplicablePromotions(Order $order): Collection`: Finds promotions applicable to order; now enforces `usage_limit_total` — excludes promotions where `total_usage_count >= usage_limit_total` (Gap #2 fix)
  - `findBestPromotion(Product $product): ?DiscountPromotion`: Returns best available promotion for product

### Discount Cart Services (`app/Services/Discounts/Cart/`)

#### Cart Actions (`app/Services/Discounts/Cart/Actions/`)
- Contains cart-specific discount application logic

#### Cart Conditions (`app/Services/Discounts/Cart/Conditions/`)
- Contains cart-level discount condition validators

### Discount Product Services (`app/Services/Discounts/Product/`)
- Contains product-level discount calculation services

### Discount Configuration Services (`app/Services/Discounts/Configs/`)
- Contains discount system configuration and rule definitions

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

### SettingSecretRedactor (`app/Services/SettingSecretRedactor.php`)
- **Purpose:** Redacts secret field values from integration setting arrays before API responses and audit logging
- **Methods:**
  - `redact(string $settingKey, mixed $value): mixed`: Replaces known secret field values with `***REDACTED***`
  - `hasSecrets(string $settingKey): bool`: Whether a setting key has any secret fields
- **Secret fields per key:** IMS (`api_key`), Moodle (`token`, `auth_userkey_token`), BBB (`secret`, `default_attendee_password`, `default_moderator_password`), SpotPlayer (`api_key`)
- **Usage:** Applied in `SettingData::fromModel()`, `SettingsService::auditIntegrationWrite()`, and during `set()` for placeholder detection

### Integration Services (`app/Services/Integrations/`)

#### ImsService (`app/Services/Integrations/ImsService.php`)
- **Purpose:** IMS (Internal Management System) REST API client for student & enrollment CRUD operations
- **Methods:**
  - `setConfig(array $config): void`: Injects runtime configuration (credentials, endpoint)
  - `storeStudent(array $payload): array`: Creates student record via POST `/api/v2/student`
  - `storeEnrollment(User $user, array $payload): array`: Creates enrollment record via POST `/api/v2/enrolment/{civil_id}`
- **Security:** PII redaction in logs (email, phone via `sanitizeBody()`); credentials resolved via `SettingsService`

#### MoodleService (`app/Services/Integrations/MoodleService.php`)
- **Purpose:** Moodle Web Services API client for user management, enrollment, grades, and SSO
- **Methods:**
  - `setConfig(array $config): void`: Injects runtime configuration
  - `findOrCreateUser(User $user): array`: Finds or creates Moodle user → returns `[moodleUserId, moodleUsername]`
  - `isCourseCompleted(int $moodleCourseId, int $moodleUserId): bool`: Checks course completion status
  - `getActivityCompletionStatus(int $moodleCourseId, int $moodleUserId): array`: Returns per-activity completion states
  - `getGrades(int $moodleCourseId, int $moodleUserId): array`: Returns course grade + activity-level grades
  - `getCourse(int $moodleCourseId): LmsMoodleBlockData`: Fetches course content structure
  - `enrollUser(int $moodleUserId, int $moodleCourseId, ?int $startTime, ?int $endTime, int $roleId = 5): void`: Manual enrollment
  - `createUserKey(string $username, ?string $token = null): string`: Generates SSO login URL key

#### SpotPlayerService (`app/Services/Integrations/SpotPlayerService.php`)
- **Purpose:** SpotPlayer video platform license provisioning
- **Methods:**
  - `issueLicense(string $spotId, User $user): array`: Issues license → returns `{license_key, player_url, raw}`

#### BbbService (`app/Services/Integrations/BbbService.php`)
- **Purpose:** BigBlueButton API client for meeting management and join URL generation (SHA1 checksum auth)
- **Methods:**
  - `createMeeting(string $meetingId, string $name, ?string $attendeePw, ?string $moderatorPw): void`: Creates BBB meeting
  - `buildJoinUrl(string $meetingId, string $fullName, ?string $password): string`: Generates attendee/moderator join URL

### Provisioning Jobs (`app/Jobs/Provisioning/`)

#### HandlesProvisioningStatus Trait (`app/Jobs/Provisioning/Concerns/HandlesProvisioningStatus.php`)
- **Purpose:** Shared trait for all provisioning jobs providing success/failure marking and provider requirement detection
- `markProvisioningSuccess(Enrollment $enrollment, string $provider, array $data): void`: Updates `provisioning_data` JSONB with provider status
- `markProvisioningFailed(Enrollment $enrollment, string $provider, string $error): void`: Sets provisioning failure state
- `requiresProvisioning(string $provider): bool`: Checks if a provider is enabled via SettingsService

#### ProvisionImsEnrollmentJob (`app/Jobs/Provisioning/ProvisionImsEnrollmentJob.php`)
- **Purpose:** Provisions student + enrollment via IMS. Resolves payment info for IMS bank account mapping. Tries: 3, backoff: [60, 180, 600]s. Creates `AdminActionLog` on failure.

#### ProvisionMoodleEnrollmentJob (`app/Jobs/Provisioning/ProvisionMoodleEnrollmentJob.php`)
- **Purpose:** Finds/creates Moodle user and enrolls in course. Tries: 3, backoff: [60, 180, 600]s.

#### ProvisionSpotPlayerEnrollmentJob (`app/Jobs/Provisioning/ProvisionSpotPlayerEnrollmentJob.php`)
- **Purpose:** Issues SpotPlayer license for a user. Tries: 3, backoff: [60, 180, 600]s.

#### ProvisionBbbEnrollmentJob (`app/Jobs/Provisioning/ProvisionBbbEnrollmentJob.php`)
- **Purpose:** Optionally auto-creates BBB meeting and generates join URL. Tries: 3, backoff: [60, 180, 600]s.

#### SyncMoodleProgressJob (`app/Jobs/Provisioning/SyncMoodleProgressJob.php`)
- **Purpose:** Syncs Moodle course completion, activity statuses, and grades into enrollment `provisioning_data`. Triggered on enrollment detail view (rate-limited to 5-min throttle per enrollment).

### Provisioning Orchestration

#### ProvisionPaidResourcesListener (`app/Listeners/ProvisionPaidResourcesListener.php`)
- **Purpose:** Dispatches provisioning jobs after payment completion based on delivery method
- **Trigger:** Listens on `PaymentCompletedEvent` (queued)
- **Logic:** For each order item with completed status, dispatches jobs based on:
  - `ims_course_code` in details → `ProvisionImsEnrollmentJob`
  - `LMS_MOODLE` delivery → `ProvisionMoodleEnrollmentJob`
  - `VIDEO_PLATFORM_SPOTPLAYER` delivery → `ProvisionSpotPlayerEnrollmentJob`
  - `LIVE_SESSION_BBB` delivery → `ProvisionBbbEnrollmentJob`

#### UpdateStatusesAfterPaymentListener (`app/Listeners/UpdateStatusesAfterPaymentListener.php`)
- **Purpose:** Calls `OrderStatusService::handlePaymentCompletion()` after payment confirmed (synchronous)

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

## Recent Behavior Clarifications

### Gateway Verification Idempotency
- Verification proceeds only when `Payment.status` is `PENDING`.
- Duplicate callbacks against non-pending payments raise a validation error keyed `payment` and do not create additional enrollments/payments.

### Capacity & Concurrency at Checkout
- Capacity is enforced at checkout time; cart additions do not reserve capacity.
- In last-spot race scenarios, the first successful checkout wins; subsequent checkouts receive validation errors on `items.0`.

### Duplicate Ownership Across Options
- Ownership is defined by `(productable_type, productable_id)` and prevents repurchase of the same underlying productable via different delivery options.

### Discounts Evaluation & Counters
- Promotions/coupons are evaluated at checkout time; expired promotions at checkout are ignored even if applied earlier.
- `DiscountPromotion.total_usage_count` and `DiscountCoupon.usage_count` increment only on successful checkout.

### Wallet Insufficient Balance Flow
- Wallet checkout with insufficient funds returns a `wallet_balance` validation error; after top-up, retry completes normally.

### Discount Snapshots on Orders
- `Order.applied_cart_discounts_json` and `OrderItem.applied_discount_details_json` persist the applied discounts as immutable snapshots of checkout state.

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
  - `registrationWindow(Carbon $from, Carbon $to)` / `availabilityWindow(Carbon $from, Carbon $to)`: Overlap-aware date filtering for storefront scheduling needs (now supports direct date range params in addition to existing scope usage)
  - `availabilityStatus(AvailabilityStatusEnum)`: Filters products by temporal state — `PAST` (available_to < now), `UPCOMING` (available_from > now), `ONGOING` (within window). Applied as deferred relationship constraint on `productDeliveryOptions`
  - `nearingCapacity(float $threshold = 0.8)`: Filters to products where at least one delivery option has `enrolled_count / capacity >= threshold`
  - `withoutFullProducts()`: Excludes delivery options where `capacity IS NOT NULL AND capacity <= enrolled_count`
  - `sortByCapacityUtilization(float $threshold = 0.8)`: Uses `LEFT JOIN LATERAL` subquery to compute `max_ratio` and `near_capacity_flag` in a single pass; orders by near-capacity first, then utilization ratio descending. `sortBy` now accepts `capacity_utilization`
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
  - `rememberHomepageContent(string $key, Closure $callback)`: Preset for homepage fragments (5 min fresh / 15 min stale); keys can now encode filter hashes (e.g., student story course/category slug combos) with wildcard invalidation support to keep variant caches consistent.
  - `rememberSearchSuggestions(string $key, Closure $callback)`: Preset for search autocomplete (1 hour fresh / 4 hours stale)
  - `rememberTrendingContent(string $key, Closure $callback)`: Preset for trending widgets (10 min fresh / 30 min stale)
- **Usage:** Powers search suggestions and homepage listings to balance freshness with perceived performance

### CacheInvalidationService (`app/Services/CacheInvalidationService.php`)
- **Purpose:** Central cache eviction utility invoked by `InvalidationObserver`
- **Public Method:**
  - `invalidateForModel(string|Model $model, array $invalidationConfig): void`: Iterates configured keys/patterns, calling `SmartCache::forget()` and `SmartCache::flushPatterns()` with exception-safe logging
- **Configuration:** Consumes `config/cache_invalidation.php` entries that can mix `CacheKeysEnum` values, literal keys, and wildcard patterns (e.g., StudentStory now flushes `student_stories:*` variants whenever testimonials change)

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

### InvalidationObserver (`app/Observers/InvalidationObserver.php`)
- **Purpose:** Global Eloquent observer that translates model save/delete events into cache invalidations.
- **Mechanism:** Reads `config/cache_invalidation.php` to map model classes (Product, Slider, Partner, HomePageBlock, Setting, etc.) to lists of `CacheKeysEnum`, literal keys, or wildcard patterns and delegates eviction to `CacheInvalidationService` (`SmartCache::forget` + `flushPatterns`).
- **Usage:** Registered for multiple CMS/content models to keep SmartCache payloads (home page content, partner lists, settings, good-for-start lists) fresh without manual cache calls.

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

### PaymentProcessorFactory (`app/Services/Payment/PaymentProcessorFactory.php`)
- **Purpose:** Factory pattern for payment processor creation
- **Public Methods:**
  - `create(string $method): PaymentProcessorInterface`: Creates appropriate payment processor
  - `getSupportedMethods(): array`: Returns list of supported payment methods

#### WalletPaymentProcessor (`app/Services/Payment/WalletPaymentProcessor.php`)
- **Purpose:** Processes wallet-based payments with transaction tracking
- **Public Methods:**
  - `processPayment(PaymentData $data): PaymentResult`: Processes wallet payments, creates PaymentTransaction record, validates balance with domain exception
  - `validateWalletBalance(Wallet $wallet, float $amount): bool`: Validates sufficient balance

#### BankTransferPaymentProcessor (`app/Services/Payment/BankTransferPaymentProcessor.php`)
- **Purpose:** Handles bank transfer payment processing
- **Public Methods:**
  - `processPayment(PaymentData $data): PaymentResult`: Processes bank transfer payments, validates bank transfer details for admin-initiated payments
  - `validateBankDetails(array $details): bool`: Validates bank transfer information

### OtpManagerService (`app/Services/OtpManagerService.php`)
- **Purpose:** Manages OTP generation, validation, and delivery
- **Public Methods:**
  - `generateOtp(string $identifier): string`: Creates time-limited OTP codes
  - `validateOtp(string $identifier, string $otp): bool`: Validates submitted OTP codes
  - `resendOtp(string $identifier): void`: Handles OTP resending with rate limiting

### InsufficientWalletBalanceException (`app/Exceptions/Payment/InsufficientWalletBalanceException.php`)
- **Purpose:** Domain exception for wallet balance validation failures
- **Properties:** `availableBalance`, `requiredBalance`, `shortfall`
- **Thrown by:** `WalletPaymentProcessor::process()` when wallet balance is insufficient for payment amount
- **Message:** Localized via `validation.custom.insufficient_wallet_balance` language key

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

## Console Commands (`app/Console/Commands/`)

### PublishPostCommand (`app/Console/Commands/PublishPostCommand.php`)
- **Purpose:** Automated blog post publication for scheduled content
- **Signature:** `post:publish`
- **Functionality:** Publishes blog posts with SCHEDULED status where `published_at` date has passed, updating status to PUBLISHED
- **Usage:** Intended for cron job scheduling to automate content publication workflow

### EncryptSettingSecretsCommand (`app/Console/Commands/EncryptSettingSecretsCommand.php`)
- **Purpose:** One-time migration to encrypt legacy plaintext secret values in integration setting configurations
- **Signature:** `settings:encrypt-secrets {--dry-run}`
- **Functionality:** Targets all four integration keys (IMS, Moodle, BBB, SpotPlayer). Detects already-encrypted values via try-decrypt to ensure idempotency. Dry-run mode previews changes. Busts settings cache after write.
- **Usage:** Run after deployment to ensure all stored secrets are encrypted at rest.

## Traits & Utilities

### FakeMediaTrait (`tests/Support/Traits/FakeMediaTrait.php`)
- **Purpose:** Reusable test trait that seeds real uploaded media files from `resources/seed-media/` into the database
- **Behavior:** Copies seed media files to `public/fake-media/`, imports via `MediaUploader::importPath()`, creates 8 media entries (3 video, 5 image variants) for use in feature tests
- **Usage:** Applied in test classes that need real media attachments rather than mock IDs

### HandlesProvisioningStatus Trait (`app/Jobs/Provisioning/Concerns/HandlesProvisioningStatus.php`)
- **Purpose:** Shared provisioning job logic for success/failure marking and provider detection
- **Methods:** `markProvisioningSuccess()`, `markProvisioningFailed()`, `requiresProvisioning()`

### HasMedia Trait — `getAllMedia()` Enhancement
- **Method:** `getAllMedia(bool $urlOnly = false, array $onlyTags = []): array` — now accepts optional `$onlyTags` parameter to filter media by specific tags instead of returning all tags. Used by BlogPostCardData (cover only) and BlogPostDetailData (all media).

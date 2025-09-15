# Digest: Core Business Logic (Actions/Services)

## Actions Pattern (`app/Actions/`)

### Admin Actions (`app/Actions/Admin/`)

#### Order Actions (`app/Actions/Admin/Order/`)
- **CreateOrderAction** (`app/Actions/Admin/Order/CreateOrderAction.php`)
  - `handle(OrderCreateData $data): Order`: Creates order bills with discount calculations, handles concurrency with pessimistic locking, validates duplicate purchases, creates enrolments
- **UpdateOrderAction** (`app/Actions/Admin/Order/UpdateOrderAction.php`)
  - `handle(OrderUpdateData $data, Order $order): Order`: Updates existing order details and status
- **DeleteOrderAction** (`app/Actions/Admin/Order/DeleteOrderAction.php`)
  - `handle(Order $order): void`: Handles order deletion and cleanup

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
  - `handle(ProductDeliveryOptionCreateData $data): ProductDeliveryOption`: Creates new delivery methods for products
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
- **CreateTeacherAction**: Sets up instructor profiles
- **UpdateTeacherAction**: Updates instructor information and qualifications
- **DeleteTeacherAction**: Removes instructor access

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
- **CreateSliderAction**: Creates new promotional sliders
- **UpdateSliderAction**: Updates slider content and positioning
- **DeleteSliderAction**: Removes sliders

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

#### CollaborationCarousel Actions (`app/Actions/Admin/CollaborationCarousel/`)
- **CreateCollaborationCarouselAction**: Adds new partnership showcases
- **UpdateCollaborationCarouselAction**: Updates partner information
- **DeleteCollaborationCarouselAction**: Removes partnership displays

### Shop Actions (`app/Actions/Shop/`)
- **EnrolmentAccessAction**: Manages customer access to purchased content
- **ProfileUpdateAction**: Handles customer profile updates

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

### OrderStatusService (`app/Services/OrderStatusService.php`)
- **Purpose:** Centralized order and enrolment status management
- **Public Methods:**
  - `handlePaymentCompletion(Order $order): void`: Cascades status updates after payment confirmation, updates order items and parent order status
  - `updateEnrollmentStatus(OrderItem $item): void`: Updates enrolment access based on order item status changes
  - `completeOrderItemAfterPayment(OrderItem $item): void`: Internal method for item-level status updates
  - `updateParentOrderStatus(Order $order): void`: Updates parent order status based on item statuses

### Discount Services (`app/Services/Discounts/`)

#### OrderCalculationService (`app/Services/Discounts/OrderCalculationService.php`)
- **Purpose:** Comprehensive discount and pricing calculation engine
- **Public Methods:**
  - `calculate(OrderCreateData $data): OrderContextData`: Applies all discount rules, promotions, and coupons to order data
  - `validateDiscountEligibility(): bool`: Checks discount rule conditions
  - `applyDiscountActions(): array`: Executes discount actions (percentage, fixed amount, etc.)

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
  - `findApplicablePromotions(Order $order): Collection`: Finds promotions applicable to order
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

### Payment Services (`app/Services/Payment/`)

#### PaymentProcessorFactory (`app/Services/Payment/PaymentProcessorFactory.php`)
- **Purpose:** Factory pattern for payment processor creation
- **Public Methods:**
  - `create(string $method): PaymentProcessorInterface`: Creates appropriate payment processor
  - `getSupportedMethods(): array`: Returns list of supported payment methods

#### WalletPaymentProcessor (`app/Services/Payment/WalletPaymentProcessor.php`)
- **Purpose:** Processes wallet-based payments
- **Public Methods:**
  - `processPayment(PaymentData $data): PaymentResult`: Processes wallet payments
  - `validateWalletBalance(Wallet $wallet, float $amount): bool`: Validates sufficient balance

#### BankTransferPaymentProcessor (`app/Services/Payment/BankTransferPaymentProcessor.php`)
- **Purpose:** Handles bank transfer payment processing
- **Public Methods:**
  - `processPayment(PaymentData $data): PaymentResult`: Processes bank transfer payments
  - `validateBankDetails(array $details): bool`: Validates bank transfer information

### OtpManagerService (`app/Services/OtpManagerService.php`)
- **Purpose:** Manages OTP generation, validation, and delivery
- **Public Methods:**
  - `generateOtp(string $identifier): string`: Creates time-limited OTP codes
  - `validateOtp(string $identifier, string $otp): bool`: Validates submitted OTP codes
  - `resendOtp(string $identifier): void`: Handles OTP resending with rate limiting

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

# Digest: Core Business Logic (Actions/Services)

## Actions Pattern (`app/Actions/`)

### Admin Actions (`app/Actions/Admin/`)

#### Order Actions (`app/Actions/Admin/Order/`)
- **CreateOrderAction** (`app/Actions/Admin/Order/CreateOrderAction.php`)
  - `handle(OrderCreateData $data): Order`: Creates order bills with discount calculations, handles concurrency with pessimistic locking, validates duplicate purchases, creates enrolments
  
#### Payment Actions (`app/Actions/Admin/Payment/`)
- **CreatePaymentAction**: Processes payment applications to orders
- **UpdatePaymentAction**: Handles payment status updates and cascading effects

#### Product Actions (`app/Actions/Admin/Product/`)
- **CreateProductAction**: Creates new sellable products with polymorphic relationships
- **UpdateProductAction**: Updates product details and delivery options

#### Discount Actions (`app/Actions/Admin/Discount/`)
- **DiscountCalculationAction**: Applies complex discount rules and promotions to orders

#### Wallet Actions (`app/Actions/Admin/Wallet/`)
- **WalletTransactionAction**: Handles wallet credit/debit operations
- **CampaignAllocationAction**: Manages bulk wallet credit campaigns

### Shop Actions (`app/Actions/Shop/`)
- **EnrolmentAccessAction**: Manages customer access to purchased content
- **ProfileUpdateAction**: Handles customer profile updates

### Auth Actions (`app/Actions/Auth/`)
- **OtpAuthenticationAction**: Handles OTP-based authentication for both guards
- **PasswordAuthenticationAction**: Manages password-based login flows

## Services Pattern (`app/Services/`)

### OrderStatusService (`app/Services/OrderStatusService.php`)
- **Purpose:** Centralized order and enrolment status management
- **Public Methods:**
  - `handlePaymentCompletion(Order $order): void`: Cascades status updates after payment confirmation, updates order items and parent order status
  - `updateEnrollmentStatus(OrderItem $item): void`: Updates enrolment access based on order item status changes
  - `completeOrderItemAfterPayment(OrderItem $item): void`: Internal method for item-level status updates
  - `updateParentOrderStatus(Order $order): void`: Updates parent order status based on item statuses

### Discount Services (`app/Services/Discounts/`)
- **OrderCalculationService**: Comprehensive discount and pricing calculation engine
  - `calculate(OrderCreateData $data): OrderContextData`: Applies all discount rules, promotions, and coupons to order data
  - `validateDiscountEligibility(): bool`: Checks discount rule conditions
  - `applyDiscountActions(): array`: Executes discount actions (percentage, fixed amount, etc.)

### Payment Services (`app/Services/Payment/`)
- **PaymentGatewayService**: Integration with external payment processors
- **PaymentValidationService**: Validates payment data and status

### OtpManagerService (`app/Services/OtpManagerService.php`)
- **Purpose:** Manages OTP generation, validation, and delivery
- **Public Methods:**
  - `generateOtp(string $identifier): string`: Creates time-limited OTP codes
  - `validateOtp(string $identifier, string $otp): bool`: Validates submitted OTP codes
  - `resendOtp(string $identifier): void`: Handles OTP resending with rate limiting

### IpPanelSmsService (`app/Services/IpPanelSmsService.php`)
- **Purpose:** SMS delivery service integration
- **Public Methods:**
  - `sendSms(string $phone, string $message): bool`: Sends SMS messages via IP Panel service
  - `sendOtpSms(string $phone, string $otp): bool`: Specialized OTP SMS delivery
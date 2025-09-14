# Digest: API Interfaces & Endpoints

## Admin API Interface (`/api/v1/admin/*`)
**Authentication:** `auth:staff` guard with `admin.audit` middleware  
**Response Pattern:** All responses use `spatie/laravel-data` DTOs with custom response macros

### StaffController (`app/Http/Controllers/Api/Admin/StaffController.php`)
- `index()`: **Route:** `GET /api/v1/admin/staff` - **Delegates to:** Staff listing action - **Response DTO:** StaffData collection
- `store(StaffCreateData $request)`: **Route:** `POST /api/v1/admin/staff` - **Request DTO:** StaffCreateData - **Delegates to:** CreateStaffAction - **Response DTO:** StaffData
- `show(Staff $staff)`: **Route:** `GET /api/v1/admin/staff/{staff}` - **Delegates to:** Staff retrieval - **Response DTO:** StaffData
- `update(StaffUpdateData $request, Staff $staff)`: **Route:** `PUT /api/v1/admin/staff/{staff}` - **Request DTO:** StaffUpdateData - **Response DTO:** StaffData
- `destroy(Staff $staff)`: **Route:** `DELETE /api/v1/admin/staff/{staff}` - **Delegates to:** Staff deletion action

### OrderController (`app/Http/Controllers/Api/Admin/OrderController.php`)
- `index()`: **Route:** `GET /api/v1/admin/order` - **Delegates to:** Order listing with filtering - **Response DTO:** OrderData collection
- `store(OrderCreateData $request)`: **Route:** `POST /api/v1/admin/order` - **Request DTO:** OrderCreateData - **Delegates to:** CreateOrderAction::handle() - **Response DTO:** OrderData
- `show(Order $order)`: **Route:** `GET /api/v1/admin/order/{order}` - **Delegates to:** Order retrieval with relationships - **Response DTO:** OrderData
- `update(OrderUpdateData $request, Order $order)`: **Route:** `PUT /api/v1/admin/order/{order}` - **Request DTO:** OrderUpdateData - **Response DTO:** OrderData

### OrderCalculationController (`app/Http/Controllers/Api/Admin/OrderCalculationController.php`)
- `__invoke(OrderCreateData $request)`: **Route:** `POST /api/v1/admin/order/preview` - **Request DTO:** OrderCreateData - **Delegates to:** OrderCalculationService::calculate() - **Response DTO:** OrderContextData

### PaymentController (`app/Http/Controllers/Api/Admin/PaymentController.php`)
- `index(Order $order)`: **Route:** `GET /api/v1/admin/order/{order}/payment` - **Delegates to:** Payment listing for order - **Response DTO:** PaymentData collection
- `store(PaymentCreateData $request, Order $order)`: **Route:** `POST /api/v1/admin/order/{order}/payment` - **Request DTO:** PaymentCreateData - **Delegates to:** CreatePaymentAction - **Response DTO:** PaymentData

### DiscountPromotionController (`app/Http/Controllers/Api/Admin/DiscountPromotionController.php`)
- `index()`: **Route:** `GET /api/v1/admin/discount-promotion` - **Delegates to:** Discount promotion listing - **Response DTO:** DiscountPromotionData collection
- `store(DiscountPromotionCreateData $request)`: **Route:** `POST /api/v1/admin/discount-promotion` - **Request DTO:** DiscountPromotionCreateData - **Response DTO:** DiscountPromotionData
- `show(DiscountPromotion $discountPromotion)`: **Route:** `GET /api/v1/admin/discount-promotion/{discountPromotion}` - **Response DTO:** DiscountPromotionData

### UserController (`app/Http/Controllers/Api/Admin/UserController.php`)
- `index()`: **Route:** `GET /api/v1/admin/user` - **Delegates to:** Customer user listing - **Response DTO:** UserData collection
- `store(UserCreateData $request)`: **Route:** `POST /api/v1/admin/user` - **Request DTO:** UserCreateData - **Response DTO:** UserData
- `show(User $user)`: **Route:** `GET /api/v1/admin/user/{user}` - **Response DTO:** UserData
- `update(UserUpdateData $request, User $user)`: **Route:** `PUT /api/v1/admin/user/{user}` - **Request DTO:** UserUpdateData - **Response DTO:** UserData

### AdminWalletController (`app/Http/Controllers/Api/Admin/Wallet/AdminWalletController.php`)
- `index()`: **Route:** `GET /api/v1/admin/wallet` - **Delegates to:** Wallet management actions - **Response DTO:** WalletData collection
- `store(WalletTransactionData $request)`: **Route:** `POST /api/v1/admin/wallet` - **Request DTO:** WalletTransactionData - **Response DTO:** WalletTransactionData

## Customer API Interface (`/api/v1/*`)
**Authentication:** `auth:user` guard for protected endpoints  
**Public Endpoints:** Available without authentication for browsing

### Auth Endpoints (`/api/v1/auth/*`)
- **InitiateAuthController**: `POST /api/v1/auth/initiate` - **Request DTO:** AuthInitiateData - **Delegates to:** OTP generation - **Response DTO:** AuthResponseData
- **PasswordLoginController**: `POST /api/v1/auth/login/password` - **Request DTO:** PasswordLoginData - **Delegates to:** Password authentication - **Response DTO:** AuthTokenData
- **OtpAuthenticationController**: `POST /api/v1/auth/otp/verify` - **Request DTO:** OtpVerifyData - **Delegates to:** OTP validation - **Response DTO:** AuthTokenData
- **LogoutController**: `POST /api/v1/auth/logout` - **Middleware:** `auth:user` - **Delegates to:** Token revocation

### Shop Protected Endpoints (`/api/v1/shop/*`)
**Middleware:** `auth:user`

#### ProfileController (`app/Http/Controllers/Api/Shop/ProfileController.php`)
- `show()`: **Route:** `GET /api/v1/shop/profile` - **Delegates to:** User profile retrieval - **Response DTO:** UserProfileData
- `update(ProfileUpdateData $request)`: **Route:** `PUT /api/v1/shop/profile` - **Request DTO:** ProfileUpdateData - **Response DTO:** UserProfileData

#### EnrolmentController (`app/Http/Controllers/Api/Shop/MyCourses/EnrolmentController.php`)
- `index()`: **Route:** `GET /api/v1/shop/my-courses` - **Delegates to:** User enrolment listing - **Response DTO:** EnrolmentData collection
- `show(Enrolment $enrolment)`: **Route:** `GET /api/v1/shop/my-courses/{enrolment:uuid}` - **Delegates to:** Enrolment details with access validation - **Response DTO:** EnrolmentData

### Admin Auth Endpoints (`/api/v1/admin/auth/*`)
- **StaffInitiateAuthController**: `POST /api/v1/admin/auth/initiate` - **Request DTO:** StaffAuthInitiateData - **Response DTO:** AuthResponseData
- **StaffPasswordLoginController**: `POST /api/v1/admin/auth/login/password` - **Request DTO:** StaffPasswordLoginData - **Response DTO:** StaffAuthTokenData
- **StaffOtpAuthenticationController**: `POST /api/v1/admin/auth/otp/verify` - **Request DTO:** StaffOtpVerifyData - **Response DTO:** StaffAuthTokenData
- **StaffLogoutController**: `POST /api/v1/admin/auth/logout` - **Middleware:** `auth:staff` - **Delegates to:** Staff token revocation

## Route Organization Pattern
- **Base Routes:** `/api/v1/api.php` includes all interface route files
- **Admin Routes:** `/api/v1/admin.php` - Complete platform management with `auth:staff` + `admin.audit`
- **Customer Routes:** `/api/v1/customer.php` - Protected customer operations with `auth:user`
- **Public Routes:** `/api/v1/shop.php` - Public browsing endpoints
- **Auth Routes:** `/api/v1/auth.php` - Dual authentication system for both interfaces
- **Select Options:** `/api/v1/select_option.php` - Dropdown/select data endpoints

# Digest: API Interfaces & Endpoints

## Admin API Interface (`/api/v1/admin/*`)
**Authentication:** `auth:staff` guard with `admin.audit` middleware  
**Response Pattern:** All responses use `spatie/laravel-data` DTOs via `ResponseService`.
**Scribe Response Files:** Stored in `resources/responses/` (version-controlled). All `@responseFile` paths reference this directory.

### StaffController (`app/Http/Controllers/Api/Admin/StaffController.php`)
- `index()`: **Route:** `GET /api/v1/admin/staff` - **Delegates to:** Staff listing action - **Response DTO:** StaffData collection
- `store(StaffCreateData $request)`: **Route:** `POST /api/v1/admin/staff` - **Request DTO:** StaffCreateData - **Delegates to:** CreateStaffAction - **Response DTO:** StaffData
- `show(Staff $staff)`: **Route:** `GET /api/v1/admin/staff/{staff}` - **Delegates to:** Staff retrieval - **Response DTO:** StaffData
- `update(StaffUpdateData $request, Staff $staff)`: **Route:** `PUT /api/v1/admin/staff/{staff}` - **Request DTO:** StaffUpdateData - **Response DTO:** StaffData
- `destroy(Staff $staff)`: **Route:** `DELETE /api/v1/admin/staff/{staff}` - **Delegates to:** Staff deletion action

### RoleController (`app/Http/Controllers/Api/Admin/RoleController.php`)
- `index()`: **Route:** `GET /api/v1/admin/roles` - **Delegates to:** Role listing - **Response DTO:** RoleData collection
- `store(RoleCreateData $request)`: **Route:** `POST /api/v1/admin/roles` - **Request DTO:** RoleCreateData - **Response DTO:** RoleData
- `show(Role $role)`: **Route:** `GET /api/v1/admin/roles/{role}` - **Response DTO:** RoleData
- `update(RoleUpdateData $request, Role $role)`: **Route:** `PUT /api/v1/admin/roles/{role}` - **Request DTO:** RoleUpdateData - **Response DTO:** RoleData
- `destroy(Role $role)`: **Route:** `DELETE /api/v1/admin/roles/{role}` - **Delegates to:** Role deletion

### PermissionController (`app/Http/Controllers/Api/Admin/PermissionController.php`)
- `index()`: **Route:** `GET /api/v1/admin/permissions` - **Delegates to:** Permission listing - **Response DTO:** PermissionData collection

### VendorController (`app/Http/Controllers/Api/Admin/VendorController.php`)
- `index()`: **Route:** `GET /api/v1/admin/vendors` - **Delegates to:** Vendor listing - **Response DTO:** VendorData collection
- `store(VendorCreateData $request)`: **Route:** `POST /api/v1/admin/vendors` - **Request DTO:** VendorCreateData - **Response DTO:** VendorData
- `show(Vendor $vendor)`: **Route:** `GET /api/v1/admin/vendors/{vendor}` - **Response DTO:** VendorData
- `update(VendorUpdateData $request, Vendor $vendor)`: **Route:** `PUT /api/v1/admin/vendors/{vendor}` - **Request DTO:** VendorUpdateData - **Response DTO:** VendorData
- `destroy(Vendor $vendor)`: **Route:** `DELETE /api/v1/admin/vendors/{vendor}` - **Delegates to:** Vendor deletion

### TeacherController (`app/Http/Controllers/Api/Admin/TeacherController.php`)
- `index()`: **Route:** `GET /api/v1/admin/teachers` - **Delegates to:** Teacher listing - **Response DTO:** TeacherData collection
- `store(TeacherCreateData $request)`: **Route:** `POST /api/v1/admin/teachers` - **Request DTO:** TeacherCreateData - **Response DTO:** TeacherData
- `show(Teacher $teacher)`: **Route:** `GET /api/v1/admin/teachers/{teacher}` - **Response DTO:** TeacherData
- `update(TeacherUpdateData $request, Teacher $teacher)`: **Route:** `PUT /api/v1/admin/teachers/{teacher}` - **Request DTO:** TeacherUpdateData - **Response DTO:** TeacherData
- `destroy(Teacher $teacher)`: **Route:** `DELETE /api/v1/admin/teachers/{teacher}` - **Delegates to:** Teacher deletion

### TermController (`app/Http/Controllers/Api/Admin/TermController.php`)
- `index()`: **Route:** `GET /api/v1/admin/terms` - **Delegates to:** Term listing - **Response DTO:** TermData collection
- `store(TermCreateData $request)`: **Route:** `POST /api/v1/admin/terms` - **Request DTO:** TermCreateData - **Response DTO:** TermData
- `show(Term $term)`: **Route:** `GET /api/v1/admin/terms/{term}` - **Response DTO:** TermData
- `update(TermUpdateData $request, Term $term)`: **Route:** `PUT /api/v1/admin/terms/{term}` - **Request DTO:** TermUpdateData - **Response DTO:** TermData
- `destroy(Term $term)`: **Route:** `DELETE /api/v1/admin/terms/{term}` - **Delegates to:** Term deletion

### UserController (`app/Http/Controllers/Api/Admin/UserController.php`)
- `index()`: **Route:** `GET /api/v1/admin/users` - **Delegates to:** Customer user listing - **Response DTO:** UserData collection
- `store(UserCreateData $request)`: **Route:** `POST /api/v1/admin/users` - **Request DTO:** UserCreateData - **Response DTO:** UserData
- `show(User $user)`: **Route:** `GET /api/v1/admin/users/{user}` - **Response DTO:** UserData
- `update(UserUpdateData $request, User $user)`: **Route:** `PUT /api/v1/admin/users/{user}` - **Request DTO:** UserUpdateData - **Response DTO:** UserData
- `destroy(User $user)`: **Route:** `DELETE /api/v1/admin/users/{user}` - **Delegates to:** User deletion

### CategoryController (`app/Http/Controllers/Api/Admin/Category/CategoryController.php`)
- `index()`: **Route:** `GET /api/v1/admin/category` - **Delegates to:** Category listing with hierarchy - **Response DTO:** CategoryData collection
- `store(CategoryCreateData $request)`: **Route:** `POST /api/v1/admin/category` - **Request DTO:** CategoryCreateData - **Response DTO:** CategoryData
- `show(Category $category)`: **Route:** `GET /api/v1/admin/category/{category}` - **Response DTO:** CategoryData
- `update(CategoryUpdateData $request, Category $category)`: **Route:** `PUT /api/v1/admin/category/{category}` - **Request DTO:** CategoryUpdateData - **Response DTO:** CategoryData
- `destroy(Category $category)`: **Route:** `DELETE /api/v1/admin/category/{category}` - **Delegates to:** Category deletion

### CategoryItemsController (`app/Http/Controllers/Api/Admin/Category/CategoryItemsController.php`)
- `__invoke(Category $category)`: **Route:** `GET /api/v1/admin/category/{category}/items` - **Delegates to:** Category product listing - **Response DTO:** ProductData collection

### GoodForStartController (`app/Http/Controllers/Api/Admin/Content/GoodForStartController.php`)
- `__invoke(SetGoodForStartData $request, Category $category, SetGoodForStartAction $action)`: **Route:** `POST /api/v1/admin/category/{category}/good-for-start` - **Request DTO:** SetGoodForStartData - **Delegates to:** SetGoodForStartAction - **Response:** success message with updated item count

### CourseController (`app/Http/Controllers/Api/Admin/Product/CourseController.php`)
- `index()`: **Route:** `GET /api/v1/admin/course` - **Delegates to:** Course listing - **Response DTO:** CourseData collection
- `store(CourseCreateData $request)`: **Route:** `POST /api/v1/admin/course` - **Request DTO:** CourseCreateData - **Response DTO:** CourseData
- `show(Course $course)`: **Route:** `GET /api/v1/admin/course/{course}` - **Response DTO:** CourseData
- `update(CourseUpdateData $request, Course $course)`: **Route:** `PUT /api/v1/admin/course/{course}` - **Request DTO:** CourseUpdateData - **Response DTO:** CourseData
- `destroy(Course $course)`: **Route:** `DELETE /api/v1/admin/course/{course}` - **Delegates to:** Course deletion

### DigitalAssetController (`app/Http/Controllers/Api/Admin/Product/DigitalAssetController.php`)
- `index()`: **Route:** `GET /api/v1/admin/digital-asset` - **Delegates to:** Digital asset listing - **Response DTO:** DigitalAssetData collection
- `store(DigitalAssetCreateData $request)`: **Route:** `POST /api/v1/admin/digital-asset` - **Request DTO:** DigitalAssetCreateData (short_name, full_name) - **Response DTO:** DigitalAssetData
- `show(DigitalAsset $digitalAsset)`: **Route:** `GET /api/v1/admin/digital-asset/{digital_asset}` - **Response DTO:** DigitalAssetData (includes short_name, full_name)
- `update(DigitalAssetUpdateData $request, DigitalAsset $digitalAsset)`: **Route:** `PUT /api/v1/admin/digital-asset/{digital_asset}` - **Request DTO:** DigitalAssetUpdateData (includes short_name, full_name) - **Response DTO:** DigitalAssetData
- `destroy(DigitalAsset $digitalAsset)`: **Route:** `DELETE /api/v1/admin/digital-asset/{digital_asset}` - **Delegates to:** Digital asset deletion
- **DTO Fields:** DigitalAsset DTOs use `short_name` (max 100 chars) and `full_name` (max 191 chars)

### SeminarController (`app/Http/Controllers/Api/Admin/Product/SeminarController.php`)
- `index()`: **Route:** `GET /api/v1/admin/seminar` - **Delegates to:** Seminar listing - **Response DTO:** SeminarData collection
- `store(SeminarCreateData $request)`: **Route:** `POST /api/v1/admin/seminar` - **Request DTO:** SeminarCreateData (curriculum_summary_text, outcomes_json array) - **Response DTO:** SeminarData
- `show(Seminar $seminar)`: **Route:** `GET /api/v1/admin/seminar/{seminar}` - **Response DTO:** SeminarData (includes curriculum_summary_text, outcomes_json)
- `update(SeminarUpdateData $request, Seminar $seminar)`: **Route:** `PUT /api/v1/admin/seminar/{seminar}` - **Request DTO:** SeminarUpdateData (includes curriculum_summary_text, outcomes_json) - **Response DTO:** SeminarData
- `destroy(Seminar $seminar)`: **Route:** `DELETE /api/v1/admin/seminar/{seminar}` - **Delegates to:** Seminar deletion
- **DTO Fields:** Seminar DTOs use `curriculum_summary_text` (nullable text) and `outcomes_json` (required array) for curriculum data

### ProductController (`app/Http/Controllers/Api/Admin/Product/ProductController.php`)
- `index()`: **Route:** `GET /api/v1/admin/product` - **Delegates to:** Product listing with filtering - **Response DTO:** ProductData collection
- `store(ProductCreateData $request)`: **Route:** `POST /api/v1/admin/product` - **Request DTO:** ProductCreateData - **Response DTO:** ProductData
- `show(Product $product)`: **Route:** `GET /api/v1/admin/product/{product}` - **Response DTO:** ProductData
- `update(ProductUpdateData $request, Product $product)`: **Route:** `PUT /api/v1/admin/product/{product}` - **Request DTO:** ProductUpdateData - **Response DTO:** ProductData
- `destroy(Product $product)`: **Route:** `DELETE /api/v1/admin/product/{product}` - **Delegates to:** Product deletion

### ArchiveProductController (`app/Http/Controllers/Api/Admin/Product/ArchiveProductController.php`)
- `__invoke(Product $product)`: **Route:** `POST /api/v1/admin/product/{product}/archive` - **Delegates to:** Product archival - **Response DTO:** ProductData

### ProductDeliveryOptionController (`app/Http/Controllers/Api/Admin/Product/ProductDeliveryOptionController.php`)
- `index(Product $product)`: **Route:** `GET /api/v1/admin/product/{product}/delivery-option` - **Response DTO:** ProductDeliveryOptionData collection
- `store(ProductDeliveryOptionCreateData $request, Product $product)`: **Route:** `POST /api/v1/admin/product/{product}/delivery-option` - **Request DTO:** ProductDeliveryOptionCreateData - **Response DTO:** ProductDeliveryOptionData
- `show(Product $product, ProductDeliveryOption $deliveryOption)`: **Route:** `GET /api/v1/admin/product/{product}/delivery-option/{delivery_option}` - **Response DTO:** ProductDeliveryOptionData
- `update(ProductDeliveryOptionUpdateData $request, Product $product, ProductDeliveryOption $deliveryOption)`: **Route:** `PUT /api/v1/admin/product/{product}/delivery-option/{delivery_option}` - **Response DTO:** ProductDeliveryOptionData
- `destroy(Product $product, ProductDeliveryOption $deliveryOption)`: **Route:** `DELETE /api/v1/admin/product/{product}/delivery-option/{delivery_option}` - **Delegates to:** Delivery option deletion

### RelatedProductController (`app/Http/Controllers/Api/Admin/Product/RelatedProductController.php`)
- `index(Product $product)`: **Route:** `GET /api/v1/admin/product/{product}/related-products` - **Query Param:** `relation_type` (optional, filters by: related, cross_sell, upsell) - **Response DTO:** RelatedProductData collection with nested ProductListItemData for each related product - **Delegates to:** Product policy authorization
- `store(Product $product, RelatedProductSyncData $data, CreateRelatedProductAction $action)`: **Route:** `POST /api/v1/admin/product/{product}/related-products` - **Request DTO:** RelatedProductSyncData (product_ids array, relation_type enum) - **Response DTO:** RelatedProductData collection (201 Created) - **Delegates to:** CreateRelatedProductAction for transactional sync - **Special Features:** Replaces all existing relations of the specified type; validates product cannot be related to itself
- `destroy(Product $product, Product $relatedProduct, DeleteRelatedProductAction $action)`: **Route:** `DELETE /api/v1/admin/product/{product}/related-products/{relatedProduct}` - **Query Param:** `relation_type` (required) - **Response:** 204 No Content - **Delegates to:** DeleteRelatedProductAction - **Validation:** Returns 422 if relation_type is invalid or missing

### BlogCategoryController (`app/Http/Controllers/Api/Admin/Blog/BlogCategoryController.php`)
- `index()`: **Route:** `GET /api/v1/admin/blog/category` - **Delegates to:** Blog category listing with hierarchy - **Response DTO:** BlogCategoryData collection
- `store(BlogCategoryCreateData $data)`: **Route:** `POST /api/v1/admin/blog/category` - **Request DTO:** BlogCategoryCreateData - **Delegates to:** CreateBlogCategoryAction - **Response DTO:** BlogCategoryData
- `show(BlogCategory $category)`: **Route:** `GET /api/v1/admin/blog/category/{category}` - **Response DTO:** BlogCategoryData
- `update(BlogCategory $category, BlogCategoryUpdateData $data)`: **Route:** `PUT /api/v1/admin/blog/category/{category}` - **Request DTO:** BlogCategoryUpdateData - **Response DTO:** BlogCategoryData
- `destroy(BlogCategory $category)`: **Route:** `DELETE /api/v1/admin/blog/category/{category}` - **Delegates to:** DeleteBlogCategoryAction

### BlogPostController (`app/Http/Controllers/Api/Admin/Blog/BlogPostController.php`)
- `index()`: **Route:** `GET /api/v1/admin/blog-post` - **Delegates to:** Blog post listing with filtering - **Response DTO:** BlogPostListItemData collection
- `store(BlogPostCreateData $request)`: **Route:** `POST /api/v1/admin/blog-post` - **Request DTO:** BlogPostCreateData - **Delegates to:** CreateBlogPostAction - **Response DTO:** BlogPostData
- `show(BlogPost $blogPost)`: **Route:** `GET /api/v1/admin/blog-post/{blog_post}` - **Response DTO:** BlogPostData
- `update(BlogPostUpdateData $request, BlogPost $blogPost)`: **Route:** `PUT /api/v1/admin/blog-post/{blog_post}` - **Request DTO:** BlogPostUpdateData - **Response DTO:** BlogPostData
- `destroy(BlogPost $blogPost)`: **Route:** `DELETE /api/v1/admin/blog-post/{blog_post}` - **Delegates to:** Blog post deletion
- **Special Features:** Enhanced filtering with main_productable_type/id support, uses BlogPostListItemData for listing efficiency

### OrderController (`app/Http/Controllers/Api/Admin/Order/OrderController.php`)
- `index()`: **Route:** `GET /api/v1/admin/orders` - **Delegates to:** Order listing with filtering - **Response DTO:** OrderListItemData collection
- `store(OrderCreateData $request)`: **Route:** `POST /api/v1/admin/orders` - **Request DTO:** OrderCreateData - **Delegates to:** CreateOrderAction::handle() — validates registration window and availability window on each item - **Response DTO:** OrderData
- `show(Order $order)`: **Route:** `GET /api/v1/admin/orders/{order}` - **Delegates to:** Order retrieval with relationships - **Response DTO:** OrderData
- `update(OrderUpdateData $request, Order $order)`: **Route:** `PUT /api/v1/admin/orders/{order}` - **Request DTO:** OrderUpdateData - **Response DTO:** OrderData
- `destroy(Order $order)`: **Route:** `DELETE /api/v1/admin/orders/{order}` - **Delegates to:** Order deletion

### OrderData DTO (`app/Data/Admin/Order/OrderData.php`)
- **Fields:** `id`, `increment_id`, `status`, `customer_id`, `customer_email`, `customer_phone`, `customer_first_name`, `customer_last_name`, `total_qty_ordered`, `total_item_count`, `subtotal`, `discount_amount`, `tax_amount`, `grand_total`, `total_paid`, `balance_due`, `full_value_grand_total`, `total_product_discount`, `total_cart_discount`, `total_discount`, `currency_code`, `customer`, `payment_status`, `applied_coupon_code`, `admin_notes`, `created_at`, `updated_at`, `customer_snapshot`, `items` (collection of `OrderItemData`)
- **Discount Layering:** `full_value_grand_total` represents the sum of all items at their base prices (no discounts applied) and is the reference for `balance_due`. `total_product_discount` aggregates product-level discounts from all items (sourced from `Order::totalProductDiscount()` accessor which sums `product_discount_amount` across items). `total_cart_discount` reflects cart-level coupon discounts (alias for `discount_amount`). `total_discount` is the combined sum of product-level and cart-level discounts.

### OrderItemData DTO (`app/Data/Admin/Order/OrderItemData.php`)
- **Fields:** `id`, `Order_id`, `product_delivery_option_id`, `discount_amount`, `qty_ordered`, `tax_amount`, `name`, `sku`, `price`, `original_price`, `product_discount_amount`, `total_discount_amount`, `total`, `payment_type`, `prepayment_amount`, `qty_refunded`, `total_refunded`, `status`, `vendor`, `product_snapshot`
- **Price Layering:** `price` is the base price (never includes discounts). `original_price` is read from `pricing_metadata['original_price']` (sourced from `OrderItem::originalPrice()` accessor). `product_discount_amount` is the product-level discount from `pricing_metadata['discount_amount']` multiplied by `qty_ordered` (sourced from `OrderItem::productDiscountAmount()` accessor). `total_discount_amount` combines product-level + cart-level discounts.

### OrderListItemData DTO (`app/Data/Admin/Order/OrderListItemData.php`)
- **Fields:** `id`, `increment_id`, `customer_first_name`, `customer_last_name`, `customer_email`, `customer_phone`, `subtotal`, `discount_amount`, `tax_amount`, `grand_total`, `total_paid`, `balance_due`, `admin_notes`, `status`, `payment_status`, `created_at`, `updated_at`, `payments` (collection of `PaymentData`), `items` (collection of `OrderItemListItemData`)

### OrderItemListItemData DTO (`app/Data/Admin/Order/OrderItemListItemData.php`)
- **Fields:** `id`, `product_delivery_option_id`, `discount_amount`, `qty_ordered`, `tax_amount`, `name`, `sku`, `price`, `total`, `payment_type`, `prepayment_amount`, `qty_refunded`, `total_refunded`

### ApproveOrderController (`app/Http/Controllers/Api/Admin/Order/ApproveOrderController.php`)
- `__invoke(Order $order, ApproveOrderAction $action)`: **Route:** `POST /api/v1/admin/orders/{order}/approve` - **Authorization:** `Gate::authorize('approve', $order)` via `PermissionEnum::ORDER_APPROVE` - **Delegates to:** ApproveOrderAction::handle() - **Response DTO:** OrderData - **Response File:** `resources/responses/admin/order/approve.json`

#### Validation Error Keys (Clarifications)
- Checkout validation errors for cart items use literal keys like `items.0`.
- Registration window errors use key `items.0` with messages like "Registration for '...' has not started yet." / "Registration period for '...' has ended."
- Availability window errors use key `items.0` with messages like "'...' is not yet available for purchase." / "'...' is no longer available for purchase."
- Wallet insufficient balance error key is `wallet_balance`.
- Gateway verify on non-pending payments returns a validation error keyed `payment`.

### OrderCalculationController (`app/Http/Controllers/Api/Admin/OrderCalculationController.php`)
- `__invoke(OrderCreateData $request)`: **Route:** `POST /api/v1/admin/orders/preview` - **Request DTO:** OrderCreateData - **Delegates to:** OrderCalculationService::calculate() - **Response DTO:** OrderContextData

### OrderItemController (`app/Http/Controllers/Api/Admin/OrderItemController.php`)
- `index(Order $order)`: **Route:** `GET /api/v1/admin/orders/{order}/order-items` - **Response DTO:** OrderItemData collection
- `show(Order $order, OrderItem $orderItem)`: **Route:** `GET /api/v1/admin/orders/{order}/order-items/{order_item}` - **Response DTO:** OrderItemData

### PaymentController (`app/Http/Controllers/Api/Admin/Order/PaymentController.php`)
- `index(Order $order)`: **Route:** `GET /api/v1/admin/orders/{order}/payment` - **Delegates to:** Payment listing for order - **Response DTO:** PaymentData collection
- `store(PaymentCreateData $request, Order $order)`: **Route:** `POST /api/v1/admin/orders/{order}/payment` - **Request DTO:** PaymentCreateData - **Delegates to:** CreatePaymentAction - **Response DTO:** envelope with `payment` (PaymentData), `requires_redirect`, `redirect_url`, `redirect_data`, `redirect_method`
- `show(Order $order, Payment $payment)`: **Route:** `GET /api/v1/admin/orders/{order}/payment/{payment}` - **Response DTO:** PaymentData
- `update(PaymentUpdateData $request, Order $order, Payment $payment)`: **Route:** `PUT /api/v1/admin/orders/{order}/payment/{payment}` - **Response DTO:** PaymentData
- `destroy(Order $order, Payment $payment)`: **Route:** `DELETE /api/v1/admin/orders/{order}/payment/{payment}` - **Delegates to:** Payment deletion
- **Nested ownership guard:** `show`/`update`/`destroy` call `ensurePaymentBelongsToOrder()` which throws 404 when `$payment->order_id !== $order->id`, preventing cross-order access to payments via nested routes

### NextPaymentDetailsController (`app/Http/Controllers/Api/Admin/Order/NextPaymentDetailsController.php`)
- `__invoke(Order $order)`: **Route:** `GET /api/v1/admin/orders/{order}/next-payment-details` - **Response DTO:** NextPaymentData. Throws `OrderFullyPaidException` (returns 422) if order is already fully paid.

### DiscountPromotionController (`app/Http/Controllers/Api/Admin/DiscountPromotionController.php`)
- `index()`: **Route:** `GET /api/v1/admin/discount-promotion` - **Delegates to:** Discount promotion listing - **Response DTO:** DiscountPromotionData collection
- `store(DiscountPromotionCreateData $request)`: **Route:** `POST /api/v1/admin/discount-promotion` - **Request DTO:** DiscountPromotionCreateData - **Response DTO:** DiscountPromotionData
- `show(DiscountPromotion $discountPromotion)`: **Route:** `GET /api/v1/admin/discount-promotion/{discount_promotion}` - **Response DTO:** DiscountPromotionData
- `update(DiscountPromotionUpdateData $request, DiscountPromotion $discountPromotion)`: **Route:** `PUT /api/v1/admin/discount-promotion/{discount_promotion}` - **Response DTO:** DiscountPromotionData
- `destroy(DiscountPromotion $discountPromotion)`: **Route:** `DELETE /api/v1/admin/discount-promotion/{discount_promotion}` - **Delegates to:** Discount promotion deletion

### DiscountPromotionStatusUpdateController (`app/Http/Controllers/Api/Admin/DiscountPromotionStatusUpdateController.php`)
- `__invoke(DiscountPromotionStatusData $request, DiscountPromotion $discountPromotion)`: **Route:** `PUT /api/v1/admin/discount-promotion/{discount_promotion}/status` - **Request DTO:** DiscountPromotionStatusData - **Response DTO:** DiscountPromotionData

### DiscountPromotionStatisticsController (`app/Http/Controllers/Api/Admin/DiscountPromotionStatisticsController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/discount-promotion-statistics` - **Response DTO:** DiscountPromotionStatisticsData

### DiscountInfoController (`app/Http/Controllers/Api/Admin/DiscountInfoController.php`)
- `index()`: **Route:** `GET /api/v1/admin/discount-info` - **Response DTO:** DiscountInfoData
- `conditions()`: **Route:** `GET /api/v1/admin/discount-info/conditions` - **Response DTO:** DiscountConditionsData
- `actions()`: **Route:** `GET /api/v1/admin/discount-info/actions` - **Response DTO:** DiscountActionsData
- `operators()`: **Route:** `GET /api/v1/admin/discount-info/operators` - **Response DTO:** DiscountOperatorsData
- `types()`: **Route:** `GET /api/v1/admin/discount-info/types` - **Response DTO:** DiscountTypesData

### AdminWalletController (`app/Http/Controllers/Api/Admin/Wallet/AdminWalletController.php`)
- `show(User $user)`: **Route:** `GET /api/v1/admin/users/{user}/wallet` - **Delegates to:** Wallet retrieval for user - **Response DTO:** WalletData (loaded with user)
- `store(CreateWalletData $data, User $user)`: **Route:** `POST /api/v1/admin/users/{user}/wallet` - **Request DTO:** CreateWalletData - **Delegates to:** CreateWalletAction - **Response DTO:** WalletData (201)
- **Authorization:** `Gate::authorize('view', $wallet)` / `Gate::authorize('create', Wallet::class)`

### DepositToWalletController (`app/Http/Controllers/Api/Admin/Wallet/DepositToWalletController.php`)
- `__invoke(DepositToWalletData $data, User $user, DepositToWalletAction $action)`: **Route:** `POST /api/v1/admin/users/{user}/wallet/deposit` - **Request DTO:** DepositToWalletData - **Response DTO:** WalletTransactionData (201, loaded with wallet/user/source)
- **Authorization:** `Gate::authorize('deposit', $wallet)` via `WalletPolicy::deposit()`

### WithdrawFromWalletController (`app/Http/Controllers/Api/Admin/Wallet/WithdrawFromWalletController.php`)
- `__invoke(WalletWithdrawalData $data, User $user, WithdrawFromWalletAction $action)`: **Route:** `POST /api/v1/admin/users/{user}/wallet/withdrawal` - **Request DTO:** WalletWithdrawalData - **Response DTO:** WalletTransactionData

### AdjustWalletController (`app/Http/Controllers/Api/Admin/Wallet/AdjustWalletController.php`)
- `__invoke(WalletAdjustmentData $data, User $user, AdjustWalletAction $action)`: **Route:** `POST /api/v1/admin/users/{user}/wallet/adjustment` - **Request DTO:** WalletAdjustmentData - **Response DTO:** WalletTransactionData

### AdminWalletCampaignController (`app/Http/Controllers/Api/Admin/WalletCampaign/AdminWalletCampaignController.php`)
- `index()`: **Route:** `GET /api/v1/admin/wallet-campaigns` - **Response DTO:** WalletCampaignData collection
- `store(WalletCampaignCreateData $request)`: **Route:** `POST /api/v1/admin/wallet-campaigns` - **Request DTO:** WalletCampaignCreateData - **Response DTO:** WalletCampaignData
- `show(WalletCampaign $walletCampaign)`: **Route:** `GET /api/v1/admin/wallet-campaigns/{wallet_campaign}` - **Response DTO:** WalletCampaignData
- `update(WalletCampaignUpdateData $request, WalletCampaign $walletCampaign)`: **Route:** `PUT /api/v1/admin/wallet-campaigns/{wallet_campaign}` - **Response DTO:** WalletCampaignData
- `destroy(WalletCampaign $walletCampaign)`: **Route:** `DELETE /api/v1/admin/wallet-campaigns/{wallet_campaign}` - **Delegates to:** Campaign deletion

### TriggerCampaignAllocationController (`app/Http/Controllers/Api/Admin/WalletCampaign/TriggerCampaignAllocationController.php`)
- `__invoke(User $user, WalletCampaign $walletCampaign)`: **Route:** `POST /api/v1/admin/users/{user}/wallet-campaigns/{wallet_campaign}/trigger-allocation` - **Response DTO:** WalletCampaignAllocationData

### BulkCampaignAllocationController (`app/Http/Controllers/Api/Admin/WalletCampaign/BulkCampaignAllocationController.php`)
- `__invoke(BulkAllocationData $request, WalletCampaign $walletCampaign)`: **Route:** `POST /api/v1/admin/wallet-campaigns/{wallet_campaign}/bulk-trigger-allocation` - **Request DTO:** BulkAllocationData - **Response DTO:** BulkAllocationResultData

### AdminAuditLogController (`app/Http/Controllers/Api/Admin/Audit/AdminAuditLogController.php`)
- `index()`: **Route:** `GET /api/v1/admin/audit/admin-actions` - **Response DTO:** AdminActionLogData collection
- `show(AdminActionLog $adminActionLog)`: **Route:** `GET /api/v1/admin/audit/admin-actions/{admin_action_log}` - **Response DTO:** AdminActionLogData

### ComplianceReportController (`app/Http/Controllers/Api/Admin/Audit/ComplianceReportController.php`)
- `__invoke(ComplianceReportRequestData $request)`: **Route:** `POST /api/v1/admin/audit/compliance-report` - **Request DTO:** ComplianceReportRequestData - **Response DTO:** ComplianceReportData

### SuspiciousActivityController (`app/Http/Controllers/Api/Admin/Audit/SuspiciousActivityController.php`)
- `__invoke(SuspiciousActivityRequestData $request)`: **Route:** `POST /api/v1/admin/audit/suspicious-activity` - **Request DTO:** SuspiciousActivityRequestData - **Response DTO:** SuspiciousActivityData

### Settings & Content Management Controllers

#### SettingController (`app/Http/Controllers/Api/Admin/Settings/SettingController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings` - **Response DTO:** SettingData collection assembled from cached CMS payloads via `SettingData::fromModel()`. Secrets (integration keys, tokens, passwords) are auto-redacted via `SettingSecretRedactor`.
- `update(SettingUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings` - **Request DTO:** SettingUpdateData (array of `{key, value}` pairs) - **Delegates to:** SettingsService::set() for each key — encrypted secrets written at rest, **REDACTED** placeholders preserve existing secret values, audit-logged with redacted payloads. Cache busted after write.
- **Response DTO:** `SettingData` collection post-update (with secrets redacted).

#### ContactInfoController (`app/Http/Controllers/Api/Admin/Content/ContactInfoController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/contact-info` - **Response DTO:** ContactInfoData sourced from SmartCache-backed SettingsService
- `update(ContactInfoUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/contact-info` - **Request DTO:** ContactInfoUpdateData - **Response DTO:** ContactInfoData after cache invalidation

#### AboutUsInfoController (`app/Http/Controllers/Api/Admin/Content/AboutUsInfoController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/about-us` - **Response DTO:** AboutUsInfoData
- `update(AboutUsInfoUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/about-us` - **Request DTO:** AboutUsInfoUpdateData - **Response DTO:** AboutUsInfoData

#### CollaborationInfoController (`app/Http/Controllers/Api/Admin/Content/CollaborationInfoController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/collaboration` - **Response DTO:** CollaborationInfoData
- `update(CollaborationInfoUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/collaboration` - **Request DTO:** CollaborationInfoUpdateData - **Response DTO:** CollaborationInfoData

#### FooterController (`app/Http/Controllers/Api/Admin/Content/FooterController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/footer` - **Response DTO:** FooterData with logo, caption, support_email, addresses, categories (array of category IDs), social_media_links, certifications
- `update(FooterUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/footer` - **Request DTO:** FooterUpdateData (categories as array of integer IDs) - **Response DTO:** FooterData post-update

#### HeaderController (`app/Http/Controllers/Api/Admin/Content/HeaderController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/header` - **Response DTO:** HeaderData with logo, contact_phone, contact_email
- `update(HeaderUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/header` - **Request DTO:** HeaderUpdateData - **Response DTO:** HeaderData

#### SliderController (`app/Http/Controllers/Api/Admin/Content/Slider/SliderController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings/slider` - **Response DTO:** SliderData collection
- `store(SliderCreateData $request)`: **Route:** `POST /api/v1/admin/settings/slider` - **Request DTO:** SliderCreateData - **Response DTO:** SliderData
- `show(Slider $slider)`: **Route:** `GET /api/v1/admin/settings/slider/{slider}` - **Response DTO:** SliderData
- `update(SliderUpdateData $request, Slider $slider)`: **Route:** `PUT /api/v1/admin/settings/slider/{slider}` - **Response DTO:** SliderData
- `destroy(Slider $slider)`: **Route:** `DELETE /api/v1/admin/settings/slider/{slider}` - **Delegates to:** Slider deletion

#### UpdateSliderStatusController (`app/Http/Controllers/Api/Admin/Content/Slider/UpdateSliderStatusController.php`)
- `__invoke(SliderStatusUpdateData $request, Slider $slider)`: **Route:** `PATCH /api/v1/admin/settings/slider/{slider}/status` - **Request DTO:** SliderStatusUpdateData - **Response DTO:** SliderData with updated publication flag

#### PartnerController (`app/Http/Controllers/Api/Admin/Content/PartnerController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings/partner` - **Response DTO:** PartnerData collection
- `store(PartnerCreateData $request)`: **Route:** `POST /api/v1/admin/settings/partner` - **Request DTO:** PartnerCreateData - **Response DTO:** PartnerData
- `show(Partner $partner)`: **Route:** `GET /api/v1/admin/settings/partner/{partner}` - **Response DTO:** PartnerData
- `update(PartnerUpdateData $request, Partner $partner)`: **Route:** `PUT /api/v1/admin/settings/partner/{partner}` - **Response DTO:** PartnerData
- `destroy(Partner $partner)`: **Route:** `DELETE /api/v1/admin/settings/partner/{partner}` - **Delegates to:** Partner removal

#### HomePageBlockController (`app/Http/Controllers/Api/Admin/Content/HomePageBlockController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings/home-page-block` - **Response DTO:** HomePageBlockData collection with block ordering
- `store(HomePageBlockCreateData $request)`: **Route:** `POST /api/v1/admin/settings/home-page-block` - **Request DTO:** HomePageBlockCreateData - **Response DTO:** HomePageBlockData
- `show(HomePageBlock $homePageBlock)`: **Route:** `GET /api/v1/admin/settings/home-page-block/{home_page_block}` - **Response DTO:** HomePageBlockData
- `update(HomePageBlockUpdateData $request, HomePageBlock $homePageBlock)`: **Route:** `PUT /api/v1/admin/settings/home-page-block/{home_page_block}` - **Response DTO:** HomePageBlockData
- `destroy(HomePageBlock $homePageBlock)`: **Route:** `DELETE /api/v1/admin/settings/home-page-block/{home_page_block}` - **Delegates to:** Home page block deletion

#### StudentStoryController (`app/Http/Controllers/Api/Admin/Content/StudentStoryController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings/student-stories` - **Filters:** `filter[student_name]`, `filter[course_name]`, exact `filter[is_visible]`, `filter[is_featured]`, callback filters `filter[course_id]`/`filter[category_id]` via course + category pivots. Sortable by `student_name`, `course_name`, `display_order`, `created_at`. **Response DTO:** Paginated `StudentStoryListItemData`.
- `store(StudentStoryCreateData $request)`: **Route:** `POST /api/v1/admin/settings/student-stories` - **Request DTO:** StudentStoryCreateData (`is_featured`, `categories[]`, `courses[]`, optional `avatar` media id) - **Response DTO:** `StudentStoryData` including attached categories/courses collections.
- `show(StudentStory $studentStory)`: **Route:** `GET /api/v1/admin/settings/student-stories/{student_story}` - Loads media + relationships before returning `StudentStoryData` with avatar, categories, courses and feature flag.
- `update(StudentStoryUpdateData $request, StudentStory $studentStory)`: **Route:** `PUT /api/v1/admin/settings/student-stories/{student_story}` - Mirrors `store()` contract for updating associations and feature status - **Response DTO:** `StudentStoryData`.
- `destroy(StudentStory $studentStory)`: **Route:** `DELETE /api/v1/admin/settings/student-stories/{student_story}` - **Response:** 204 No Content.

### Order Endpoints
Order routes use plural form: `/api/v1/admin/orders`, `/api/v1/admin/orders/preview`, `/api/v1/admin/orders/{order}/approve`, `/api/v1/admin/orders/{order}/order-items`, `/api/v1/admin/orders/{order}/payment`, `/api/v1/admin/orders/{order}/next-payment-details`.`

### Refund & Order Refund Endpoints
- **RefundController** (`app/Http/Controllers/Api/Admin/Order/RefundController.php`):
  - `index()`: **Route:** `GET /api/v1/admin/refunds` - **Response DTO:** RefundData collection
  - `store(RefundCreateData $request)`: **Route:** `POST /api/v1/admin/refunds` - **Request DTO:** RefundCreateData - **Response DTO:** RefundData
  - `show(Refund $refund)`: **Route:** `GET /api/v1/admin/refunds/{refund}` - **Response DTO:** RefundData
  - `update(RefundUpdateData $request, Refund $refund)`: **Route:** `PUT /api/v1/admin/refunds/{refund}` - **Response DTO:** RefundData
  - `destroy(Refund $refund)`: **Route:** `DELETE /api/v1/admin/refunds/{refund}` - **Delegates to:** Refund deletion
- **OrderRefundController** (`app/Http/Controllers/Api/Admin/Order/OrderRefundController.php`):
  - `store(RefundCreateData $request, Order $order)`: **Route:** `POST /api/v1/admin/orders/{order}/refund` - **Request DTO:** RefundCreateData - **Response DTO:** RefundData - Initiates full or partial refund at order level.
- **RefundUpdateStatusController** (`app/Http/Controllers/Api/Admin/Order/RefundUpdateStatusController.php`):
  - `__invoke(RefundStatusUpdateData $request, Refund $refund)`: **Route:** `PUT /api/v1/admin/refunds/{refund}/status` - **Request DTO:** RefundStatusUpdateData - **Response DTO:** RefundData

### Digipay Admin Endpoints
- **DigipayAdminController** (`app/Http/Controllers/Api/Admin/Payment/DigipayAdminController.php`):
  - `refund(Payment $payment)`: **Route:** `POST /api/v1/admin/payments/{payment}/digipay/refund` - Initiates refund via Digipay gateway
  - `deliver(Payment $payment)`: **Route:** `POST /api/v1/admin/payments/{payment}/digipay/deliver` - Confirms digital goods delivery
  - `reverse(Payment $payment)`: **Route:** `POST /api/v1/admin/payments/{payment}/digipay/reverse` - Voids unsettled transaction
  - `inquireRefund(Request $request)`: **Route:** `POST /api/v1/admin/payments/digipay/inquire-refund` - Checks refund status by tracking code

### Payment Gateway Settings Controller
- **PaymentGatewaySettingsController** (`app/Http/Controllers/Api/Admin/Settings/PaymentGatewaySettingsController.php`):
  - `index()`: **Route:** `GET /api/v1/admin/settings/payment-gateways` - Returns gateway config (with secrets redacted)
  - `update(PaymentGatewaySettingsUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/payment-gateways` - Updates gateway credentials and settings

### Enrollment Management Endpoints
- **EnrollmentController** (`app/Http/Controllers/Api/Admin/Enrollment/EnrollmentController.php`):
  - `index()`: **Route:** `GET /api/v1/admin/enrollments` - **Query Filters:** `filter[customer_id]`, `filter[enrollment_status]`, `filter[order_id]`, `filter[product_delivery_option_id]`, `filter[productable_type]` - **Response DTO:** `EnrollmentListItemData` paginated collection
  - `show(Enrollment $enrollment)`: **Route:** `GET /api/v1/admin/enrollments/{enrollment}` - **Response DTO:** `EnrollmentData` with nested order, customer, delivery option
  - `update(EnrollmentUpdateData $request, Enrollment $enrollment)`: **Route:** `PUT /api/v1/admin/enrollments/{enrollment}` - **Request DTO:** EnrollmentUpdateData - **Response DTO:** EnrollmentData
  - `destroy(Enrollment $enrollment)`: **Route:** `DELETE /api/v1/admin/enrollments/{enrollment}` - **Authorization:** `Gate::authorize('delete', $enrollment)` via `PermissionEnum::ENROLLMENT_DELETE` - **Delegates to:** DeleteEnrollmentAction
- **ChangeEnrollmentStatusController** (`app/Http/Controllers/Api/Admin/Enrollment/ChangeEnrollmentStatusController.php`):
  - `__invoke(Enrollment $enrollment, EnrollmentStatusChangeData $data, ChangeEnrollmentStatusAction $action)`: **Route:** `POST /api/v1/admin/enrollments/{enrollment}/change-status` - **Request DTO:** EnrollmentStatusChangeData (new_status, reason) - **Response DTO:** EnrollmentData
- **RetryProvisioningController** (`app/Http/Controllers/Api/Admin/Enrollment/RetryProvisioningController.php`):
  - `__invoke(Enrollment $enrollment, RetryProvisioningAction $action)`: **Route:** `POST /api/v1/admin/enrollments/{enrollment}/retry-provisioning` - **Authorization:** `Gate::authorize('retryProvision', $enrollment)` via `PermissionEnum::ENROLLMENT_RETRY_PROVISION` - Delegates to RetryProvisioningAction

### Review Management Controllers

#### ReviewController (`app/Http/Controllers/Api/Admin/Review/ReviewController.php`)
- `index()`: **Route:** `GET /api/v1/admin/reviews` - **Response DTO:** ReviewData collection
- `show(Review $review)`: **Route:** `GET /api/v1/admin/reviews/{review}` - **Response DTO:** ReviewData
- `destroy(Review $review)`: **Route:** `DELETE /api/v1/admin/reviews/{review}` - **Delegates to:** Review deletion

#### ApproveReviewController (`app/Http/Controllers/Api/Admin/Review/ApproveReviewController.php`)
- `__invoke(Review $review)`: **Route:** `POST /api/v1/admin/reviews/{review}/approve` - **Response DTO:** ReviewData

#### RejectReviewController (`app/Http/Controllers/Api/Admin/Review/RejectReviewController.php`)
- `__invoke(Review $review)`: **Route:** `POST /api/v1/admin/reviews/{review}/reject` - **Response DTO:** ReviewData

#### UpdateReviewFeaturedStatusController (`app/Http/Controllers/Api/Admin/Review/UpdateReviewFeaturedStatusController.php`)
- `__invoke(ReviewFeaturedStatusData $request, Review $review)`: **Route:** `PATCH /api/v1/admin/reviews/{review}/featured` - **Request DTO:** ReviewFeaturedStatusData - **Response DTO:** ReviewData

### Forms Management Controllers

#### AdviceRequestController (`app/Http/Controllers/Api/Admin/Forms/AdviceRequest/AdviceRequestController.php`)
- `index()`: **Route:** `GET /api/v1/admin/advice-requests` - **Response DTO:** AdviceRequestData paginated collection with handler relation
- `show(AdviceRequest $adviceRequest)`: **Route:** `GET /api/v1/admin/advice-requests/{advice_request}` - **Response DTO:** AdviceRequestData
- `update(AdviceRequestUpdateData $data, AdviceRequest $adviceRequest, UpdateAdviceRequestAction $action)`: **Route:** `PUT /api/v1/admin/advice-requests/{advice_request}` - **Request DTO:** AdviceRequestUpdateData - **Response DTO:** AdviceRequestData
- `destroy(AdviceRequest $adviceRequest)`: **Route:** `DELETE /api/v1/admin/advice-requests/{advice_request}` - **Delegates to:** Advice request deletion

#### AdviceRequestUpdateStatusController (`app/Http/Controllers/Api/Admin/Forms/AdviceRequest/AdviceRequestUpdateStatusController.php`)
- `__invoke(AdviceRequestUpdateData $data, AdviceRequest $adviceRequest, UpdateAdviceRequestStatusAction $action)`: **Route:** `PATCH /api/v1/admin/advice-requests/{advice_request}/status` - **Request DTO:** AdviceRequestUpdateData - **Response DTO:** AdviceRequestData with updated status

### File Management Controllers

#### UploadMediaController (`app/Http/Controllers/Api/Admin/FileManagement/UploadMediaController.php`)
- `__invoke(MediaUploadData $request)`: **Route:** `POST /api/v1/admin/media/upload` - **Request DTO:** MediaUploadData - **Response DTO:** MediaData

#### ViewMediaController (`app/Http/Controllers/Api/Admin/FileManagement/ViewMediaController.php`)
- `__invoke(Media $media)`: **Route:** `GET /api/v1/admin/media/{media}` - **Response:** Media file stream

#### UploadPrivateController (`app/Http/Controllers/Api/Admin/FileManagement/UploadPrivateController.php`)
- `__invoke(PrivateFileUploadData $request)`: **Route:** `POST /api/v1/admin/private-file/upload` - **Request DTO:** PrivateFileUploadData - **Response DTO:** PrivateFileData

#### ViewPrivateFileController (`app/Http/Controllers/Api/Admin/FileManagement/ViewPrivateFileController.php`)
- `__invoke(PrivateFile $file)`: **Route:** `GET /api/v1/admin/private-file/{file}` - **Response:** Private file stream

#### PrivateFileDownloadController (`app/Http/Controllers/Api/Admin/PrivateFileDownloadController.php`)
- `__invoke(PrivateFile $file)`: **Route:** `GET /api/v1/admin/private-file/{file}/download` - **Response:** File download

### Select Option Controllers

#### CategorySelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/CategorySelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/categories` - **Response DTO:** CategorySelectOptionData collection

#### BlogCategorySelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/BlogCategorySelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/blog-categories` - **Response DTO:** BlogCategorySelectOptionData collection

#### TermSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/TermSelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/terms` - **Response DTO:** TermSelectOptionData collection

#### VendorSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/VendorSelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/vendors` - **Response DTO:** VendorSelectOptionData collection

#### TeacherSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/TeacherSelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/teachers` - **Response DTO:** TeacherSelectOptionData collection

#### ProductableSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/ProductableSelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/productables` - **Response DTO:** ProductableSelectOptionData collection

#### StaffSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/StaffSelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/staff` - **Response DTO:** StaffSelectOptionData collection

#### CustomerSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/CustomerSelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/customers` - **Response DTO:** CustomerSelectOptionData collection

#### ProductSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/ProductSelectOptionController.php`)
- `__invoke(ProductQueryService $service, ?ProductableEnum $productableType = null)`: **Route:** `GET /api/v1/admin/select-option/products/{productableType?}` - **Path Param:** `productableType` (optional: course, seminar, digital_asset) - **Query Params:** `q` (search term for name/SKU matching), `limit` (default: 15) - **Response DTO:** ProductSelectOptionData collection (id, title=short_name, subtitle=slug, type=productable_type) - **Delegates to:** ProductQueryService for filtering and search with `whereLike()` matching - **Special Features:** Supports type filtering, search across product names, and configurable result limits; sorted by short_name ascending

## Customer API Interface (`/api/v1/*`)
**Authentication:** `auth:user` guard for protected endpoints  
**Public Endpoints:** Available without authentication for browsing

### Auth Endpoints (`/api/v1/auth/*`)

#### InitiateAuthController (`app/Http/Controllers/Api/Shop/Auth/InitiateAuthController.php`)
- `__invoke(AuthInitiateData $request)`: **Route:** `POST /api/v1/auth/initiate` - **Request DTO:** AuthInitiateData - **Delegates to:** OTP generation - **Response DTO:** AuthResponseData

#### PasswordLoginController (`app/Http/Controllers/Api/Shop/Auth/PasswordLoginController.php`)
- `__invoke(PasswordLoginData $request)`: **Route:** `POST /api/v1/auth/login/password` - **Request DTO:** PasswordLoginData - **Delegates to:** Password authentication - **Response DTO:** AuthTokenData

#### OtpAuthenticationController (`app/Http/Controllers/Api/Shop/Auth/OtpAuthenticationController.php`)
- `__invoke(OtpVerifyData $request)`: **Route:** `POST /api/v1/auth/otp/verify` - **Request DTO:** OtpVerifyData - **Delegates to:** OTP validation - **Response DTO:** AuthTokenData

#### ResendOtpController (`app/Http/Controllers/Api/Shop/Auth/ResendOtpController.php`)
- `__invoke(ResendOtpData $request)`: **Route:** `POST /api/v1/auth/otp/resend` - **Request DTO:** ResendOtpData - **Response DTO:** OtpResendData

#### ForgotPasswordController (`app/Http/Controllers/Api/Shop/Auth/ForgotPasswordController.php`)
- `__invoke(ForgotPasswordData $request)`: **Route:** `POST /api/v1/auth/password/reset` - **Request DTO:** ForgotPasswordData - **Response DTO:** PasswordResetResponseData

#### ResetPasswordController (`app/Http/Controllers/Api/Shop/Auth/ResetPasswordController.php`)
- `__invoke(ResetPasswordData $request)`: **Route:** `POST /api/v1/auth/password/reset/otp` - **Request DTO:** ResetPasswordData - **Response DTO:** PasswordResetCompleteData

#### LogoutController (`app/Http/Controllers/Api/Shop/Auth/LogoutController.php`)
- `__invoke()`: **Route:** `POST /api/v1/auth/logout` - **Middleware:** `auth:user` - **Delegates to:** Token revocation - **Response DTO:** LogoutResponseData

### Shop Protected Endpoints (`/api/v1/shop/*`)
**Middleware:** `auth:user`

#### ProfileController (`app/Http/Controllers/Api/Shop/Profile/ProfileController.php`)
- `show()`: **Route:** `GET /api/v1/shop/profile` (singleton) - **Delegates to:** User profile retrieval - **Response DTO:** UserProfileData
- `update(ProfileUpdateData $request)`: **Route:** `PUT /api/v1/shop/profile` (singleton) - **Request DTO:** ProfileUpdateData - **Response DTO:** UserProfileData

#### CustomerChangePasswordController (`app/Http/Controllers/Api/Shop/Profile/CustomerChangePasswordController.php`)
- `__invoke(ChangePasswordRequest $request)`: **Route:** `PUT /api/v1/shop/change-password` - **Request DTO:** ChangePasswordRequest (current_password, new_password) - **Delegates to:** ChangePasswordAction - Validates current password when the account has one set; mismatches surface `validation.password.current_password_does_not_match`

#### AvatarController (`app/Http/Controllers/Api/Shop/AvatarController.php`)
- `update(Request $request)`: **Route:** `POST /api/v1/shop/customer/avatar` - Uploads/replaces the customer's avatar media
- `destroy(Request $request)`: **Route:** `DELETE /api/v1/shop/customer/avatar` - Removes the customer's avatar

#### GatewayListController (`app/Http/Controllers/Api/Shop/Sale/GatewayListController.php`)
- `__invoke(GatewayService $service)`: **Route:** `GET /api/v1/shop/payment/gateways` - Returns available payment gateway options with labels and icons for checkout UI. Delegates to `GatewayService::getShopActiveGatewaysDetials()` which resolves gateway settings via `SettingsService` with `config/payments.php` defaults as fallback.

#### WalletInfoController (`app/Http/Controllers/Api/Shop/Wallet/WalletInfoController.php`)
- `__invoke()`: **Route:** `GET /api/v1/shop/wallet` - Returns the authenticated customer's wallet info (balance, gift balance, status)

#### WalletTopupController (`app/Http/Controllers/Api/Shop/Wallet/WalletTopupController.php`)
- `topup(WalletTopupRequestData $data)`: **Route:** `POST /api/v1/shop/wallet/topup` (throttled: 5/1min, requires `auth:user`) - Allows authenticated user to add funds to their wallet. Blocks WALLET as payment method for top-ups. Creates PENDING payment via `PreparePendingPaymentAction` with `WALLET_TOPUP` purpose, then processes via gateway. **Request DTO:** WalletTopupRequestData (amount min 10000, payment_method: mellat_gateway|digipay). **Response DTO:** Payment process result with redirect info.

#### Student Dashboard (`/api/v1/shop/student/*`)

##### EnrollmentController (`app/Http/Controllers/Api/Shop/Student/EnrollmentController.php`)
- `index()`: **Route:** `GET /api/v1/shop/student/courses` - Lists authenticated user's enrolled course delivery options. **Response DTO:** Paginated collection of enrollment list DTOs with delivery method, dates, provisioning status.
- `show(Enrollment $enrollment)`: **Route:** `GET /api/v1/shop/student/courses/{enrollment:uuid}` - Returns enriched enrollment detail with typed block DTOs per delivery method, SSO URLs, certificate/review/survey info. **Response DTO:** `EnrollmentDetailData` with nested block DTOs.

##### MoodleSsoController (`app/Http/Controllers/Api/Shop/Student/MoodleSsoController.php`)
- `__invoke(Enrollment $enrollment)`: **Route:** `POST /api/v1/shop/student/courses/{enrollment:uuid}/moodle/sso` - Generates Moodle SSO login URL for the enrolled course. **Response DTO:** `MoodleSsoUrlData`.

##### JoinUrlController (`app/Http/Controllers/Api/Shop/Student/JoinUrlController.php`)
- `__invoke(Enrollment $enrollment)`: **Route:** `GET /api/v1/shop/student/courses/{enrollment:uuid}/join` - Lazy-generates and returns join URL based on delivery method (BBB, Skyroom, SpotPlayer). **Delegates to:** GetJoinUrlAction.

##### QuizController (`app/Http/Controllers/Api/Shop/Student/QuizController.php`)
- `__invoke()`: **Route:** `GET /api/v1/shop/student/quizzes` - Returns list of user's quizzes with completion states, sourced from Moodle integration via `provisioning_data`.

##### DigitalAssetEnrollmentController (`app/Http/Controllers/Api/Shop/Student/DigitalAssetEnrollmentController.php`)
- `__invoke()`: **Route:** `GET /api/v1/shop/student/digital-assets` - Lists user's enrolled digital assets with download availability.

##### DigitalAssetDownloadController (`app/Http/Controllers/Api/Shop/Student/DigitalAssetDownloadController.php`)
- `__invoke(Enrollment $enrollment, DigitalAsset $digitalAsset)`: **Route:** `GET /api/v1/shop/student/digital-assets/{enrollment:uuid}/download/{digitalAsset}` - Generates signed download URL for digital asset file.

##### OrderController (`app/Http/Controllers/Api/Shop/Student/OrderController.php`)
- `index()`: **Route:** `GET /api/v1/shop/student/orders` - Lists authenticated user orders (with items + payments). **Response DTO:** `OrderData` paginator.
- `show(string $incrementId)`: **Route:** `GET /api/v1/shop/student/orders/{order:increment_id}` - Returns single order with nested items/payments. **Response DTO:** `OrderData`.

##### CancelOrderController (`app/Http/Controllers/Api/Shop/Student/CancelOrderController.php`)
- `__invoke(Order $order)`: **Route:** `POST /api/v1/shop/student/orders/{order:increment_id}/cancel` - **Delegates to:** CancelOrderByCustomerAction::execute(). **Response DTO:** OrderData.

##### RetryPaymentController (`app/Http/Controllers/Api/Shop/Student/RetryPaymentController.php`)
- `__invoke(string $incrementId, RetryOrderPaymentData $request)`: **Route:** `POST /api/v1/shop/student/orders/{order:increment_id}/retry-payment` (throttled) - Revalidates eligibility and triggers `RetryOrderPaymentAction` using `PreparePendingPaymentAction` + processor.

##### ShowPaymentController (`app/Http/Controllers/Api/Shop/Student/ShowPaymentController.php`)
- `index()`: **Route:** `GET /api/v1/shop/student/payments` - Lists authenticated user's payments. **Response DTO:** `PaymentData` collection.
- `show(string $uuid)`: **Route:** `GET /api/v1/shop/student/payments/{uuid}` - Returns single payment by UUID. **Response DTO:** `PaymentData`.

#### Teacher Dashboard (`/api/v1/shop/teacher/*`)
All teacher endpoints require a `auth:user` account linked to a `Teacher` profile (`Auth::user()->teacherData`); unlinked accounts receive 403. Most routes proxy requests to the external IMS system using the teacher's civil ID.

##### CourseController (`app/Http/Controllers/Api/Shop/Teacher/CourseController.php`)
- `index()`: **Route:** `GET /api/v1/shop/teacher/courses` - **Query Param:** `period` (current|past) - Fetches the teacher's courses from IMS via `ImsService::getTeacherCourses()`, enriches each with local product cover image and `product_delivery_option_uuid` when a matching `ProductDeliveryOption` (keyed by `details_json->ims_course_code`) exists. **Response DTO:** `TeacherCourseItemData` collection (code, name, start/end date, is_current, has_grades_enabled, has_attendance_enabled, product_image, product_delivery_option_uuid)

##### TeacherMoodleSsoController (`app/Http/Controllers/Api/Shop/Teacher/TeacherMoodleSsoController.php`)
- `__invoke(ProductDeliveryOption $deliveryOption)`: **Route:** `POST /api/v1/shop/teacher/courses/{deliveryOption:uuid}/moodle/sso` - Generates a Moodle SSO login URL for the teacher in the course's Moodle-linked delivery option. **Response DTO:** `MoodleSsoUrlData`.

##### AttendanceController (`app/Http/Controllers/Api/Shop/Teacher/AttendanceController.php`)
- `index(ShowAttendanceData $request, string $courseCode)`: **Route:** `GET /api/v1/shop/teacher/courses/{courseCode}/attendances` - Reads attendance records via `ImsService::getAttendance()`
- `store(StoreAttendanceData $data, string $courseCode)`: **Route:** `POST /api/v1/shop/teacher/courses/{courseCode}/attendances` - Creates attendance records; `UnrecoverableProvisioningException` surfaces validation errors (422)
- `update(StoreAttendanceData $data, string $courseCode)`: **Route:** `PUT /api/v1/shop/teacher/courses/{courseCode}/attendances/{attendance}` - Updates attendance records
- `destroy(DeleteAttendanceData $attendanceData, string $courseCode)`: **Route:** `DELETE /api/v1/shop/teacher/courses/{courseCode}/attendances` - Removes attendance records (registered separately — the apiResource excludes `destroy`)

##### GradeController (`app/Http/Controllers/Api/Shop/Teacher/GradeController.php`)
- `index(Request $request, string $courseCode)`: **Route:** `GET /api/v1/shop/teacher/courses/{courseCode}/grades` - Lists grades via `ImsService::getGrades()`
- `store(StoreGradeData $data, string $courseCode)`: **Route:** `POST /api/v1/shop/teacher/courses/{courseCode}/grades` - Stores a single grade
- `update(StoreGradeData $data, string $courseCode)`: **Route:** `PUT /api/v1/shop/teacher/courses/{courseCode}/grades/{grade}` - Updates a grade
- `destroy(Request $request, string $courseCode)`: **Route:** `DELETE /api/v1/shop/teacher/courses/{courseCode}/grades/{grade}` - Deletes a grade
- `storeBulk(StoreBulkGradeData $data, string $courseCode)`: **Route:** `POST /api/v1/shop/teacher/courses/{courseCode}/grades/bulk` - Stores multiple grades in one request

##### SeminarController (`app/Http/Controllers/Api/Shop/Teacher/SeminarController.php`)
- `__invoke()`: **Route:** `GET /api/v1/shop/teacher/seminars` - **Query Param:** `per_page` - Lists seminars linked to the authenticated teacher (via teacher products with seminar delivery methods) from local data. **Response DTO:** `TeacherSeminarData` paginator.

#### CartController (`app/Http/Controllers/Api/Shop/Sale/CartController.php`)
- `index()`: **Route:** `GET /api/v1/shop/cart` - **Guards:** Supports authenticated users or guests (via `X-Guest-Token`) - **Response DTO:** `CartData`
- `store(AddCartItemData $request)`: **Route:** `POST /api/v1/shop/cart/items` - Adds a delivery option to the cart after validating capacity/payment type - **Response DTO:** `CartData`
- `update(UpdateCartItemData $request, CartItem $cartItem)`: **Route:** `PUT /api/v1/shop/cart/items/{cartItem}` - Updates quantity for an existing cart item - **Response DTO:** `CartData`
- `destroy(CartItem $cartItem)`: **Route:** `DELETE /api/v1/shop/cart/items/{cartItem}` - Removes an item - **Response:** `204 No Content`
- `applyCoupon(ApplyCouponData $request)`: **Route:** `POST /api/v1/shop/cart/coupon` - Applies a coupon via `PromotionService::findPromotionByCoupon()` + condition checks - **Response DTO:** `CartData`
- `removeCoupon()`: **Route:** `DELETE /api/v1/shop/cart/coupon` - Clears any applied coupon - **Response DTO:** `CartData`

#### CheckoutController (`app/Http/Controllers/Api/Shop/Sale/CheckoutController.php`)
- `__invoke(CheckoutData $request, CreateOrderFromCartAction $action)`: **Route:** `POST /api/v1/shop/checkout` (requires `auth:user`, `profile.check`) - Converts the current cart into an order, runs `CreateOrderFromCartAction`, and returns `CheckoutResponseData` that either embeds a completed `OrderData` payload or redirect instructions for multi-step gateways (Mellat, etc.). Free orders auto-complete with `NO_PAYMENT`. Validates registration window and availability window on each cart item at checkout. **Request DTO:** CheckoutData includes optional `payment_data` array for gateway-specific parameters.

### Shop Public Endpoints (`/api/v1/shop/*`)
**Authentication:** Unauthenticated public access

#### HomePageContentController (`app/Http/Controllers/Api/Shop/HomePage/HomePageContentController.php`)
- `index(GetHomePageBlocksListAction $action)`: **Route:** `GET /api/v1/shop/home-page-blocks` - **Response DTO:** HomePageBlockListData collection summarising id, location, preset
- `show(HomePageBlock $homePageBlock, GetHomePageBlockAction $action)`: **Route:** `GET /api/v1/shop/home-page-blocks/{home_page_block}` - **Response DTO:** HomePageBlockData for the requested block, including curated and dynamic list payloads

#### SliderController (`app/Http/Controllers/Api/Shop/HomePage/SliderController.php`)
- `__invoke()`: **Route:** `GET /api/v1/shop/sliders` - **Response DTO:** SliderData collection cached via SmartCache using `CacheKeysEnum::Slider`

#### PartnerController (`app/Http/Controllers/Api/Shop/HomePage/PartnerController.php`)
- `__invoke(Request $request)`: **Route:** `GET /api/v1/shop/partners` - **Query Params:** `show_in=home|course` - **Response DTO:** PartnerData collection filtered by display location and cached per `PartnerShowInEnum`

#### StudentStoryController (`app/Http/Controllers/Api/Shop/HomePage/StudentStoryController.php`)
- `__invoke(StudentStoryRequestData $request)`: **Route:** `GET /api/v1/shop/student-stories` - **Query Params:** `course_slug`, `category_slug`, `featured_only`, optional `limit`. Filters visible stories by requested course/category (matching both direct course relations and linked products) and falls back to featured stories when a requested slug yields no records. **Response DTO:** `StudentStoryData` collection ordered by `display_order` and cached per-parameter via `SWRCacheService` with wildcard invalidation support.

#### GatewayCallbackController (`app/Http/Controllers/Api/Shop/Payment/GatewayCallbackController.php`)
- `handle(Request $request, Payment $payment, VerifyPaymentAction $action)`: **Route:** `GET|POST /api/v1/shop/payment/gateway/callback/{payment}` - Accepts gateway callbacks via route-bound Payment UUID. Logs payloads, delegates to `VerifyPaymentAction` with Payment model + raw request data. Redirects customers to config-driven success/failure URLs with `payment`, `purpose`, `order` query params. Catches `PaymentExceptionContract` and `Throwable` separately for error-specific redirects.

### Shop Public Product & Search Endpoints (`/api/v1/shop/*`)
**Authentication:** Unauthenticated public access

#### CourseController (`app/Http/Controllers/Api/Shop/Product/CourseController.php`)
- `index(ProductListRequestData $request)`: **Route:** `GET /api/v1/shop/courses` - **Request DTO:** ProductListRequestData (supports `filter[category_slugs][]`, `filter[fulfillment_types][]`, `filter[difficulty_level]`, `filter[availability_status]` (past|upcoming|ongoing), `filter[capacity]`, price range, discount flag, availability windows, search `q`, sort (including `capacity_utilization`), pagination) - **Validation:** Date filter params (`registration_starts_after`, `registration_ends_before`, `available_from`, `available_to`) use `jdate:Y-m-d` and `jdate_after` rules for Jalali date validation - **Delegates to:** `ProductQueryService::getCourseList()` with `ProductPriceService` hydration - **Response DTO:** Paginated `ProductCardData`
- `show(Product $product)`: **Route:** `GET /api/v1/shop/course/{product:slug}` - **Delegates to:** `ProductQueryService` detail pipeline and `ProductPriceService` for pricing snapshot - **Response DTO:** `CourseDetailData`

#### SeminarController (`app/Http/Controllers/Api/Shop/Product/SeminarController.php`)
- `index(ProductListRequestData $request)`: **Route:** `GET /api/v1/shop/seminars` - **Request DTO:** ProductListRequestData (same filtering/sorting contract as courses) - **Delegates to:** `ProductQueryService::getSeminarList()` with price hydration - **Response DTO:** Paginated `ProductCardData`

#### DigitalAssetController (`app/Http/Controllers/Api/Shop/Product/DigitalAssetController.php`)
- `index(ProductListRequestData $request)`: **Route:** `GET /api/v1/shop/digital-assets` - **Request DTO:** ProductListRequestData - **Delegates to:** `ProductQueryService::getDigitalAssetList()` with price hydration - **Response DTO:** Paginated `ProductCardData`
- `show(Product $product)`: **Route:** `GET /api/v1/shop/digital-asset/{product:slug}` - **Delegates to:** `ProductQueryService` detail pipeline + `ProductPriceService` - **Response DTO:** `DigitalAssetDetailData`

#### GoodForStartCoursesController (`app/Http/Controllers/Api/Shop/Product/GoodForStartCoursesController.php`)
- `__invoke(Category $category, ProductPriceService $priceService)`: **Route:** `GET /api/v1/shop/good-for-start/category/{category:slug}/courses` - **Query Param:** `limit` (default 10) - **Delegates to:** Cached `ProductQueryService::goodForStart()` lookup within SmartCache using `CacheKeysEnum::GoodForStart` - **Response DTO:** `ProductCardData` collection

#### ProductDeliveryOptionController (`app/Http/Controllers/Api/Shop/Product/ProductDeliveryOptionController.php`)
- `__invoke(ProductDeliveryOption $productDeliveryOption, ProductPriceService $priceService)`: **Route:** `GET /api/v1/shop/product-delivery-option/{productDeliveryOption:uuid}` - Loads the delivery option with its product/productable/media and returns its card DTO for direct SKU display (used by checkout confirmation and shared delivery-option links). **Response DTO:** `ProductDeliveryOptionCardData`

#### SearchController (`app/Http/Controllers/Api/Shop/SearchController.php`)
- `__invoke(SearchData $request, GlobalSearchService $service, ProductPriceService $priceService)`: **Route:** `GET /api/v1/shop/search` - **Request DTO:** SearchData (query, per_page, result_types, productable_type, filter.*) - **Delegates to:** `GlobalSearchService::search()` with Typesense/PGroonga fallback; maps products to `ProductCardData` and blog posts to `BlogPostCardData` (each tagged with `type`)

#### SuggestSearchController (`app/Http/Controllers/Api/Shop/SuggestSearchController.php`)
- `__invoke(SearchSuggestRequestData $request, GlobalSearchService $service)`: **Route:** `GET /api/v1/shop/search/suggest` - **Request DTO:** SearchSuggestRequestData (`q`, optional `limit`) - **Delegates to:** `GlobalSearchService::suggest()` using SWR cache & Typesense autocomplete - **Response:** Array of suggestion strings

#### BlogPostController (`app/Http/Controllers/Api/Shop/Blog/BlogPostController.php`)
- `index(BlogPostListRequestData $request)`: **Route:** `GET /api/v1/shop/blog/posts` - **Request DTO:** BlogPostListRequestData (supports `is_featured`, `category_slug`, `sortBy=published_at|created_at|popularity`, `sortOrder`, pagination controls) - **Response DTO:** Paginated `BlogPostCardData` containing author summary, rating aggregates, Jalali `published_at`, thumbnail URL (scoped to tag `cover` via `getAllMedia(urlOnly: true, onlyTags: ['cover'])`), featured flag, and attached categories. Only posts with `status=PUBLISHED` and `published_at <= now()` surface and the endpoint supports category slug filtering through `whereHas`. `sortBy=popularity` orders by `average_rating DESC` with nulls last.
- **Validation:** `category_slug` filter does not validate existence against `blog_categories` table — supports soft-deleted or temporary category references.
- `show(string $slug)`: **Route:** `GET /api/v1/shop/blog/post/{slug}` - **Response DTO:** `BlogPostDetailData` enriched with author data, media collections (cover/gallery/video tags via `getAllMedia()`), category cards, SEO metadata, review aggregates, and **related products** (up to 4 published products linked via `main_productable`). Returns 404 if the slug belongs to unpublished, scheduled, or missing posts.

#### BlogCategoryController (`app/Http/Controllers/Api/Shop/Blog/BlogCategoryController.php`)
- `index()`: **Route:** `GET /api/v1/shop/blog/categories` - **Response DTO:** `BlogCategoryCardData` collection ordered by name with `posts_count` reflecting currently published posts.
- `show(string $slug)`: **Route:** `GET /api/v1/shop/blog/category/{slug}` - **Response DTO:** `BlogCategoryDetailData` (extends card payload with SEO metadata) and 404s when slug is unknown.
- `posts(string $slug, BlogPostListRequestData $request)`: **Route:** `GET /api/v1/shop/blog/category/{slug}/posts` - **Request DTO:** BlogPostListRequestData (same sorting/filtering contract as the global blog post list plus optional featured-only filter) - **Response DTO:** Paginated `BlogPostCardData` for the requested category; respects `is_featured`, `sortBy`, `sortOrder`, and pagination inputs while excluding unpublished/future posts.

### Shop Public Category Endpoints (`/api/v1/shop/categories/*`)
**Authentication:** Unauthenticated public access

#### CategoryController (`app/Http/Controllers/Api/Shop/Product/CategoryController.php`)
- `index()`: **Route:** `GET /api/v1/shop/categories` - **Response DTO:** `CategoryCardData` collection with aggregated product counts computed via `ProductQueryService`. Each category includes `$children` array (recursive `CategoryCardData` for subcategories).
- `show(PaginationRequestData $request, Category $category, CategoryQueryService $service)`: **Route:** `GET /api/v1/shop/category/{category:slug}` - **Request DTO:** PaginationRequestData (`per_page`) - **Delegates to:** `CategoryQueryService::getProductsForCategory()` for each productable type - **Response DTO:** `CategoryDetailData` containing embedded course/seminar/digital asset collections

#### CategoryCourseController (`app/Http/Controllers/Api/Shop/Product/CategoryCourseController.php`)
- `__invoke(PaginationRequestData $request, Category $category, CategoryQueryService $service)`: **Route:** `GET /api/v1/shop/category/{category:slug}/courses` - **Delegates to:** `CategoryQueryService::getProductsForCategory()` with pagination flag - **Response DTO:** Paginated `ProductCardData`

#### CategorySeminarController (`app/Http/Controllers/Api/Shop/Product/CategorySeminarController.php`)
- `__invoke(PaginationRequestData $request, Category $category, CategoryQueryService $service)`: **Route:** `GET /api/v1/shop/category/{category:slug}/seminars` - **Delegates to:** `CategoryQueryService::getProductsForCategory()` - **Response DTO:** Paginated `ProductCardData`

#### CategoryDigitalAssetController (`app/Http/Controllers/Api/Shop/Product/CategoryDigitalAssetController.php`)
- `__invoke(PaginationRequestData $request, Category $category, CategoryQueryService $service)`: **Route:** `GET /api/v1/shop/category/{category:slug}/digital-assets` - **Delegates to:** `CategoryQueryService::getProductsForCategory()` - **Response DTO:** Paginated `ProductCardData`

### ProductCardData Enhancements
- **New fields:** `registration_status` (ProductRegistrationStatusEnum — derived from registration dates across all delivery options), `delivery_type` (ProductDeliveryStatusEnum — aggregated from fulfillment types), `teachers` (array of TeacherBasicData — unique teachers across all delivery options)
- **All shop product listings** include these derived fields via `ProductQueryService` when building `ProductCardData`

### ProductDetail Endpoint — `show()` sync trigger
- **EnrollmentController::show()** (`GET /api/v1/shop/student/courses/{enrollment:uuid}`): Triggers `SyncMoodleProgressJob` after rendering response to sync Moodle course progress (throttled at 5-min per enrollment). The sync result is stored in `provisioning_data` for subsequent reads.

#### TeacherController (`app/Http/Controllers/Api/Shop/TeacherController.php`)
- `show(Teacher $teacher)`: **Route:** `GET /api/v1/shop/teachers/{teacher:uuid}` - **URL Param:** `uuid` - **Response DTO:** `TeacherDetailData` with full instructor profile

#### ProductTeacherController (`app/Http/Controllers/Api/Shop/ProductTeacherController.php`)
- `__invoke(Product $product)`: **Route:** `GET /api/v1/shop/product/{product:slug}/teachers` - **URL Param:** `product:slug` - **Response DTO:** `TeacherDetailData` collection of unique teachers across all delivery options for the product

#### RelatedProductController (`app/Http/Controllers/Api/Shop/Product/RelatedProductController.php`)
- `__invoke(Product $product, RelationTypeEnum $relation_type)`: **Route:** `GET /api/v1/shop/product/{product:slug}/related/{relation_type}` - **URL Params:** `product:slug`, `relation_type` (`related|cross_sell|upsell`) - **Response DTO:** Array of `ProductCardData` items resolved via `ProductQueryService::availableProducts()->forListing()` plus `ProductPriceService` hydration. Returns an empty array when no relations match and automatically excludes unpublished/unavailable related products.

#### HeaderController (`app/Http/Controllers/Api/Shop/Settings/HeaderController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/header` - **Response DTO:** HeaderData derived from SettingsService payload

#### FooterController (`app/Http/Controllers/Api/Shop/Settings/FooterController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/footer` - **Response DTO:** FooterData including addresses, social media entries, certifications. Categories are resolved from stored IDs to `{name, slug}` pairs via `Category` model query. Footer payload excludes `support_link` and `main_links`; `navigation_links` excluded from header payload.

#### AboutUsController (`app/Http/Controllers/Api/Shop/CMS/AboutUsController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/aboutus` - **Response DTO:** AboutUsData transformed from SettingsService

#### ContactPageController (`app/Http/Controllers/Api/Shop/CMS/ContactPageController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/contact-page` - **Response DTO:** ContactPageData with support and address details

#### CollaborationPageController (`app/Http/Controllers/Api/Shop/CMS/CollaborationPageController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/collaboration` - **Response DTO:** CollaborationPageData providing collaboration content sections

### Moodle SSO Endpoint
#### MoodleSsoController (`app/Http/Controllers/Api/Shop/Student/MoodleSsoController.php`)
- `__invoke(Enrollment $enrollment, MoodleService $moodleService)`: **Route:** `POST /api/v1/shop/student/courses/{enrollment:uuid}/moodle/sso` - **Auth:** `auth:user` - **Delegates to:** MoodleService SSO URL generation via GetEnrollmentDetailAction - **Response DTO:** `MoodleSsoUrlData` with auto-login URL. Requires Moodle integration enabled and user enrolled in the course's Moodle-linked delivery option. Generates a `createUserKey` token valid for single-use login.

## CORS Configuration (`config/cors.php`)
- **Paths:** `api/*` + `sanctum/csrf-cookie`
- **Credentials:** `supports_credentials` set to `true` (allows cookies/auth headers)
- **Allowed origins (explicit list):** `https://dev-jedu.encel.ir`, `https://dev-admin-jedu.encel.ir`, `https://test-jedu.encel.ir`, `https://test-admin-jedu.encel.ir`, `https://api.jedu.ir`, `https://shop.jedu.ir`, `http://localhost:3000`, `http://185.141.133.114:8080`
- **Allows all:** headers, methods (`*`); no origin patterns
- **Usage:** Enables cross-origin API access from configured frontend domains (admin panel, shopfront, dev environments) with credential support

### Shop Form Submission Endpoints (`/api/v1/shop/*`)
**Rate Limiting:** `throttle:10,1` (10 requests per minute)

#### ContactUsRequestController (`app/Http/Controllers/Api/Shop/Forms/ContactUsRequestController.php`)
- `__invoke(ContactUsRequestData $data, StoreContactUsRequestAction $action)`: **Route:** `POST /api/v1/shop/contact-us` - **Request DTO:** ContactUsRequestData - **Response:** Success message on acceptance

#### CollaborationRequestController (`app/Http/Controllers/Api/Shop/Forms/CollaborationRequestController.php`)
- `__invoke(CreateCollaborationRequestData $data, CreateCollaborationRequestAction $action)`: **Route:** `POST /api/v1/shop/collaboration` - **Request DTO:** CreateCollaborationRequestData - **Response:** `201 Created` acknowledgement on submission (subject to throttle)

#### AdviceRequestController (`app/Http/Controllers/Api/Shop/AdviceRequestController.php`)
- `__invoke(AdviceRequestCreateData $data, StoreAdviceRequestAction $action)`: **Route:** `POST /api/v1/shop/advice-requests` - **Request DTO:** AdviceRequestCreateData (phone number required) - **Delegates to:** `StoreAdviceRequestAction` - **Response:** `201 Created` with success message (subject to throttle)

### Admin Auth Endpoints (`/api/v1/admin/auth/*`)

#### StaffInitiateAuthController (`app/Http/Controllers/Api/Admin/Auth/StaffInitiateAuthController.php`)
- `__invoke(StaffAuthInitiateData $request)`: **Route:** `POST /api/v1/admin/auth/initiate` - **Request DTO:** StaffAuthInitiateData - **Response DTO:** AuthResponseData

#### StaffPasswordLoginController (`app/Http/Controllers/Api/Admin/Auth/StaffPasswordLoginController.php`)
- `__invoke(StaffPasswordLoginData $request)`: **Route:** `POST /api/v1/admin/auth/login/password` - **Request DTO:** StaffPasswordLoginData - **Response DTO:** StaffAuthTokenData

#### StaffOtpAuthenticationController (`app/Http/Controllers/Api/Admin/Auth/StaffOtpAuthenticationController.php`)
- `__invoke(StaffOtpVerifyData $request)`: **Route:** `POST /api/v1/admin/auth/otp/verify` - **Request DTO:** StaffOtpVerifyData - **Response DTO:** StaffAuthTokenData

#### StaffResendOtpController (`app/Http/Controllers/Api/Admin/Auth/StaffResendOtpController.php`)
- `__invoke(StaffResendOtpData $request)`: **Route:** `POST /api/v1/admin/auth/otp/resend` - **Request DTO:** StaffResendOtpData - **Response DTO:** StaffOtpResendData

#### StaffForgotPasswordController (`app/Http/Controllers/Api/Admin/Auth/StaffForgotPasswordController.php`)
- `__invoke(StaffForgotPasswordData $request)`: **Route:** `POST /api/v1/admin/auth/password/reset` - **Request DTO:** StaffForgotPasswordData - **Response DTO:** StaffPasswordResetResponseData

#### StaffResetPasswordController (`app/Http/Controllers/Api/Admin/Auth/StaffResetPasswordController.php`)
- `__invoke(StaffResetPasswordData $request)`: **Route:** `POST /api/v1/admin/auth/password/reset/otp` - **Request DTO:** StaffResetPasswordData - **Response DTO:** StaffPasswordResetCompleteData

#### StaffLogoutController (`app/Http/Controllers/Api/Admin/Auth/StaffLogoutController.php`)
- `__invoke()`: **Route:** `POST /api/v1/admin/auth/logout` - **Middleware:** `auth:staff` - **Delegates to:** Staff token revocation - **Response DTO:** StaffLogoutResponseData

### Staff Profile Endpoints (`/api/v1/admin/*`)

#### StaffProfileController (`app/Http/Controllers/Api/Admin/Profile/StaffProfileController.php`)
- `show()`: **Route:** `GET /api/v1/admin/profile` (singleton) - Returns the authenticated staff member's own profile. **Response DTO:** StaffProfileData
- `update(UpdateStaffProfileData $data)`: **Route:** `PUT /api/v1/admin/profile` (singleton) - **Request DTO:** UpdateStaffProfileData (name, email, avatar) - **Delegates to:** UpdateStaffProfileAction

#### StaffChangePasswordController (`app/Http/Controllers/Api/Admin/Profile/StaffChangePasswordController.php`)
- `__invoke(ChangePasswordRequest $request)`: **Route:** `PUT /api/v1/admin/change-password` - **Request DTO:** ChangePasswordRequest (current_password, new_password) - **Delegates to:** ChangePasswordAction - Validates current password when the account has one set; mismatches surface `validation.password.current_password_does_not_match`

## Route Organization Pattern
- **Base Routes:** `/api/v1/api.php` includes all interface route files
- **Admin Routes:** `/api/v1/admin.php` - Complete platform management with `auth:staff` + `admin.audit`. All routes standardized to plural form. Individual route files: `admin/admin.php` (core CRUD + staff profile/wallet singleton), `admin/sale.php` (orders, payments, refunds, enrollments, Digipay admin), `admin/blog.php`, `admin/catalog.php`, `admin/file.php` (media upload/view/download), `admin/setting.php`, `admin/wallet.php` (wallet campaigns, audit, compliance), `admin/select_option.php`.
- **Customer Routes:** `/api/v1/customer.php` - Protected customer operations with `auth:user`. Student dashboard grouped under `/api/v1/shop/student/*`, teacher dashboard under `/api/v1/shop/teacher/*`.
- **Public Routes:** `/api/v1/shop/shop.php` - CMS-driven public endpoints (home page blocks, sliders, partners, header/footer, about/contact/collaboration pages, product listings, search)
- **Rate-Limited Shop Routes:** `/api/v1/shop/rate-limited.php` - Public form submissions (contact us, collaboration) protected by `throttle:10,1`
- **Auth Routes:** `/api/v1/auth.php` - Dual authentication system for both interfaces
- **Select Options:** `/api/v1/admin/select_option.php` - Dropdown/select data endpoints for admin interface

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

### RoleController (`app/Http/Controllers/Api/Admin/RoleController.php`)
- `index()`: **Route:** `GET /api/v1/admin/role` - **Delegates to:** Role listing - **Response DTO:** RoleData collection
- `store(RoleCreateData $request)`: **Route:** `POST /api/v1/admin/role` - **Request DTO:** RoleCreateData - **Response DTO:** RoleData
- `show(Role $role)`: **Route:** `GET /api/v1/admin/role/{role}` - **Response DTO:** RoleData
- `update(RoleUpdateData $request, Role $role)`: **Route:** `PUT /api/v1/admin/role/{role}` - **Request DTO:** RoleUpdateData - **Response DTO:** RoleData
- `destroy(Role $role)`: **Route:** `DELETE /api/v1/admin/role/{role}` - **Delegates to:** Role deletion

### PermissionController (`app/Http/Controllers/Api/Admin/PermissionController.php`)
- `index()`: **Route:** `GET /api/v1/admin/permission` - **Delegates to:** Permission listing - **Response DTO:** PermissionData collection

### VendorController (`app/Http/Controllers/Api/Admin/VendorController.php`)
- `index()`: **Route:** `GET /api/v1/admin/vendor` - **Delegates to:** Vendor listing - **Response DTO:** VendorData collection
- `store(VendorCreateData $request)`: **Route:** `POST /api/v1/admin/vendor` - **Request DTO:** VendorCreateData - **Response DTO:** VendorData
- `show(Vendor $vendor)`: **Route:** `GET /api/v1/admin/vendor/{vendor}` - **Response DTO:** VendorData
- `update(VendorUpdateData $request, Vendor $vendor)`: **Route:** `PUT /api/v1/admin/vendor/{vendor}` - **Request DTO:** VendorUpdateData - **Response DTO:** VendorData
- `destroy(Vendor $vendor)`: **Route:** `DELETE /api/v1/admin/vendor/{vendor}` - **Delegates to:** Vendor deletion

### TeacherController (`app/Http/Controllers/Api/Admin/TeacherController.php`)
- `index()`: **Route:** `GET /api/v1/admin/teacher` - **Delegates to:** Teacher listing - **Response DTO:** TeacherData collection
- `store(TeacherCreateData $request)`: **Route:** `POST /api/v1/admin/teacher` - **Request DTO:** TeacherCreateData - **Response DTO:** TeacherData
- `show(Teacher $teacher)`: **Route:** `GET /api/v1/admin/teacher/{teacher}` - **Response DTO:** TeacherData
- `update(TeacherUpdateData $request, Teacher $teacher)`: **Route:** `PUT /api/v1/admin/teacher/{teacher}` - **Request DTO:** TeacherUpdateData - **Response DTO:** TeacherData
- `destroy(Teacher $teacher)`: **Route:** `DELETE /api/v1/admin/teacher/{teacher}` - **Delegates to:** Teacher deletion

### TermController (`app/Http/Controllers/Api/Admin/TermController.php`)
- `index()`: **Route:** `GET /api/v1/admin/term` - **Delegates to:** Term listing - **Response DTO:** TermData collection
- `store(TermCreateData $request)`: **Route:** `POST /api/v1/admin/term` - **Request DTO:** TermCreateData - **Response DTO:** TermData
- `show(Term $term)`: **Route:** `GET /api/v1/admin/term/{term}` - **Response DTO:** TermData
- `update(TermUpdateData $request, Term $term)`: **Route:** `PUT /api/v1/admin/term/{term}` - **Request DTO:** TermUpdateData - **Response DTO:** TermData
- `destroy(Term $term)`: **Route:** `DELETE /api/v1/admin/term/{term}` - **Delegates to:** Term deletion

### UserController (`app/Http/Controllers/Api/Admin/UserController.php`)
- `index()`: **Route:** `GET /api/v1/admin/user` - **Delegates to:** Customer user listing - **Response DTO:** UserData collection
- `store(UserCreateData $request)`: **Route:** `POST /api/v1/admin/user` - **Request DTO:** UserCreateData - **Response DTO:** UserData
- `show(User $user)`: **Route:** `GET /api/v1/admin/user/{user}` - **Response DTO:** UserData
- `update(UserUpdateData $request, User $user)`: **Route:** `PUT /api/v1/admin/user/{user}` - **Request DTO:** UserUpdateData - **Response DTO:** UserData
- `destroy(User $user)`: **Route:** `DELETE /api/v1/admin/user/{user}` - **Delegates to:** User deletion

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
- `store(DigitalAssetCreateData $request)`: **Route:** `POST /api/v1/admin/digital-asset` - **Request DTO:** DigitalAssetCreateData (includes short_name, full_name instead of name field) - **Response DTO:** DigitalAssetData
- `show(DigitalAsset $digitalAsset)`: **Route:** `GET /api/v1/admin/digital-asset/{digital_asset}` - **Response DTO:** DigitalAssetData (includes short_name, full_name)
- `update(DigitalAssetUpdateData $request, DigitalAsset $digitalAsset)`: **Route:** `PUT /api/v1/admin/digital-asset/{digital_asset}` - **Request DTO:** DigitalAssetUpdateData (includes short_name, full_name) - **Response DTO:** DigitalAssetData
- `destroy(DigitalAsset $digitalAsset)`: **Route:** `DELETE /api/v1/admin/digital-asset/{digital_asset}` - **Delegates to:** Digital asset deletion
- **DTO Changes:** All DigitalAsset DTOs now use `short_name` (max 100 chars) and `full_name` (max 191 chars) instead of single `name` field

### SeminarController (`app/Http/Controllers/Api/Admin/Product/SeminarController.php`)
- `index()`: **Route:** `GET /api/v1/admin/seminar` - **Delegates to:** Seminar listing - **Response DTO:** SeminarData collection
- `store(SeminarCreateData $request)`: **Route:** `POST /api/v1/admin/seminar` - **Request DTO:** SeminarCreateData (includes curriculum_summary_text, outcomes_json array instead of learning_objectives) - **Response DTO:** SeminarData
- `show(Seminar $seminar)`: **Route:** `GET /api/v1/admin/seminar/{seminar}` - **Response DTO:** SeminarData (includes curriculum_summary_text, outcomes_json)
- `update(SeminarUpdateData $request, Seminar $seminar)`: **Route:** `PUT /api/v1/admin/seminar/{seminar}` - **Request DTO:** SeminarUpdateData (includes curriculum_summary_text, outcomes_json) - **Response DTO:** SeminarData
- `destroy(Seminar $seminar)`: **Route:** `DELETE /api/v1/admin/seminar/{seminar}` - **Delegates to:** Seminar deletion
- **DTO Changes:** All Seminar DTOs replaced `learning_objectives` text field with `curriculum_summary_text` (nullable text) and `outcomes_json` (required array) for structured curriculum data

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

### OrderController (`app/Http/Controllers/Api/Admin/OrderController.php`)
- `index()`: **Route:** `GET /api/v1/admin/order` - **Delegates to:** Order listing with filtering - **Response DTO:** OrderData collection
- `store(OrderCreateData $request)`: **Route:** `POST /api/v1/admin/order` - **Request DTO:** OrderCreateData - **Delegates to:** CreateOrderAction::handle() — now validates registration window and availability window on each item - **Response DTO:** OrderData
- `show(Order $order)`: **Route:** `GET /api/v1/admin/order/{order}` - **Delegates to:** Order retrieval with relationships - **Response DTO:** OrderData
- `update(OrderUpdateData $request, Order $order)`: **Route:** `PUT /api/v1/admin/order/{order}` - **Request DTO:** OrderUpdateData - **Response DTO:** OrderData
- `destroy(Order $order)`: **Route:** `DELETE /api/v1/admin/order/{order}` - **Delegates to:** Order deletion

### ApproveOrderController (`app/Http/Controllers/Api/Admin/Order/ApproveOrderController.php`)
- `__invoke(Order $order, ApproveOrderAction $action)`: **Route:** `POST /api/v1/admin/order/{order}/approve` - **Authorization:** `Gate::authorize('approve', $order)` via `PermissionEnum::ORDER_APPROVE` - **Delegates to:** ApproveOrderAction::handle() - **Response DTO:** OrderData - **Response File:** `storage/responses/admin/order/approve.json`

#### Validation Error Keys (Clarifications)
- Checkout validation errors for cart items use literal keys like `items.0`.
- Registration window errors use key `items.0` with messages like "Registration for '...' has not started yet." / "Registration period for '...' has ended."
- Availability window errors use key `items.0` with messages like "'...' is not yet available for purchase." / "'...' is no longer available for purchase."
- Wallet insufficient balance error key is `wallet_balance`.
- Gateway verify on non-pending payments returns a validation error keyed `payment`.

### OrderCalculationController (`app/Http/Controllers/Api/Admin/OrderCalculationController.php`)
- `__invoke(OrderCreateData $request)`: **Route:** `POST /api/v1/admin/order/preview` - **Request DTO:** OrderCreateData - **Delegates to:** OrderCalculationService::calculate() - **Response DTO:** OrderContextData

### OrderItemController (`app/Http/Controllers/Api/Admin/OrderItemController.php`)
- `index(Order $order)`: **Route:** `GET /api/v1/admin/order/{order}/order-item` - **Response DTO:** OrderItemData collection
- `show(Order $order, OrderItem $orderItem)`: **Route:** `GET /api/v1/admin/order/{order}/order-item/{order_item}` - **Response DTO:** OrderItemData

### PaymentController (`app/Http/Controllers/Api/Admin/PaymentController.php`)
- `index(Order $order)`: **Route:** `GET /api/v1/admin/order/{order}/payment` - **Delegates to:** Payment listing for order - **Response DTO:** PaymentData collection
- `store(PaymentCreateData $request, Order $order)`: **Route:** `POST /api/v1/admin/order/{order}/payment` - **Request DTO:** PaymentCreateData - **Delegates to:** CreatePaymentAction - **Response DTO:** PaymentData
- `show(Order $order, Payment $payment)`: **Route:** `GET /api/v1/admin/order/{order}/payment/{payment}` - **Response DTO:** PaymentData
- `update(PaymentUpdateData $request, Order $order, Payment $payment)`: **Route:** `PUT /api/v1/admin/order/{order}/payment/{payment}` - **Response DTO:** PaymentData
- `destroy(Order $order, Payment $payment)`: **Route:** `DELETE /api/v1/admin/order/{order}/payment/{payment}` - **Delegates to:** Payment deletion

### NextPaymentDetailsController (`app/Http/Controllers/Api/Admin/NextPaymentDetailsController.php`)
- `__invoke(Order $order)`: **Route:** `GET /api/v1/admin/order/{order}/next-payment-details` - **Response DTO:** NextPaymentData

### RefundController (`app/Http/Controllers/Api/Admin/RefundController.php`)
- `index(OrderItem $orderItem)`: **Route:** `GET /api/v1/admin/order-item/{order_item}/refund` - **Response DTO:** RefundData collection
- `store(RefundCreateData $request, OrderItem $orderItem)`: **Route:** `POST /api/v1/admin/order-item/{order_item}/refund` - **Request DTO:** RefundCreateData - **Response DTO:** RefundData
- `show(OrderItem $orderItem, Refund $refund)`: **Route:** `GET /api/v1/admin/order-item/{order_item}/refund/{refund}` - **Response DTO:** RefundData
- `update(RefundUpdateData $request, OrderItem $orderItem, Refund $refund)`: **Route:** `PUT /api/v1/admin/order-item/{order_item}/refund/{refund}` - **Response DTO:** RefundData
- `destroy(OrderItem $orderItem, Refund $refund)`: **Route:** `DELETE /api/v1/admin/order-item/{order_item}/refund/{refund}` - **Delegates to:** Refund deletion

### RefundUpdateStatusController (`app/Http/Controllers/Api/Admin/RefundUpdateStatusController.php`)
- `__invoke(RefundStatusUpdateData $request, Refund $refund)`: **Route:** `PUT /api/v1/admin/refund/{refund}/status` - **Request DTO:** RefundStatusUpdateData - **Response DTO:** RefundData

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
- `index()`: **Route:** `GET /api/v1/admin/wallet` - **Delegates to:** Wallet management actions - **Response DTO:** WalletData collection
- `show(Wallet $wallet)`: **Route:** `GET /api/v1/admin/wallet/{wallet}` - **Response DTO:** WalletData

### CreateWalletController (`app/Http/Controllers/Api/Admin/Wallet/CreateWalletController.php`)
- `__invoke(CreateWalletData $request)`: **Route:** `POST /api/v1/admin/wallet/create` - **Request DTO:** CreateWalletData - **Response DTO:** WalletData

### DepositToWalletController (`app/Http/Controllers/Api/Admin/Wallet/DepositToWalletController.php`)
- `__invoke(WalletDepositData $request, Wallet $wallet)`: **Route:** `POST /api/v1/admin/wallet/deposit/{wallet}` - **Request DTO:** WalletDepositData - **Response DTO:** WalletTransactionData

### WithdrawFromWalletController (`app/Http/Controllers/Api/Admin/Wallet/WithdrawFromWalletController.php`)
- `__invoke(WalletWithdrawalData $request, Wallet $wallet)`: **Route:** `POST /api/v1/admin/wallet/withdrawal/{wallet}` - **Request DTO:** WalletWithdrawalData - **Response DTO:** WalletTransactionData

### AdjustWalletController (`app/Http/Controllers/Api/Admin/Wallet/AdjustWalletController.php`)
- `__invoke(WalletAdjustmentData $request, Wallet $wallet)`: **Route:** `POST /api/v1/admin/wallet/adjustment/{wallet}` - **Request DTO:** WalletAdjustmentData - **Response DTO:** WalletTransactionData

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
- `show()`: **Route:** `GET /api/v1/admin/settings/footer` - **Response DTO:** FooterData with nested link arrays
- `update(FooterUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/footer` - **Request DTO:** FooterUpdateData - **Response DTO:** FooterData post-update

#### HeaderController (`app/Http/Controllers/Api/Admin/Content/HeaderController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/header` - **Response DTO:** HeaderData including navigation links
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
- `index()`: **Route:** `GET /api/v1/admin/settings/student-stories` - **Filters:** `filter[student_name]`, `filter[course_name]`, exact `filter[is_visible]`, `filter[is_featured]`, plus callback filters `filter[course_id]`/`filter[category_id]` that leverage the new course + category pivots. Sortable by `student_name`, `course_name`, `display_order`, `created_at`. **Response DTO:** Paginated `StudentStoryListItemData`.
- `store(StudentStoryCreateData $request)`: **Route:** `POST /api/v1/admin/settings/student-stories` - **Request DTO:** StudentStoryCreateData (now accepts `is_featured`, `categories[]`, `courses[]`, optional `avatar` media id) - **Response DTO:** `StudentStoryData` including attached categories/courses collections.
- `show(StudentStory $studentStory)`: **Route:** `GET /api/v1/admin/settings/student-stories/{student_story}` - Loads media + relationships before returning `StudentStoryData` with avatar, categories, courses and feature flag.
- `update(StudentStoryUpdateData $request, StudentStory $studentStory)`: **Route:** `PUT /api/v1/admin/settings/student-stories/{student_story}` - Mirrors `store()` contract for updating associations and feature status - **Response DTO:** `StudentStoryData`.
- `destroy(StudentStory $studentStory)`: **Route:** `DELETE /api/v1/admin/settings/student-stories/{student_story}` - **Response:** 204 No Content.

### Review Management Controllers

#### ReviewController (`app/Http/Controllers/Api/Admin/Review/ReviewController.php`)
- `index()`: **Route:** `GET /api/v1/admin/review` - **Response DTO:** ReviewData collection
- `show(Review $review)`: **Route:** `GET /api/v1/admin/review/{review}` - **Response DTO:** ReviewData
- `destroy(Review $review)`: **Route:** `DELETE /api/v1/admin/review/{review}` - **Delegates to:** Review deletion

#### ApproveReviewController (`app/Http/Controllers/Api/Admin/Review/ApproveReviewController.php`)
- `__invoke(Review $review)`: **Route:** `POST /api/v1/admin/review/{review}/approve` - **Response DTO:** ReviewData

#### RejectReviewController (`app/Http/Controllers/Api/Admin/Review/RejectReviewController.php`)
- `__invoke(Review $review)`: **Route:** `POST /api/v1/admin/review/{review}/reject` - **Response DTO:** ReviewData

#### UpdateReviewFeaturedStatusController (`app/Http/Controllers/Api/Admin/Review/UpdateReviewFeaturedStatusController.php`)
- `__invoke(ReviewFeaturedStatusData $request, Review $review)`: **Route:** `PATCH /api/v1/admin/review/{review}/featured` - **Request DTO:** ReviewFeaturedStatusData - **Response DTO:** ReviewData

### Forms Management Controllers

#### AdviceRequestController (`app/Http/Controllers/Api/Admin/Forms/AdviceRequest/AdviceRequestController.php`)
- `index()`: **Route:** `GET /api/v1/admin/advice-request` - **Response DTO:** AdviceRequestData paginated collection with handler relation
- `show(AdviceRequest $adviceRequest)`: **Route:** `GET /api/v1/admin/advice-request/{advice_request}` - **Response DTO:** AdviceRequestData
- `update(AdviceRequestUpdateData $data, AdviceRequest $adviceRequest, UpdateAdviceRequestAction $action)`: **Route:** `PUT /api/v1/admin/advice-request/{advice_request}` - **Request DTO:** AdviceRequestUpdateData - **Response DTO:** AdviceRequestData
- `destroy(AdviceRequest $adviceRequest)`: **Route:** `DELETE /api/v1/admin/advice-request/{advice_request}` - **Delegates to:** Advice request deletion

#### AdviceRequestUpdateStatusController (`app/Http/Controllers/Api/Admin/Forms/AdviceRequest/AdviceRequestUpdateStatusController.php`)
- `__invoke(AdviceRequestUpdateData $data, AdviceRequest $adviceRequest, UpdateAdviceRequestStatusAction $action)`: **Route:** `PATCH /api/v1/admin/advice-request/{advice_request}/status` - **Request DTO:** AdviceRequestUpdateData - **Response DTO:** AdviceRequestData with updated status

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
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/category` - **Response DTO:** CategorySelectOptionData collection

#### TermSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/TermSelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/term` - **Response DTO:** TermSelectOptionData collection

#### VendorSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/VendorSelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/vendor` - **Response DTO:** VendorSelectOptionData collection

#### TeacherSelectOptionController (`app/Http/Controllers/Api/Admin/SelectOptions/TeacherSelectOptionController.php`)
- `__invoke()`: **Route:** `GET /api/v1/admin/select-option/teacher` - **Response DTO:** TeacherSelectOptionData collection

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

#### ResnedOtpController (`app/Http/Controllers/Api/Shop/Auth/ResnedOtpController.php`)
- `__invoke(ResendOtpData $request)`: **Route:** `POST /api/v1/auth/otp/resend` - **Request DTO:** ResendOtpData - **Response DTO:** OtpResendData

#### ForgotPasswordController (`app/Http/Controllers/Api/Shop/Auth/ForgotPasswordController.php`)
- `__invoke(ForgotPasswordData $request)`: **Route:** `POST /api/v1/auth/password/reset` - **Request DTO:** ForgotPasswordData - **Response DTO:** PasswordResetResponseData

#### ResetPasswordController (`app/Http/Controllers/Api/Shop/Auth/ResetPasswordController.php`)
- `__invoke(ResetPasswordData $request)`: **Route:** `POST /api/v1/auth/password/reset/otp` - **Request DTO:** ResetPasswordData - **Response DTO:** PasswordResetCompleteData

#### LogoutController (`app/Http/Controllers/Api/Shop/Auth/LogoutController.php`)
- `__invoke()`: **Route:** `POST /api/v1/auth/logout` - **Middleware:** `auth:user` - **Delegates to:** Token revocation - **Response DTO:** LogoutResponseData

### Shop Protected Endpoints (`/api/v1/shop/*`)
**Middleware:** `auth:user`

#### ProfileController (`app/Http/Controllers/Api/Shop/ProfileController.php`)
- `show()`: **Route:** `GET /api/v1/shop/profile` - **Delegates to:** User profile retrieval - **Response DTO:** UserProfileData
- `update(ProfileUpdateData $request)`: **Route:** `PUT /api/v1/shop/profile` - **Request DTO:** ProfileUpdateData - **Response DTO:** UserProfileData

#### EnrolmentController (`app/Http/Controllers/Api/Shop/MyCourses/EnrolmentController.php`)
- `index()`: **Route:** `GET /api/v1/shop/my-courses` - **Delegates to:** User enrolment listing - **Response DTO:** EnrolmentData collection
- `show(Enrolment $enrolment)`: **Route:** `GET /api/v1/shop/my-courses/{enrolment:uuid}` - **Delegates to:** Enrolment details with access validation - **Response DTO:** EnrolmentData

#### CartController (`app/Http/Controllers/Api/Shop/Sale/CartController.php`)
- `index()`: **Route:** `GET /api/v1/shop/cart` - **Guards:** Supports authenticated users or guests (via `X-Guest-Token`) - **Response DTO:** `CartData`
- `store(AddCartItemData $request)`: **Route:** `POST /api/v1/shop/cart/items` - Adds a delivery option to the cart after validating capacity/payment type - **Response DTO:** `CartData`
- `update(UpdateCartItemData $request, CartItem $cartItem)`: **Route:** `PUT /api/v1/shop/cart/items/{cartItem}` - Updates quantity for an existing cart item - **Response DTO:** `CartData`
- `destroy(CartItem $cartItem)`: **Route:** `DELETE /api/v1/shop/cart/items/{cartItem}` - Removes an item - **Response:** `204 No Content`
- `applyCoupon(ApplyCouponData $request)`: **Route:** `POST /api/v1/shop/cart/coupon` - Applies a coupon using PromotionFinder - **Response DTO:** `CartData`
- `removeCoupon()`: **Route:** `DELETE /api/v1/shop/cart/coupon` - Clears any applied coupon - **Response DTO:** `CartData`

#### CheckoutController (`app/Http/Controllers/Api/Shop/Sale/CheckoutController.php`)
- `__invoke(CheckoutData $request, CreateOrderFromCartAction $action)`: **Route:** `POST /api/v1/shop/checkout` (requires `auth:user`, `profile.check`) - Converts the current cart into an order, runs `CreateOrderFromCartAction`, and returns `CheckoutResponseData` that either embeds a completed `OrderData` payload or redirect instructions for multi-step gateways (Mellat, etc.). Free orders auto-complete with `NO_PAYMENT`. Now validates registration window (`registration_start_date`/`registration_end_date`) and availability window (`available_from`/`available_to`) on each cart item at checkout.

#### OrderController (`app/Http/Controllers/Api/Shop/Sale/OrderController.php`)
- `index()`: **Route:** `GET /api/v1/shop/orders` - Lists authenticated user orders (with items + payments eager loaded) - **Response DTO:** `OrderData` paginator
- `show(string $incrementId)`: **Route:** `GET /api/v1/shop/orders/{order:increment_id}` - Returns a single order with nested items/payments - **Response DTO:** `OrderData`

#### CancelOrderController (`app/Http/Controllers/Api/Shop/Sale/CancelOrderController.php`)
- `__invoke(Order $order)`: **Route:** `POST /api/v1/shop/orders/{order:increment_id}/cancel` - **Auth:** `auth:user` - **Delegates to:** CancelOrderByCustomerAction::execute() - **Response DTO:** OrderData - **Error:** 422 with `DomainException` message if order has completed payments or is not in PENDING status

#### RetryPaymentController (`app/Http/Controllers/Api/Shop/Sale/RetryPaymentController.php`)
- `__invoke(string $incrementId, RetryOrderPaymentData $request)`: **Route:** `POST /api/v1/shop/orders/{order:increment_id}/retry-payment` (throttled) - Revalidates eligibility and triggers `RetryOrderPaymentAction`, responding with either redirect metadata (pending gateway payment) or immediate success payload.

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
- `__invoke(Request $request, VerifyPaymentAction $action)`: **Route:** `POST /api/v1/shop/payment/gateway/callback` - Accepts Mellat/other gateway callbacks without auth, logs payloads, wraps them in `GatewayCallbackData`, and delegates to `VerifyPaymentAction`. Redirects customers to `shop.payment.*` web routes depending on resulting `PaymentStatusEnum`.

### Shop Public Product & Search Endpoints (`/api/v1/shop/*`)
**Authentication:** Unauthenticated public access

#### CourseController (`app/Http/Controllers/Api/Shop/Product/CourseController.php`)
- `index(ProductListRequestData $request)`: **Route:** `GET /api/v1/shop/courses` - **Request DTO:** ProductListRequestData (supports `filter[category_slugs][]`, `filter[fulfillment_types][]`, `filter[difficulty_level]`, `filter[availability_status]` (past|upcoming|ongoing), `filter[capacity]`, price range, discount flag, availability windows, search `q`, sort (including `capacity_utilization`), pagination) - **Delegates to:** `ProductQueryService::getCourseList()` with `ProductPriceService` hydration - **Response DTO:** Paginated `ProductCardData`
- `show(Product $product)`: **Route:** `GET /api/v1/shop/course/{product:slug}` - **Delegates to:** `ProductQueryService` detail pipeline and `ProductPriceService` for pricing snapshot - **Response DTO:** `CourseDetailData`

#### SeminarController (`app/Http/Controllers/Api/Shop/Product/SeminarController.php`)
- `index(ProductListRequestData $request)`: **Route:** `GET /api/v1/shop/seminars` - **Request DTO:** ProductListRequestData (same filtering/sorting contract as courses) - **Delegates to:** `ProductQueryService::getSeminarList()` with price hydration - **Response DTO:** Paginated `ProductCardData`

#### DigitalAssetController (`app/Http/Controllers/Api/Shop/Product/DigitalAssetController.php`)
- `index(ProductListRequestData $request)`: **Route:** `GET /api/v1/shop/digital-assets` - **Request DTO:** ProductListRequestData - **Delegates to:** `ProductQueryService::getDigitalAssetList()` with price hydration - **Response DTO:** Paginated `ProductCardData`
- `show(Product $product)`: **Route:** `GET /api/v1/shop/digital-asset/{product:slug}` - **Delegates to:** `ProductQueryService` detail pipeline + `ProductPriceService` - **Response DTO:** `DigitalAssetDetailData`

#### GoodForStartCoursesController (`app/Http/Controllers/Api/Shop/Product/GoodForStartCoursesController.php`)
- `__invoke(Category $category, ProductPriceService $priceService)`: **Route:** `GET /api/v1/shop/good-for-start/category/{category:slug}/courses` - **Query Param:** `limit` (default 10) - **Delegates to:** Cached `ProductQueryService::goodForStart()` lookup within SmartCache using `CacheKeysEnum::GoodForStart` - **Response DTO:** `ProductCardData` collection

#### SearchController (`app/Http/Controllers/Api/Shop/SearchController.php`)
- `__invoke(SearchData $request, GlobalSearchService $service, ProductPriceService $priceService)`: **Route:** `GET /api/v1/shop/search` - **Request DTO:** SearchData (query, per_page, result_types, productable_type, filter.*) - **Delegates to:** `GlobalSearchService::search()` with Typesense/PGroonga fallback; maps products to `ProductCardData` and blog posts to `BlogPostCardData` (each tagged with `type`)

#### SuggestSearchController (`app/Http/Controllers/Api/Shop/SuggestSearchController.php`)
- `__invoke(SearchSuggestRequestData $request, GlobalSearchService $service)`: **Route:** `GET /api/v1/shop/search/suggest` - **Request DTO:** SearchSuggestRequestData (`q`, optional `limit`) - **Delegates to:** `GlobalSearchService::suggest()` using SWR cache & Typesense autocomplete - **Response:** Array of suggestion strings

#### BlogPostController (`app/Http/Controllers/Api/Shop/Blog/BlogPostController.php`)
- `index(BlogPostListRequestData $request)`: **Route:** `GET /api/v1/shop/blog/posts` - **Request DTO:** BlogPostListRequestData (supports `is_featured`, `category_slug`, `sortBy=published_at|created_at|popularity`, `sortOrder`, pagination controls) - **Response DTO:** Paginated `BlogPostCardData` containing author summary, rating aggregates, Jalali `published_at`, thumbnail URL (scoped to tag `cover` via `getAllMedia(urlOnly: true, onlyTags: ['cover'])`), featured flag, and attached categories. Only posts with `status=PUBLISHED` and `published_at <= now()` surface and the endpoint supports category slug filtering through `whereHas`. `sortBy=popularity` orders by `average_rating DESC` with nulls last.
- `show(string $slug)`: **Route:** `GET /api/v1/shop/blog/post/{slug}` - **Response DTO:** `BlogPostDetailData` enriched with author data, media collections (cover/gallery/video tags via `getAllMedia()`), category cards, SEO metadata, review aggregates, and **related products** (up to 4 published products linked via `main_productable`). Returns 404 if the slug belongs to unpublished, scheduled, or missing posts.

#### BlogCategoryController (`app/Http/Controllers/Api/Shop/Blog/BlogCategoryController.php`)
- `index()`: **Route:** `GET /api/v1/shop/blog/categories` - **Response DTO:** `BlogCategoryCardData` collection ordered by name with `posts_count` reflecting currently published posts.
- `show(string $slug)`: **Route:** `GET /api/v1/shop/blog/category/{slug}` - **Response DTO:** `BlogCategoryDetailData` (extends card payload with SEO metadata) and 404s when slug is unknown.
- `posts(string $slug, BlogPostListRequestData $request)`: **Route:** `GET /api/v1/shop/blog/category/{slug}/posts` - **Request DTO:** BlogPostListRequestData (same sorting/filtering contract as the global blog post list plus optional featured-only filter) - **Response DTO:** Paginated `BlogPostCardData` for the requested category; respects `is_featured`, `sortBy`, `sortOrder`, and pagination inputs while excluding unpublished/future posts.

### Shop Public Category Endpoints (`/api/v1/shop/categories/*`)
**Authentication:** Unauthenticated public access

#### CategoryController (`app/Http/Controllers/Api/Shop/Product/CategoryController.php`)
- `index()`: **Route:** `GET /api/v1/shop/categories` - **Response DTO:** `CategoryCardData` collection with aggregated product counts computed via `ProductQueryService`. Each category now includes `$children` array (recursive `CategoryCardData` for subcategories).
- `show(PaginationRequestData $request, Category $category, CategoryQueryService $service)`: **Route:** `GET /api/v1/shop/category/{category:slug}` - **Request DTO:** PaginationRequestData (`per_page`) - **Delegates to:** `CategoryQueryService::getProductsForCategory()` for each productable type - **Response DTO:** `CategoryDetailData` containing embedded course/seminar/digital asset collections

#### CategoryCourseController (`app/Http/Controllers/Api/Shop/Product/CategoryCourseController.php`)
- `__invoke(PaginationRequestData $request, Category $category, CategoryQueryService $service)`: **Route:** `GET /api/v1/shop/category/{category:slug}/courses` - **Delegates to:** `CategoryQueryService::getProductsForCategory()` with pagination flag - **Response DTO:** Paginated `ProductCardData`

#### CategorySeminarController (`app/Http/Controllers/Api/Shop/Product/CategorySeminarController.php`)
- `__invoke(PaginationRequestData $request, Category $category, CategoryQueryService $service)`: **Route:** `GET /api/v1/shop/category/{category:slug}/seminars` - **Delegates to:** `CategoryQueryService::getProductsForCategory()` - **Response DTO:** Paginated `ProductCardData`

#### CategoryDigitalAssetController (`app/Http/Controllers/Api/Shop/Product/CategoryDigitalAssetController.php`)
- `__invoke(PaginationRequestData $request, Category $category, CategoryQueryService $service)`: **Route:** `GET /api/v1/shop/category/{category:slug}/digital-assets` - **Delegates to:** `CategoryQueryService::getProductsForCategory()` - **Response DTO:** Paginated `ProductCardData`

### ProductCardData Enhancements
- **New fields:** `registration_status` (ProductRegistrationStatusEnum — derived from registration dates across all delivery options), `delivery_type` (ProductDeliveryStatusEnum — aggregated from fulfillment types), `teachers` (array of TeacherBasicData — unique teachers across all delivery options)
- **All shop product listings** now include these derived fields automatically via `ProductQueryService` when building `ProductCardData`

### ProductDetail Endpoint — `show()` sync trigger
- **EnrolmentController::show()** (`GET /api/v1/shop/my-courses/{enrolment:uuid}`): Now triggers `SyncMoodleProgressJob` after rendering response to sync Moodle course progress (throttled at 5-min per enrollment). The sync result is stored in `provisioning_data` for subsequent reads.

#### TeacherController (`app/Http/Controllers/Api/Shop/TeacherController.php`)
- `show(Teacher $teacher)`: **Route:** `GET /api/v1/shop/teachers/{teacher:uuid}` - **URL Param:** `uuid` - **Response DTO:** `TeacherDetailData` with full instructor profile

#### ProductTeacherController (`app/Http/Controllers/Api/Shop/ProductTeacherController.php`)
- `__invoke(Product $product)`: **Route:** `GET /api/v1/shop/product/{product:slug}/teachers` - **URL Param:** `product:slug` - **Response DTO:** `TeacherDetailData` collection of unique teachers across all delivery options for the product

#### RelatedProductController (`app/Http/Controllers/Api/Shop/Product/RelatedProductController.php`)
- `__invoke(Product $product, RelationTypeEnum $relation_type)`: **Route:** `GET /api/v1/shop/product/{product:slug}/related/{relation_type}` - **URL Params:** `product:slug`, `relation_type` (`related|cross_sell|upsell`) - **Response DTO:** Array of `ProductCardData` items resolved via `ProductQueryService::availableProducts()->forListing()` plus `ProductPriceService` hydration. Returns an empty array when no relations match and automatically excludes unpublished/unavailable related products.

#### HeaderController (`app/Http/Controllers/Api/Shop/Settings/HeaderController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/header` - **Response DTO:** HeaderData derived from SettingsService payload

#### FooterController (`app/Http/Controllers/Api/Shop/Settings/FooterController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/footer` - **Response DTO:** FooterData including addresses, categories, links, social media entries

#### AboutUsController (`app/Http/Controllers/Api/Shop/CMS/AboutUsController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/aboutus` - **Response DTO:** AboutUsData transformed from SettingsService

#### ContactPageController (`app/Http/Controllers/Api/Shop/CMS/ContactPageController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/contact-page` - **Response DTO:** ContactPageData with support and address details

#### CollaborationPageController (`app/Http/Controllers/Api/Shop/CMS/CollaborationPageController.php`)
- `__invoke(SettingsService $service)`: **Route:** `GET /api/v1/shop/collaboration` - **Response DTO:** CollaborationPageData providing collaboration content sections

### Moodle SSO Endpoint
#### MoodleSsoController (`app/Http/Controllers/Api/Shop/MoodleSsoController.php`)
- `__invoke(Order $order, MoodleService $moodleService)`: **Route:** `GET /api/v1/shop/moodle/sso/{order:increment_id}` - **Auth:** `auth:user` - **Delegates to:** MoodleService SSO URL generation - **Response:** Redirects user to Moodle with auto-login key. Requires Moodle integration to be enabled and user to be enrolled in the order's Moodle-linked products. Generates a `createUserKey` token valid for single-use login.

## CORS Configuration (`config/cors.php`)
- **Pattern:** Schema-based allowed origins — reads `cors.allowed_origins` from config, parsing each into `scheme://host` format
- **Credentials:** `supports_credentials` set to `true` (allows cookies/auth headers)
- **Allows all:** origins, headers, methods default to wildcard
- **Usage:** Enables cross-origin API access from configured frontend domains with credential support

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

## Route Organization Pattern
- **Base Routes:** `/api/v1/api.php` includes all interface route files
- **Admin Routes:** `/api/v1/admin.php` - Complete platform management with `auth:staff` + `admin.audit`. Individual route file `admin/sale.php` now includes `POST order/{order}/approve` (ApproveOrderController).
- **Customer Routes:** `/api/v1/customer.php` - Protected customer operations with `auth:user`. Now includes `POST orders/{order:increment_id}/cancel` (CancelOrderController).
- **Public Routes:** `/api/v1/shop/shop.php` - CMS-driven public endpoints (home page blocks, sliders, partners, header/footer, about/contact/collaboration pages)
- **Rate-Limited Shop Routes:** `/api/v1/shop/rate-limited.php` - Public form submissions (contact us, collaboration) protected by `throttle:10,1`
- **Auth Routes:** `/api/v1/auth.php` - Dual authentication system for both interfaces
- **Select Options:** `/api/v1/select_option.php` - Dropdown/select data endpoints for admin interface

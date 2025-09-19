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

### GoodForStartController (`app/Http/Controllers/Api/Admin/Category/GoodForStartController.php`)
- `set(Category $category)`: **Route:** `POST /api/v1/admin/category/{category}/good-for-start` - **Delegates to:** SetGoodForStartAction - **Response DTO:** CategoryData

### CourseController (`app/Http/Controllers/Api/Admin/Product/CourseController.php`)
- `index()`: **Route:** `GET /api/v1/admin/course` - **Delegates to:** Course listing - **Response DTO:** CourseData collection
- `store(CourseCreateData $request)`: **Route:** `POST /api/v1/admin/course` - **Request DTO:** CourseCreateData - **Response DTO:** CourseData
- `show(Course $course)`: **Route:** `GET /api/v1/admin/course/{course}` - **Response DTO:** CourseData
- `update(CourseUpdateData $request, Course $course)`: **Route:** `PUT /api/v1/admin/course/{course}` - **Request DTO:** CourseUpdateData - **Response DTO:** CourseData
- `destroy(Course $course)`: **Route:** `DELETE /api/v1/admin/course/{course}` - **Delegates to:** Course deletion

### DigitalAssetController (`app/Http/Controllers/Api/Admin/Product/DigitalAssetController.php`)
- `index()`: **Route:** `GET /api/v1/admin/digital-asset` - **Delegates to:** Digital asset listing - **Response DTO:** DigitalAssetData collection
- `store(DigitalAssetCreateData $request)`: **Route:** `POST /api/v1/admin/digital-asset` - **Request DTO:** DigitalAssetCreateData - **Response DTO:** DigitalAssetData
- `show(DigitalAsset $digitalAsset)`: **Route:** `GET /api/v1/admin/digital-asset/{digital_asset}` - **Response DTO:** DigitalAssetData
- `update(DigitalAssetUpdateData $request, DigitalAsset $digitalAsset)`: **Route:** `PUT /api/v1/admin/digital-asset/{digital_asset}` - **Request DTO:** DigitalAssetUpdateData - **Response DTO:** DigitalAssetData
- `destroy(DigitalAsset $digitalAsset)`: **Route:** `DELETE /api/v1/admin/digital-asset/{digital_asset}` - **Delegates to:** Digital asset deletion

### SeminarController (`app/Http/Controllers/Api/Admin/Product/SeminarController.php`)
- `index()`: **Route:** `GET /api/v1/admin/seminar` - **Delegates to:** Seminar listing - **Response DTO:** SeminarData collection
- `store(SeminarCreateData $request)`: **Route:** `POST /api/v1/admin/seminar` - **Request DTO:** SeminarCreateData - **Response DTO:** SeminarData
- `show(Seminar $seminar)`: **Route:** `GET /api/v1/admin/seminar/{seminar}` - **Response DTO:** SeminarData
- `update(SeminarUpdateData $request, Seminar $seminar)`: **Route:** `PUT /api/v1/admin/seminar/{seminar}` - **Request DTO:** SeminarUpdateData - **Response DTO:** SeminarData
- `destroy(Seminar $seminar)`: **Route:** `DELETE /api/v1/admin/seminar/{seminar}` - **Delegates to:** Seminar deletion

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
- `store(OrderCreateData $request)`: **Route:** `POST /api/v1/admin/order` - **Request DTO:** OrderCreateData - **Delegates to:** CreateOrderAction::handle() - **Response DTO:** OrderData
- `show(Order $order)`: **Route:** `GET /api/v1/admin/order/{order}` - **Delegates to:** Order retrieval with relationships - **Response DTO:** OrderData
- `update(OrderUpdateData $request, Order $order)`: **Route:** `PUT /api/v1/admin/order/{order}` - **Request DTO:** OrderUpdateData - **Response DTO:** OrderData
- `destroy(Order $order)`: **Route:** `DELETE /api/v1/admin/order/{order}` - **Delegates to:** Order deletion

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

### Settings Management Controllers

#### SettingController (`app/Http/Controllers/Api/Admin/Settings/SettingController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings` - **Response DTO:** SettingData collection

#### ContactInfoController (`app/Http/Controllers/Api/Admin/Settings/ContactInfoController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/contact-info` - **Response DTO:** ContactInfoData
- `update(ContactInfoUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/contact-info` - **Request DTO:** ContactInfoUpdateData - **Response DTO:** ContactInfoData

#### AboutUsInfoController (`app/Http/Controllers/Api/Admin/Settings/AboutUsInfoController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/about-us` - **Response DTO:** AboutUsInfoData
- `update(AboutUsInfoUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/about-us` - **Request DTO:** AboutUsInfoUpdateData - **Response DTO:** AboutUsInfoData

#### FooterController (`app/Http/Controllers/Api/Admin/Settings/FooterController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/footer` - **Response DTO:** FooterData
- `update(FooterUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/footer` - **Request DTO:** FooterUpdateData - **Response DTO:** FooterData

#### HeaderController (`app/Http/Controllers/Api/Admin/Settings/HeaderController.php`)
- `show()`: **Route:** `GET /api/v1/admin/settings/header` - **Response DTO:** HeaderData
- `update(HeaderUpdateData $request)`: **Route:** `PUT /api/v1/admin/settings/header` - **Request DTO:** HeaderUpdateData - **Response DTO:** HeaderData

#### SliderController (`app/Http/Controllers/Api/Admin/Settings/SliderController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings/slider` - **Response DTO:** SliderData collection
- `store(SliderCreateData $request)`: **Route:** `POST /api/v1/admin/settings/slider` - **Request DTO:** SliderCreateData - **Response DTO:** SliderData
- `show(Slider $slider)`: **Route:** `GET /api/v1/admin/settings/slider/{slider}` - **Response DTO:** SliderData
- `update(SliderUpdateData $request, Slider $slider)`: **Route:** `PUT /api/v1/admin/settings/slider/{slider}` - **Response DTO:** SliderData
- `destroy(Slider $slider)`: **Route:** `DELETE /api/v1/admin/settings/slider/{slider}` - **Delegates to:** Slider deletion

#### CollaborationCarouselController (`app/Http/Controllers/Api/Admin/Settings/CollaborationCarouselController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings/collaboration-carousel` - **Response DTO:** CollaborationCarouselData collection
- `store(CollaborationCarouselCreateData $request)`: **Route:** `POST /api/v1/admin/settings/collaboration-carousel` - **Response DTO:** CollaborationCarouselData
- `show(CollaborationCarousel $collaborationCarousel)`: **Route:** `GET /api/v1/admin/settings/collaboration-carousel/{collaboration_carousel}` - **Response DTO:** CollaborationCarouselData
- `update(CollaborationCarouselUpdateData $request, CollaborationCarousel $collaborationCarousel)`: **Route:** `PUT /api/v1/admin/settings/collaboration-carousel/{collaboration_carousel}` - **Response DTO:** CollaborationCarouselData
- `destroy(CollaborationCarousel $collaborationCarousel)`: **Route:** `DELETE /api/v1/admin/settings/collaboration-carousel/{collaboration_carousel}` - **Delegates to:** Collaboration carousel deletion

#### HomePageBlockController (`app/Http/Controllers/Api/Admin/Settings/HomePageBlockController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings/home-page-block` - **Response DTO:** HomePageBlockData collection
- `store(HomePageBlockCreateData $request)`: **Route:** `POST /api/v1/admin/settings/home-page-block` - **Response DTO:** HomePageBlockData
- `show(HomePageBlock $homePageBlock)`: **Route:** `GET /api/v1/admin/settings/home-page-block/{home_page_block}` - **Response DTO:** HomePageBlockData
- `update(HomePageBlockUpdateData $request, HomePageBlock $homePageBlock)`: **Route:** `PUT /api/v1/admin/settings/home-page-block/{home_page_block}` - **Response DTO:** HomePageBlockData
- `destroy(HomePageBlock $homePageBlock)`: **Route:** `DELETE /api/v1/admin/settings/home-page-block/{home_page_block}` - **Delegates to:** Home page block deletion

#### StudentStoryController (`app/Http/Controllers/Api/Admin/Settings/StudentStoryController.php`)
- `index()`: **Route:** `GET /api/v1/admin/settings/student-stories` - **Response DTO:** StudentStoryData collection
- `store(StudentStoryCreateData $request)`: **Route:** `POST /api/v1/admin/settings/student-stories` - **Response DTO:** StudentStoryData
- `show(StudentStory $studentStory)`: **Route:** `GET /api/v1/admin/settings/student-stories/{student_story}` - **Response DTO:** StudentStoryData
- `update(StudentStoryUpdateData $request, StudentStory $studentStory)`: **Route:** `PUT /api/v1/admin/settings/student-stories/{student_story}` - **Response DTO:** StudentStoryData
- `destroy(StudentStory $studentStory)`: **Route:** `DELETE /api/v1/admin/settings/student-stories/{student_story}` - **Delegates to:** Student story deletion

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

### Shop Public Endpoints (`/api/v1/shop/*`)
**Authentication:** Unauthenticated public access

#### HomePageContentController (`app/Http/Controllers/Api/Shop/HomePageContentController.php`)
- `__invoke()`: **Route:** `GET /api/v1/shop/home-page-content` - **Delegates to:** GetHomePageContentAction - **Response DTO:** HomePageContentData
- **Special Features:** Comprehensive home page content assembly with hero and main content blocks, supports curated lists, dynamic lists, banners, and webinar banners with integrated pricing data

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
- **Admin Routes:** `/api/v1/admin.php` - Complete platform management with `auth:staff` + `admin.audit`
- **Customer Routes:** `/api/v1/customer.php` - Protected customer operations with `auth:user`
- **Public Routes:** `/api/v1/shop.php` - Public browsing endpoints (currently empty - no public endpoints defined)
- **Auth Routes:** `/api/v1/auth.php` - Dual authentication system for both interfaces
- **Select Options:** `/api/v1/select_option.php` - Dropdown/select data endpoints for admin interface

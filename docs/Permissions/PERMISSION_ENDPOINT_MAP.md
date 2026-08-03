# Permission → Endpoint Map

> **For frontend developers**: Every admin API endpoint and the permission key required to access it.
> Permission keys match the string values in `app/Enums/PermissionEnum.php`.
>
> **Super-admin note**: Any `Staff` with `is_admin = true` bypasses ALL permission checks (via `Gate::before` in `AuthServiceProvider`). The permissions listed below are enforced only for non-admin staff.

## How to Read This Map

| Column | Description |
|---|---|
| Method | HTTP verb |
| URI | Path relative to `api/v1` |
| Route Name | Laravel route name (relative to `api.v1.admin.`) |
| Controller | Controller class and method |
| Permission | The exact `PermissionEnum` string value required. Multiple values = any one suffices. "—" = no permission guard (see audit doc). |
| Guard | Auth guard applied |

All admin endpoints use guard `auth:staff` + `admin.audit` middleware, applied group-wide in `bootstrap/app.php`.

---

## Staff Management

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/staff | admin.staff.index | StaffController@index | `staff.view_any` | auth:staff |
| POST | admin/staff | admin.staff.store | StaffController@store | `staff.create` | auth:staff |
| GET | admin/staff/{staff} | admin.staff.show | StaffController@show | `staff.view` (self-bypass: own record needs no permission) | auth:staff |
| PUT/PATCH | admin/staff/{staff} | admin.staff.update | StaffController@update | `staff.update` (self-bypass: own record needs no permission) | auth:staff |
| DELETE | admin/staff/{staff} | admin.staff.destroy | StaffController@destroy | `staff.delete` | auth:staff |

## Roles & Permissions

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/roles | admin.roles.index | RoleController@index | `roles.view_any` | auth:staff |
| POST | admin/roles | admin.roles.store | RoleController@store | `roles.create` | auth:staff |
| GET | admin/roles/{role} | admin.roles.show | RoleController@show | `roles.view` | auth:staff |
| PUT/PATCH | admin/roles/{role} | admin.roles.update | RoleController@update | `roles.update` | auth:staff |
| DELETE | admin/roles/{role} | admin.roles.destroy | RoleController@destroy | `roles.delete` | auth:staff |
| GET | admin/permissions | admin.permissions.index | PermissionController@__invoke | — (no guard) | auth:staff |

## Vendors

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/vendors | admin.vendors.index | VendorController@index | `vendors.view_any` | auth:staff |
| POST | admin/vendors | admin.vendors.store | VendorController@store | `vendors.create` | auth:staff |
| GET | admin/vendors/{vendor} | admin.vendors.show | VendorController@show | `vendors.view` | auth:staff |
| PUT/PATCH | admin/vendors/{vendor} | admin.vendors.update | VendorController@update | `vendors.update` | auth:staff |
| DELETE | admin/vendors/{vendor} | admin.vendors.destroy | VendorController@destroy | `vendors.delete` | auth:staff |

## Teachers

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/teachers | admin.teachers.index | TeacherController@index | `teachers.view_any` | auth:staff |
| POST | admin/teachers | admin.teachers.store | TeacherController@store | `teachers.create` | auth:staff |
| GET | admin/teachers/{teacher} | admin.teachers.show | TeacherController@show | `teachers.view` | auth:staff |
| PUT/PATCH | admin/teachers/{teacher} | admin.teachers.update | TeacherController@update | `teachers.update` | auth:staff |
| DELETE | admin/teachers/{teacher} | admin.teachers.destroy | TeacherController@destroy | `teachers.delete` | auth:staff |

## Terms

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/terms | admin.terms.index | TermController@index | `terms.view_any` | auth:staff |
| POST | admin/terms | admin.terms.store | TermController@store | `terms.create` | auth:staff |
| GET | admin/terms/{term} | admin.terms.show | TermController@show | `terms.view` | auth:staff |
| PUT/PATCH | admin/terms/{term} | admin.terms.update | TermController@update | `terms.update` | auth:staff |
| DELETE | admin/terms/{term} | admin.terms.destroy | TermController@destroy | `terms.delete` | auth:staff |

## Users (Customers)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/users | admin.users.index | UserController@index | `users.view_any` | auth:staff |
| POST | admin/users | admin.users.store | UserController@store | `users.create` | auth:staff |
| GET | admin/users/{user} | admin.users.show | UserController@show | `users.view` | auth:staff |
| PUT/PATCH | admin/users/{user} | admin.users.update | UserController@update | `users.update` | auth:staff |
| DELETE | admin/users/{user} | admin.users.destroy | UserController@destroy | `users.delete` | auth:staff |

## Wallet (per User)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/users/{user}/wallet | admin.users.wallet.show | AdminWalletController@show | `wallets.view` | auth:staff |
| POST | admin/users/{user}/wallet | admin.users.wallet.store | AdminWalletController@store | `wallets.create` | auth:staff |
| POST | admin/users/{user}/wallet/deposit | admin.users.wallet.deposit | DepositToWalletController@__invoke | `wallets.deposit` | auth:staff |
| POST | admin/users/{user}/wallet/withdrawal | admin.users.wallet.withdrawal | WithdrawFromWalletController@__invoke | `wallets.withdrawal` | auth:staff |
| POST | admin/users/{user}/wallet/adjustment | admin.users.wallet.adjustment | AdjustWalletController@__invoke | `wallets.adjustment` | auth:staff |

## Reviews

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/reviews | admin.reviews.index | ReviewController@index | `reviews.view_any` | auth:staff |
| GET | admin/reviews/{review} | admin.reviews.show | ReviewController@show | `reviews.view` | auth:staff |
| DELETE | admin/reviews/{review} | admin.reviews.destroy | ReviewController@destroy | `reviews.delete` | auth:staff |
| POST | admin/reviews/{review}/approve | admin.reviews.approve | ApproveReviewController@__invoke | `reviews.update` | auth:staff |
| POST | admin/reviews/{review}/reject | admin.reviews.reject | RejectReviewController@__invoke | `reviews.update` | auth:staff |
| PATCH | admin/reviews/{review}/featured | admin.reviews.update-featured-status | UpdateReviewFeaturedStatusController@__invoke | `reviews.update_featured_status` | auth:staff |

## Advice Requests

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/advice-requests | admin.advice-requests.index | AdviceRequestController@index | `advice_requests.view_any` | auth:staff |
| GET | admin/advice-requests/{advice_request} | admin.advice-requests.show | AdviceRequestController@show | `advice_requests.view` | auth:staff |
| PUT/PATCH | admin/advice-requests/{advice_request} | admin.advice-requests.update | AdviceRequestController@update | `advice_requests.update` | auth:staff |
| DELETE | admin/advice-requests/{advice_request} | admin.advice-requests.destroy | AdviceRequestController@destroy | `advice_requests.delete` | auth:staff |
| PATCH | admin/advice-requests/{adviceRequest}/status | admin.advice-requests.update-status | AdviceRequestUpdateStatusController@__invoke | `advice_requests.update` | auth:staff |

## Profile & Password (Self-service, no permission needed)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/profile | admin.profile.show | StaffProfileController@show | — (self-service) | auth:staff |
| PUT/PATCH | admin/profile | admin.profile.update | StaffProfileController@update | — (self-service) | auth:staff |
| PUT | admin/change-password | admin.change-password | StaffChangePasswordController@__invoke | — (self-service) | auth:staff |

## Categories

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/categories | admin.categories.index | CategoryController@index | `categories.view_any` | auth:staff |
| POST | admin/categories | admin.categories.store | CategoryController@store | `categories.create` | auth:staff |
| GET | admin/categories/{category} | admin.categories.show | CategoryController@show | `categories.view` | auth:staff |
| PUT/PATCH | admin/categories/{category} | admin.categories.update | CategoryController@update | `categories.update` | auth:staff |
| DELETE | admin/categories/{category} | admin.categories.destroy | CategoryController@destroy | `categories.delete` | auth:staff |
| GET | admin/categories/{category}/items | admin.categories.items.index | CategoryItemsController@__invoke | `categories.view` | auth:staff |
| POST | admin/categories/{category}/good-for-start | admin.categories.good-for-start.set | GoodForStartController@__invoke | `categories.update` | auth:staff |

## Courses

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/courses | admin.courses.index | CourseController@index | `courses.view_any` | auth:staff |
| POST | admin/courses | admin.courses.store | CourseController@store | `courses.create` | auth:staff |
| GET | admin/courses/{course} | admin.courses.show | CourseController@show | `courses.view` | auth:staff |
| PUT/PATCH | admin/courses/{course} | admin.courses.update | CourseController@update | `courses.update` | auth:staff |
| DELETE | admin/courses/{course} | admin.courses.destroy | CourseController@destroy | `courses.delete` | auth:staff |

## Digital Assets (uses FILE_* permissions)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/digital-assets | admin.digital-assets.index | DigitalAssetController@index | `files.view_any` | auth:staff |
| POST | admin/digital-assets | admin.digital-assets.store | DigitalAssetController@store | `files.create` | auth:staff |
| GET | admin/digital-assets/{digitalAsset} | admin.digital-assets.show | DigitalAssetController@show | `files.view` | auth:staff |
| PUT/PATCH | admin/digital-assets/{digitalAsset} | admin.digital-assets.update | DigitalAssetController@update | `files.update` | auth:staff |
| DELETE | admin/digital-assets/{digitalAsset} | admin.digital-assets.destroy | DigitalAssetController@destroy | `files.delete` | auth:staff |

## Seminars

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/seminars | admin.seminars.index | SeminarController@index | `seminars.view_any` | auth:staff |
| POST | admin/seminars | admin.seminars.store | SeminarController@store | `seminars.create` | auth:staff |
| GET | admin/seminars/{seminar} | admin.seminars.show | SeminarController@show | `seminars.view` | auth:staff |
| PUT/PATCH | admin/seminars/{seminar} | admin.seminars.update | SeminarController@update | `seminars.update` | auth:staff |
| DELETE | admin/seminars/{seminar} | admin.seminars.destroy | SeminarController@destroy | `seminars.delete` | auth:staff |

## Products

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/products | admin.products.index | ProductController@index | `products.view_any` | auth:staff |
| POST | admin/products | admin.products.store | ProductController@store | `products.create` | auth:staff |
| GET | admin/products/{product} | admin.products.show | ProductController@show | `products.view` | auth:staff |
| PUT/PATCH | admin/products/{product} | admin.products.update | ProductController@update | `products.update` | auth:staff |
| DELETE | admin/products/{product} | admin.products.destroy | ProductController@destroy | `products.delete` | auth:staff |
| POST | admin/products/{product}/archive | admin.products.archive | ArchiveProductController@__invoke | `products.update` | auth:staff |

## Product Delivery Options

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/products/{product}/delivery-options | admin.products.delivery-options.index | ProductDeliveryOptionController@index | `product_delivery_options.view_any` | auth:staff |
| POST | admin/products/{product}/delivery-options | admin.products.delivery-options.store | ProductDeliveryOptionController@store | `product_delivery_options.create` | auth:staff |
| GET | admin/products/{product}/delivery-options/{deliveryOption} | admin.products.delivery-options.show | ProductDeliveryOptionController@show | `product_delivery_options.view` | auth:staff |
| PUT/PATCH | admin/products/{product}/delivery-options/{deliveryOption} | admin.products.delivery-options.update | ProductDeliveryOptionController@update | `product_delivery_options.update` | auth:staff |
| DELETE | admin/products/{product}/delivery-options/{deliveryOption} | admin.products.delivery-options.destroy | ProductDeliveryOptionController@destroy | `product_delivery_options.delete` | auth:staff |

## Related Products

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/products/{product}/related-products | admin.products.related-products.index | RelatedProductController@index | `products.view` | auth:staff |
| POST | admin/products/{product}/related-products | admin.products.related-products.store | RelatedProductController@store | `products.update` | auth:staff |
| DELETE | admin/products/{product}/related-products/{relatedProduct} | admin.products.related-products.destroy | RelatedProductController@destroy | `products.update` | auth:staff |

## Orders

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/orders | admin.orders.index | OrderController@index | `orders.view_any` | auth:staff |
| POST | admin/orders | admin.orders.store | OrderController@store | `orders.create` | auth:staff |
| GET | admin/orders/{order} | admin.orders.show | OrderController@show | `orders.view` | auth:staff |
| PUT/PATCH | admin/orders/{order} | admin.orders.update | OrderController@update | `orders.update` | auth:staff |
| DELETE | admin/orders/{order} | admin.orders.destroy | OrderController@destroy | `orders.delete` | auth:staff |
| POST | admin/orders/preview | admin.orders.preview | OrderCalculationController@__invoke | `orders.create` | auth:staff |
| POST | admin/orders/{order}/approve | admin.orders.approve | ApproveOrderController@__invoke | `orders.approve` | auth:staff |

## Order Items

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/orders/{order}/order-items | admin.orders.order-items.index | OrderItemController@index | `orders.view` | auth:staff |
| GET | admin/orders/{order}/order-items/{order_item} | admin.orders.order-items.show | OrderItemController@show | `orders.view` | auth:staff |

## Payments

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/orders/{order}/payments | admin.orders.payments.index | PaymentController@index | `payments.view` | auth:staff |
| POST | admin/orders/{order}/payments | admin.orders.payments.store | PaymentController@store | `payments.create` | auth:staff |
| GET | admin/orders/{order}/payments/{payment} | admin.orders.payments.show | PaymentController@show | `payments.view` | auth:staff |
| PUT/PATCH | admin/orders/{order}/payments/{payment} | admin.orders.payments.update | PaymentController@update | `payments.update` | auth:staff |
| DELETE | admin/orders/{order}/payments/{payment} | admin.orders.payments.destroy | PaymentController@destroy | `payments.delete` | auth:staff |
| GET | admin/orders/{order}/next-payment-details | admin.orders.payments.next-payment-details | NextPaymentDetailsController@__invoke | `orders.view_any` | auth:staff |

## Digipay Payment Operations

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| POST | admin/payments/{payment}/digipay/refund | admin.payments.digipay.refund | DigipayAdminController@refund | `payments.update` | auth:staff |
| POST | admin/payments/{payment}/digipay/deliver | admin.payments.digipay.deliver | DigipayAdminController@deliver | `payments.update` | auth:staff |
| POST | admin/payments/{payment}/digipay/reverse | admin.payments.digipay.reverse | DigipayAdminController@reverse | `payments.delete` | auth:staff |
| POST | admin/payments/digipay/inquire-refund | admin.payments.digipay.inquire-refund | DigipayAdminController@inquireRefund | `payments.view` | auth:staff |

## Refunds

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/refunds | admin.refunds.index | RefundController@index | `refunds.view_any` | auth:staff |
| POST | admin/refunds | admin.refunds.store | RefundController@store | `refunds.create` | auth:staff |
| GET | admin/refunds/{refund} | admin.refunds.show | RefundController@show | `refunds.view` | auth:staff |
| PUT/PATCH | admin/refunds/{refund} | admin.refunds.update | RefundController@update | `refunds.update` | auth:staff |
| DELETE | admin/refunds/{refund} | admin.refunds.destroy | RefundController@destroy | `refunds.delete` | auth:staff |
| POST | admin/orders/{order}/refund | admin.orders.refund | OrderRefundController@store | `refunds.create` + `refunds.skip_gateway` | auth:staff |
| PUT | admin/refunds/{refund}/status | admin.refunds.status | RefundUpdateStatusController@__invoke | `refunds.update_status` | auth:staff |

## Discount Promotions

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/discount-promotions | admin.discount-promotions.index | DiscountPromotionController@index | `discounts.view_any` | auth:staff |
| POST | admin/discount-promotions | admin.discount-promotions.store | DiscountPromotionController@store | `discounts.create` | auth:staff |
| GET | admin/discount-promotions/{discountPromotion} | admin.discount-promotions.show | DiscountPromotionController@show | `discounts.view` | auth:staff |
| PUT/PATCH | admin/discount-promotions/{discountPromotion} | admin.discount-promotions.update | DiscountPromotionController@update | `discounts.update` | auth:staff |
| DELETE | admin/discount-promotions/{discountPromotion} | admin.discount-promotions.destroy | DiscountPromotionController@destroy | `discounts.delete` | auth:staff |
| GET | admin/discount-promotions/statistics | admin.discount-promotions.statistics | DiscountPromotionStatisticsController@__invoke | `discounts.view_any` | auth:staff |
| PUT | admin/discount-promotions/{discountPromotion}/status | admin.discount-promotions.toggle-status | DiscountPromotionStatusUpdateController@__invoke | `discounts.update` | auth:staff |

## Discount Info (read-only metadata)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/discount-info | admin.discount-info | DiscountInfoController@index | `discounts.view_any` | auth:staff |
| GET | admin/discount-info/conditions | admin.discount-info.conditions | DiscountInfoController@conditions | `discounts.view_any` | auth:staff |
| GET | admin/discount-info/actions | admin.discount-info.actions | DiscountInfoController@actions | `discounts.view_any` | auth:staff |
| GET | admin/discount-info/operators | admin.discount-info.operators | DiscountInfoController@operators | `discounts.view_any` | auth:staff |
| GET | admin/discount-info/types | admin.discount-info.types | DiscountInfoController@types | `discounts.view_any` | auth:staff |

## Enrollments

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/enrollments | admin.enrollments.index | EnrollmentController@index | `enrollments.view_any` | auth:staff |
| GET | admin/enrollments/{enrollment} | admin.enrollments.show | EnrollmentController@show | `enrollments.view` | auth:staff |
| PUT/PATCH | admin/enrollments/{enrollment} | admin.enrollments.update | EnrollmentController@update | `enrollments.update` | auth:staff |
| DELETE | admin/enrollments/{enrollment} | admin.enrollments.destroy | EnrollmentController@destroy | `enrollments.delete` | auth:staff |
| POST | admin/enrollments/{enrollment}/change-status | admin.enrollments.change-status | ChangeEnrollmentStatusController@__invoke | `enrollments.update` | auth:staff |
| POST | admin/enrollments/{enrollment}/retry-provisioning | admin.enrollments.retry-provisioning | RetryProvisioningController@__invoke | `enrollments.retry_provision` | auth:staff |

## File Management

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| POST | admin/media/upload | admin.media.upload | UploadMediaController@__invoke | — (no guard) | auth:staff |
| GET | admin/media/{media} | admin.media.view | ViewMediaController@__invoke | — (no guard) | auth:staff |
| POST | admin/private-file/upload | admin.private-upload.upload | UploadPrivateController@__invoke | — (no guard) | auth:staff |
| GET | admin/private-file/{file} | admin.private-upload.view | ViewPrivateFileController@__invoke | `files.view_any` | auth:staff |
| GET | admin/private-file/{file}/download | admin.private-upload.download | PrivateFileDownloadController@__invoke | `files.view_any` | auth:staff |

## Wallet Campaigns

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/wallet-campaigns | admin.wallet-campaigns.index | AdminWalletCampaignController@index | `wallet_campaigns.view_any` | auth:staff |
| POST | admin/wallet-campaigns | admin.wallet-campaigns.store | AdminWalletCampaignController@store | `wallet_campaigns.create` | auth:staff |
| GET | admin/wallet-campaigns/{wallet_campaign} | admin.wallet-campaigns.show | AdminWalletCampaignController@show | `wallet_campaigns.view` | auth:staff |
| PUT/PATCH | admin/wallet-campaigns/{wallet_campaign} | admin.wallet-campaigns.update | AdminWalletCampaignController@update | `wallet_campaigns.update` | auth:staff |
| DELETE | admin/wallet-campaigns/{wallet_campaign} | admin.wallet-campaigns.destroy | AdminWalletCampaignController@destroy | `wallet_campaigns.delete` | auth:staff |
| POST | admin/users/{user}/wallet-campaigns/{wallet_campaign}/trigger-allocation | admin.users.wallet-campaigns.trigger-allocation | TriggerCampaignAllocationController@__invoke | `wallet_campaigns.allocate` | auth:staff |
| POST | admin/wallet-campaigns/{wallet_campaign}/bulk-trigger-allocation | admin.wallet-campaigns.bulk-trigger-allocation | BulkCampaignAllocationController@__invoke | `wallet_campaigns.allocate` | auth:staff |

## Audit Logs

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/audit/admin-actions | admin.audit.admin-actions.index | AdminAuditLogController@index | `audits.admin_actions_view` | auth:staff |
| GET | admin/audit/admin-actions/{adminActionLog} | admin.audit.admin-actions.show | AdminAuditLogController@show | `audits.admin_actions_view` | auth:staff |
| POST | admin/audit/compliance-report | admin.audit.compliance-report | ComplianceReportController@__invoke | `audits.compliance_reports_view` | auth:staff |
| POST | admin/audit/suspicious-activity | admin.audit.suspicious-activity | SuspiciousActivityController@__invoke | `audits.suspicious_activity_view` | auth:staff |

## Blog Categories

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/blog/categories | admin.blog.categories.index | BlogCategoryController@index | `blog_categories.view_any` | auth:staff |
| POST | admin/blog/categories | admin.blog.categories.store | BlogCategoryController@store | `blog_categories.create` | auth:staff |
| GET | admin/blog/categories/{blog_category} | admin.blog.categories.show | BlogCategoryController@show | `blog_categories.view` | auth:staff |
| PUT/PATCH | admin/blog/categories/{blog_category} | admin.blog.categories.update | BlogCategoryController@update | `blog_categories.update` | auth:staff |
| DELETE | admin/blog/categories/{blog_category} | admin.blog.categories.destroy | BlogCategoryController@destroy | `blog_categories.delete` | auth:staff |

## Blog Posts

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/blog/posts | admin.blog.posts.index | BlogPostController@index | `blog_posts.view_any` | auth:staff |
| POST | admin/blog/posts | admin.blog.posts.store | BlogPostController@store | `blog_posts.create` | auth:staff |
| GET | admin/blog/posts/{post} | admin.blog.posts.show | BlogPostController@show | `blog_posts.view` | auth:staff |
| PUT/PATCH | admin/blog/posts/{post} | admin.blog.posts.update | BlogPostController@update | `blog_posts.update` | auth:staff |
| DELETE | admin/blog/posts/{post} | admin.blog.posts.destroy | BlogPostController@destroy | `blog_posts.delete` | auth:staff |

## Settings

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/settings | admin.settings.index | SettingController@index | `settings.view_any` | auth:staff |
| GET | admin/settings/contact-info | admin.settings.contact-info.show | ContactInfoController@show | `settings.view_any` | auth:staff |
| PUT/PATCH | admin/settings/contact-info | admin.settings.contact-info.update | ContactInfoController@update | `settings.update` | auth:staff |
| GET | admin/settings/about-us | admin.settings.about-us.show | AboutUsInfoController@show | `settings.view_any` | auth:staff |
| PUT/PATCH | admin/settings/about-us | admin.settings.about-us.update | AboutUsInfoController@update | `settings.update` | auth:staff |
| GET | admin/settings/collaboration | admin.settings.collaboration.show | CollaborationInfoController@show | `settings.view_any` | auth:staff |
| PUT/PATCH | admin/settings/collaboration | admin.settings.collaboration.update | CollaborationInfoController@update | `settings.update` | auth:staff |
| GET | admin/settings/footer | admin.settings.footer.show | FooterController@show | `settings.view_any` | auth:staff |
| PUT/PATCH | admin/settings/footer | admin.settings.footer.update | FooterController@update | `settings.update` | auth:staff |
| GET | admin/settings/header | admin.settings.header.show | HeaderController@show | `settings.view_any` | auth:staff |
| PUT/PATCH | admin/settings/header | admin.settings.header.update | HeaderController@update | `settings.update` | auth:staff |

## Sliders (Settings)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/settings/slider | admin.settings.slider.index | SliderController@index | `sliders.view_any` | auth:staff |
| POST | admin/settings/slider | admin.settings.slider.store | SliderController@store | `sliders.create` | auth:staff |
| GET | admin/settings/slider/{slider} | admin.settings.slider.show | SliderController@show | `sliders.view` | auth:staff |
| PUT/PATCH | admin/settings/slider/{slider} | admin.settings.slider.update | SliderController@update | `sliders.update` | auth:staff |
| DELETE | admin/settings/slider/{slider} | admin.settings.slider.destroy | SliderController@destroy | `sliders.delete` | auth:staff |
| PATCH | admin/settings/slider/{slider}/status | admin.settings.slider.status | UpdateSliderStatusController@__invoke | `sliders.update` | auth:staff |

## Partners (Settings)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/settings/partner | admin.settings.partner.index | PartnerController@index | `partners.view_any` | auth:staff |
| POST | admin/settings/partner | admin.settings.partner.store | PartnerController@store | `partners.create` | auth:staff |
| GET | admin/settings/partner/{partner} | admin.settings.partner.show | PartnerController@show | `partners.view` | auth:staff |
| PUT/PATCH | admin/settings/partner/{partner} | admin.settings.partner.update | PartnerController@update | `partners.update` | auth:staff |
| DELETE | admin/settings/partner/{partner} | admin.settings.partner.destroy | PartnerController@destroy | `partners.delete` | auth:staff |

## Home Page Blocks (Settings)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/settings/home-page-block | admin.settings.home-page-block.index | HomePageBlockController@index | `home_page_blocks.view_any` | auth:staff |
| POST | admin/settings/home-page-block | admin.settings.home-page-block.store | HomePageBlockController@store | `home_page_blocks.create` | auth:staff |
| GET | admin/settings/home-page-block/{homePageBlock} | admin.settings.home-page-block.show | HomePageBlockController@show | `home_page_blocks.view` | auth:staff |
| PUT/PATCH | admin/settings/home-page-block/{homePageBlock} | admin.settings.home-page-block.update | HomePageBlockController@update | `home_page_blocks.update` | auth:staff |
| DELETE | admin/settings/home-page-block/{homePageBlock} | admin.settings.home-page-block.destroy | HomePageBlockController@destroy | `home_page_blocks.delete` | auth:staff |

## Student Stories (Settings)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/settings/student-stories | admin.settings.student-stories.index | StudentStoryController@index | `student_stories.view_any` | auth:staff |
| POST | admin/settings/student-stories | admin.settings.student-stories.store | StudentStoryController@store | `student_stories.create` | auth:staff |
| GET | admin/settings/student-stories/{studentStory} | admin.settings.student-stories.show | StudentStoryController@show | `student_stories.view` | auth:staff |
| PUT/PATCH | admin/settings/student-stories/{studentStory} | admin.settings.student-stories.update | StudentStoryController@update | `student_stories.update` | auth:staff |
| DELETE | admin/settings/student-stories/{studentStory} | admin.settings.student-stories.destroy | StudentStoryController@destroy | `student_stories.delete` | auth:staff |

## Payment Gateway Settings

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/settings/payment-gateways | (unnamed) | PaymentGatewaySettingsController@index | `settings.payment_view` | auth:staff |
| GET | admin/settings/payment-gateways/{gateway} | (unnamed) | PaymentGatewaySettingsController@show | `settings.payment_view` | auth:staff |
| PUT/PATCH | admin/settings/payment-gateways/{gateway} | (unnamed) | PaymentGatewaySettingsController@update | `settings.payment_update` | auth:staff |

## Select Options (dropdown lists, no permission guard)

| Method | URI | Route Name | Controller | Permission | Guard |
|---|---|---|---|---|---|
| GET | admin/select-option/categories | admin.select-option.categories | CategorySelectOptionController@__invoke | — (no guard) | auth:staff |
| GET | admin/select-option/blog-categories | admin.select-option.blog-categories | BlogCategorySelectOptionController@__invoke | — (no guard) | auth:staff |
| GET | admin/select-option/terms | admin.select-option.terms | TermSelectOptionController@__invoke | — (no guard) | auth:staff |
| GET | admin/select-option/vendors | admin.select-option.vendors | VendorSelectOptionController@__invoke | — (no guard) | auth:staff |
| GET | admin/select-option/teachers | admin.select-option.teacherss | TeacherSelectOptionController@__invoke | — (no guard) | auth:staff |
| GET | admin/select-option/productables | admin.select-option.productables | ProductableSelectOptionController@__invoke | — (no guard) | auth:staff |
| GET | admin/select-option/staff | admin.select-option.staff | StaffSelectOptionController@__invoke | — (no guard) | auth:staff |
| GET | admin/select-option/customers | admin.select-option.customers | CustomerSelectOptionController@__invoke | — (no guard) | auth:staff |
| GET | admin/select-option/products/{productableType?} | admin.select-option.products | ProductSelectOptionController@__invoke | — (no guard) | auth:staff |

---

## All Permission Keys (for reference)

The full list of 160 permission keys from `PermissionEnum.php`:

### advice_requests
`advice_requests.create` · `advice_requests.delete` · `advice_requests.update` · `advice_requests.view` · `advice_requests.view_any`

### audits
`audits.admin_actions_view` · `audits.compliance_reports_view` · `audits.suspicious_activity_view`

### blog_categories
`blog_categories.create` · `blog_categories.delete` · `blog_categories.update` · `blog_categories.view` · `blog_categories.view_any`

### blog_posts
`blog_posts.create` · `blog_posts.delete` · `blog_posts.feature` · `blog_posts.publish` · `blog_posts.update` · `blog_posts.view` · `blog_posts.view_any`

### categories
`categories.create` · `categories.delete` · `categories.delete_own` · `categories.update` · `categories.update_own` · `categories.view` · `categories.view_any`

### courses
`courses.create` · `courses.delete` · `courses.delete_own` · `courses.update` · `courses.update_own` · `courses.view` · `courses.view_any`

### discounts
`discounts.create` · `discounts.delete` · `discounts.update` · `discounts.view` · `discounts.view_any`

### enrollments
`enrollments.create` · `enrollments.delete` · `enrollments.retry_provision` · `enrollments.update` · `enrollments.view` · `enrollments.view_any`

### files
`files.create` · `files.delete` · `files.delete_own` · `files.update` · `files.update_own` · `files.view` · `files.view_any`

### home_page_blocks
`home_page_blocks.create` · `home_page_blocks.delete` · `home_page_blocks.update` · `home_page_blocks.view` · `home_page_blocks.view_any`

### orders
`orders.approve` · `orders.create` · `orders.delete` · `orders.update` · `orders.view` · `orders.view_any`

### partners
`partners.create` · `partners.delete` · `partners.update` · `partners.view` · `partners.view_any`

### payments
`payments.create` · `payments.delete` · `payments.update` · `payments.view` · `payments.view_any`

### product_delivery_options
`product_delivery_options.create` · `product_delivery_options.delete` · `product_delivery_options.update` · `product_delivery_options.view` · `product_delivery_options.view_any`

### products
`products.create` · `products.delete` · `products.update` · `products.view` · `products.view_any`

### refunds
`refunds.create` · `refunds.delete` · `refunds.skip_gateway` · `refunds.update` · `refunds.update_status` · `refunds.view` · `refunds.view_any`

### reviews
`reviews.delete` · `reviews.update` · `reviews.update_featured_status` · `reviews.view` · `reviews.view_any`

### roles
`roles.create` · `roles.delete` · `roles.update` · `roles.view` · `roles.view_any`

### seminars
`seminars.create` · `seminars.delete` · `seminars.delete_own` · `seminars.update` · `seminars.update_own` · `seminars.view` · `seminars.view_any`

### settings
`settings.payment_update` · `settings.payment_view` · `settings.update` · `settings.view_any`

### sliders
`sliders.create` · `sliders.delete` · `sliders.update` · `sliders.view` · `sliders.view_any`

### staff
`staff.create` · `staff.delete` · `staff.impersonate` · `staff.update` · `staff.view` · `staff.view_any`

### student_stories
`student_stories.create` · `student_stories.delete` · `student_stories.update` · `student_stories.view` · `student_stories.view_any`

### teachers
`teachers.create` · `teachers.delete` · `teachers.update` · `teachers.view` · `teachers.view_any`

### terms
`terms.create` · `terms.delete` · `terms.update` · `terms.view` · `terms.view_any`

### users
`users.create` · `users.delete` · `users.update` · `users.view` · `users.view_any`

### vendors
`vendors.create` · `vendors.delete` · `vendors.update` · `vendors.view` · `vendors.view_any`

### wallets
`wallets.adjustment` · `wallets.create` · `wallets.deposit` · `wallets.view` · `wallets.view_any` · `wallets.withdrawal`

### wallet_campaigns
`wallet_campaigns.allocate` · `wallet_campaigns.create` · `wallet_campaigns.delete` · `wallet_campaigns.process_bonus` · `wallet_campaigns.update` · `wallet_campaigns.view` · `wallet_campaigns.view_any`

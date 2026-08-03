# Permission → Endpoint Map (Frontend)

> **For frontend developers**: Every admin API endpoint and the permission key required to access it.
> Permission keys match the string values in `app/Enums/PermissionEnum.php` and are what the backend checks.
>
> **How to use**: Before calling an endpoint, check whether the current user's role/permissions include the key shown in the **Permission** column. If the endpoint has **—**, no permission is required beyond having an authenticated admin session.
>
> **Super-admin note**: Any `Staff` with `is_admin = true` bypasses ALL permission checks. The permissions below are enforced only for non-admin staff.
>
> **Sign-in scoping**: All endpoints require `auth:staff` (admin login). The permission column lists what your own client-side UI should gate on to match backend enforcement.

## How to Read This Map

| Column | Description |
|---|---|
| Method | HTTP verb |
| URI | Path to call, relative to `api/v1` |
| Permission | The exact permission key required. Multiple values = any one suffices. "—" = no permission guard. |

---

## Staff Management

| Method | URI | Permission |
|---|---|---|
| GET | admin/staff | `staff.view_any` |
| POST | admin/staff | `staff.create` |
| GET | admin/staff/{staff} | `staff.view` (self-bypass: own record needs no permission) |
| PUT/PATCH | admin/staff/{staff} | `staff.update` (self-bypass: own record needs no permission) |
| DELETE | admin/staff/{staff} | `staff.delete` |

## Roles & Permissions

| Method | URI | Permission |
|---|---|---|
| GET | admin/roles | `roles.view_any` |
| POST | admin/roles | `roles.create` |
| GET | admin/roles/{role} | `roles.view` |
| PUT/PATCH | admin/roles/{role} | `roles.update` |
| DELETE | admin/roles/{role} | `roles.delete` |
| GET | admin/permissions | — (no guard) |

## Vendors

| Method | URI | Permission |
|---|---|---|
| GET | admin/vendors | `vendors.view_any` |
| POST | admin/vendors | `vendors.create` |
| GET | admin/vendors/{vendor} | `vendors.view` |
| PUT/PATCH | admin/vendors/{vendor} | `vendors.update` |
| DELETE | admin/vendors/{vendor} | `vendors.delete` |

## Teachers

| Method | URI | Permission |
|---|---|---|
| GET | admin/teachers | `teachers.view_any` |
| POST | admin/teachers | `teachers.create` |
| GET | admin/teachers/{teacher} | `teachers.view` |
| PUT/PATCH | admin/teachers/{teacher} | `teachers.update` |
| DELETE | admin/teachers/{teacher} | `teachers.delete` |

## Terms

| Method | URI | Permission |
|---|---|---|
| GET | admin/terms | `terms.view_any` |
| POST | admin/terms | `terms.create` |
| GET | admin/terms/{term} | `terms.view` |
| PUT/PATCH | admin/terms/{term} | `terms.update` |
| DELETE | admin/terms/{term} | `terms.delete` |

## Users (Customers)

| Method | URI | Permission |
|---|---|---|
| GET | admin/users | `users.view_any` |
| POST | admin/users | `users.create` |
| GET | admin/users/{user} | `users.view` |
| PUT/PATCH | admin/users/{user} | `users.update` |
| DELETE | admin/users/{user} | `users.delete` |

## Wallet (per User)

| Method | URI | Permission |
|---|---|---|
| GET | admin/users/{user}/wallet | `wallets.view` |
| POST | admin/users/{user}/wallet | `wallets.create` |
| POST | admin/users/{user}/wallet/deposit | `wallets.deposit` |
| POST | admin/users/{user}/wallet/withdrawal | `wallets.withdrawal` |
| POST | admin/users/{user}/wallet/adjustment | `wallets.adjustment` |

## Reviews

| Method | URI | Permission |
|---|---|---|
| GET | admin/reviews | `reviews.view_any` |
| GET | admin/reviews/{review} | `reviews.view` |
| DELETE | admin/reviews/{review} | `reviews.delete` |
| POST | admin/reviews/{review}/approve | `reviews.update` |
| POST | admin/reviews/{review}/reject | `reviews.update` |
| PATCH | admin/reviews/{review}/featured | `reviews.update_featured_status` |

## Advice Requests

| Method | URI | Permission |
|---|---|---|
| GET | admin/advice-requests | `advice_requests.view_any` |
| GET | admin/advice-requests/{advice_request} | `advice_requests.view` |
| PUT/PATCH | admin/advice-requests/{advice_request} | `advice_requests.update` |
| DELETE | admin/advice-requests/{advice_request} | `advice_requests.delete` |
| PATCH | admin/advice-requests/{adviceRequest}/status | `advice_requests.update` |

## Profile & Password (self-service)

| Method | URI | Permission |
|---|---|---|
| GET | admin/profile | — (self-service) |
| PUT/PATCH | admin/profile | — (self-service) |
| PUT | admin/change-password | — (self-service) |

## Categories

| Method | URI | Permission |
|---|---|---|
| GET | admin/categories | `categories.view_any` |
| POST | admin/categories | `categories.create` |
| GET | admin/categories/{category} | `categories.view` |
| PUT/PATCH | admin/categories/{category} | `categories.update` |
| DELETE | admin/categories/{category} | `categories.delete` |
| GET | admin/categories/{category}/items | `categories.view` |
| POST | admin/categories/{category}/good-for-start | `categories.update` |

## Courses

| Method | URI | Permission |
|---|---|---|
| GET | admin/courses | `courses.view_any` |
| POST | admin/courses | `courses.create` |
| GET | admin/courses/{course} | `courses.view` |
| PUT/PATCH | admin/courses/{course} | `courses.update` |
| DELETE | admin/courses/{course} | `courses.delete` |

## Digital Assets (uses FILE_* permissions)

| Method | URI | Permission |
|---|---|---|
| GET | admin/digital-assets | `files.view_any` |
| POST | admin/digital-assets | `files.create` |
| GET | admin/digital-assets/{digitalAsset} | `files.view` |
| PUT/PATCH | admin/digital-assets/{digitalAsset} | `files.update` |
| DELETE | admin/digital-assets/{digitalAsset} | `files.delete` |

## Seminars

| Method | URI | Permission |
|---|---|---|
| GET | admin/seminars | `seminars.view_any` |
| POST | admin/seminars | `seminars.create` |
| GET | admin/seminars/{seminar} | `seminars.view` |
| PUT/PATCH | admin/seminars/{seminar} | `seminars.update` |
| DELETE | admin/seminars/{seminar} | `seminars.delete` |

## Products

| Method | URI | Permission |
|---|---|---|
| GET | admin/products | `products.view_any` |
| POST | admin/products | `products.create` |
| GET | admin/products/{product} | `products.view` |
| PUT/PATCH | admin/products/{product} | `products.update` |
| DELETE | admin/products/{product} | `products.delete` |
| POST | admin/products/{product}/archive | `products.update` |

## Product Delivery Options

| Method | URI | Permission |
|---|---|---|
| GET | admin/products/{product}/delivery-options | `product_delivery_options.view_any` |
| POST | admin/products/{product}/delivery-options | `product_delivery_options.create` |
| GET | admin/products/{product}/delivery-options/{deliveryOption} | `product_delivery_options.view` |
| PUT/PATCH | admin/products/{product}/delivery-options/{deliveryOption} | `product_delivery_options.update` |
| DELETE | admin/products/{product}/delivery-options/{deliveryOption} | `product_delivery_options.delete` |

## Related Products

| Method | URI | Permission |
|---|---|---|
| GET | admin/products/{product}/related-products | `products.view` |
| POST | admin/products/{product}/related-products | `products.update` |
| DELETE | admin/products/{product}/related-products/{relatedProduct} | `products.update` |

## Orders

| Method | URI | Permission |
|---|---|---|
| GET | admin/orders | `orders.view_any` |
| POST | admin/orders | `orders.create` |
| GET | admin/orders/{order} | `orders.view` |
| PUT/PATCH | admin/orders/{order} | `orders.update` |
| DELETE | admin/orders/{order} | `orders.delete` |
| POST | admin/orders/preview | `orders.create` |
| POST | admin/orders/{order}/approve | `orders.approve` |

## Order Items

| Method | URI | Permission |
|---|---|---|
| GET | admin/orders/{order}/order-items | `orders.view` |
| GET | admin/orders/{order}/order-items/{order_item} | `orders.view` |

## Payments

| Method | URI | Permission |
|---|---|---|
| GET | admin/orders/{order}/payments | `payments.view` |
| POST | admin/orders/{order}/payments | `payments.create` |
| GET | admin/orders/{order}/payments/{payment} | `payments.view` |
| PUT/PATCH | admin/orders/{order}/payments/{payment} | `payments.update` |
| DELETE | admin/orders/{order}/payments/{payment} | `payments.delete` |
| GET | admin/orders/{order}/next-payment-details | `orders.view_any` |

## Digipay Payment Operations

| Method | URI | Permission |
|---|---|---|
| POST | admin/payments/{payment}/digipay/refund | `payments.update` |
| POST | admin/payments/{payment}/digipay/deliver | `payments.update` |
| POST | admin/payments/{payment}/digipay/reverse | `payments.delete` |
| POST | admin/payments/digipay/inquire-refund | `payments.view` |

## Refunds

| Method | URI | Permission                                                            |
|---|---|-----------------------------------------------------------------------|
| GET | admin/refunds | `refunds.view_any`                                                    |
| POST | admin/refunds | `refunds.create`                                                      |
| GET | admin/refunds/{refund} | `refunds.view`                                                        |
| PUT/PATCH | admin/refunds/{refund} | `refunds.update`                                                      |
| DELETE | admin/refunds/{refund} | `refunds.delete`                                                      |
| POST | admin/orders/{order}/refund | `refunds.create` + `refunds.skip_gateway` (if `skip_gateway` enabled) |
| PUT | admin/refunds/{refund}/status | `refunds.update_status`                                               |

## Discount Promotions

| Method | URI | Permission |
|---|---|---|
| GET | admin/discount-promotions | `discounts.view_any` |
| POST | admin/discount-promotions | `discounts.create` |
| GET | admin/discount-promotions/{discountPromotion} | `discounts.view` |
| PUT/PATCH | admin/discount-promotions/{discountPromotion} | `discounts.update` |
| DELETE | admin/discount-promotions/{discountPromotion} | `discounts.delete` |
| GET | admin/discount-promotions/statistics | `discounts.view_any` |
| PUT | admin/discount-promotions/{discountPromotion}/status | `discounts.update` |

## Discount Info (read-only metadata)

| Method | URI | Permission |
|---|---|---|
| GET | admin/discount-info | `discounts.view_any` |
| GET | admin/discount-info/conditions | `discounts.view_any` |
| GET | admin/discount-info/actions | `discounts.view_any` |
| GET | admin/discount-info/operators | `discounts.view_any` |
| GET | admin/discount-info/types | `discounts.view_any` |

## Enrollments

| Method | URI | Permission |
|---|---|---|
| GET | admin/enrollments | `enrollments.view_any` |
| GET | admin/enrollments/{enrollment} | `enrollments.view` |
| PUT/PATCH | admin/enrollments/{enrollment} | `enrollments.update` |
| DELETE | admin/enrollments/{enrollment} | `enrollments.delete` |
| POST | admin/enrollments/{enrollment}/change-status | `enrollments.update` |
| POST | admin/enrollments/{enrollment}/retry-provisioning | `enrollments.retry_provision` |

## File Management

| Method | URI | Permission |
|---|---|---|
| POST | admin/media/upload | — (no guard) |
| GET | admin/media/{media} | — (no guard) |
| POST | admin/private-file/upload | — (no guard) |
| GET | admin/private-file/{file} | `files.view_any` |
| GET | admin/private-file/{file}/download | `files.view_any` |

## Wallet Campaigns

| Method | URI | Permission |
|---|---|---|
| GET | admin/wallet-campaigns | `wallet_campaigns.view_any` |
| POST | admin/wallet-campaigns | `wallet_campaigns.create` |
| GET | admin/wallet-campaigns/{wallet_campaign} | `wallet_campaigns.view` |
| PUT/PATCH | admin/wallet-campaigns/{wallet_campaign} | `wallet_campaigns.update` |
| DELETE | admin/wallet-campaigns/{wallet_campaign} | `wallet_campaigns.delete` |
| POST | admin/users/{user}/wallet-campaigns/{wallet_campaign}/trigger-allocation | `wallet_campaigns.allocate` |
| POST | admin/wallet-campaigns/{wallet_campaign}/bulk-trigger-allocation | `wallet_campaigns.allocate` |

## Audit Logs

| Method | URI | Permission |
|---|---|---|
| GET | admin/audit/admin-actions | `audits.admin_actions_view` |
| GET | admin/audit/admin-actions/{adminActionLog} | `audits.admin_actions_view` |
| POST | admin/audit/compliance-report | `audits.compliance_reports_view` |
| POST | admin/audit/suspicious-activity | `audits.suspicious_activity_view` |

## Blog Categories

| Method | URI | Permission |
|---|---|---|
| GET | admin/blog/categories | `blog_categories.view_any` |
| POST | admin/blog/categories | `blog_categories.create` |
| GET | admin/blog/categories/{blog_category} | `blog_categories.view` |
| PUT/PATCH | admin/blog/categories/{blog_category} | `blog_categories.update` |
| DELETE | admin/blog/categories/{blog_category} | `blog_categories.delete` |

## Blog Posts

| Method | URI | Permission |
|---|---|---|
| GET | admin/blog/posts | `blog_posts.view_any` |
| POST | admin/blog/posts | `blog_posts.create` |
| GET | admin/blog/posts/{post} | `blog_posts.view` |
| PUT/PATCH | admin/blog/posts/{post} | `blog_posts.update` |
| DELETE | admin/blog/posts/{post} | `blog_posts.delete` |

## Settings

| Method | URI | Permission |
|---|---|---|
| GET | admin/settings | `settings.view_any` |
| GET | admin/settings/contact-info | `settings.view_any` |
| PUT/PATCH | admin/settings/contact-info | `settings.update` |
| GET | admin/settings/about-us | `settings.view_any` |
| PUT/PATCH | admin/settings/about-us | `settings.update` |
| GET | admin/settings/collaboration | `settings.view_any` |
| PUT/PATCH | admin/settings/collaboration | `settings.update` |
| GET | admin/settings/footer | `settings.view_any` |
| PUT/PATCH | admin/settings/footer | `settings.update` |
| GET | admin/settings/header | `settings.view_any` |
| PUT/PATCH | admin/settings/header | `settings.update` |

## Sliders (Settings)

| Method | URI | Permission |
|---|---|---|
| GET | admin/settings/slider | `sliders.view_any` |
| POST | admin/settings/slider | `sliders.create` |
| GET | admin/settings/slider/{slider} | `sliders.view` |
| PUT/PATCH | admin/settings/slider/{slider} | `sliders.update` |
| DELETE | admin/settings/slider/{slider} | `sliders.delete` |
| PATCH | admin/settings/slider/{slider}/status | `sliders.update` |

## Partners (Settings)

| Method | URI | Permission |
|---|---|---|
| GET | admin/settings/partner | `partners.view_any` |
| POST | admin/settings/partner | `partners.create` |
| GET | admin/settings/partner/{partner} | `partners.view` |
| PUT/PATCH | admin/settings/partner/{partner} | `partners.update` |
| DELETE | admin/settings/partner/{partner} | `partners.delete` |

## Home Page Blocks (Settings)

| Method | URI | Permission |
|---|---|---|
| GET | admin/settings/home-page-block | `home_page_blocks.view_any` |
| POST | admin/settings/home-page-block | `home_page_blocks.create` |
| GET | admin/settings/home-page-block/{homePageBlock} | `home_page_blocks.view` |
| PUT/PATCH | admin/settings/home-page-block/{homePageBlock} | `home_page_blocks.update` |
| DELETE | admin/settings/home-page-block/{homePageBlock} | `home_page_blocks.delete` |

## Student Stories (Settings)

| Method | URI | Permission |
|---|---|---|
| GET | admin/settings/student-stories | `student_stories.view_any` |
| POST | admin/settings/student-stories | `student_stories.create` |
| GET | admin/settings/student-stories/{studentStory} | `student_stories.view` |
| PUT/PATCH | admin/settings/student-stories/{studentStory} | `student_stories.update` |
| DELETE | admin/settings/student-stories/{studentStory} | `student_stories.delete` |

## Payment Gateway Settings

| Method | URI | Permission |
|---|---|---|
| GET | admin/settings/payment-gateways | `settings.payment_view` |
| GET | admin/settings/payment-gateways/{gateway} | `settings.payment_view` |
| PUT/PATCH | admin/settings/payment-gateways/{gateway} | `settings.payment_update` |

## Select Options (dropdown lists, no permission guard)

| Method | URI | Permission |
|---|---|---|
| GET | admin/select-option/categories | — (no guard) |
| GET | admin/select-option/blog-categories | — (no guard) |
| GET | admin/select-option/terms | — (no guard) |
| GET | admin/select-option/vendors | — (no guard) |
| GET | admin/select-option/teachers | — (no guard) |
| GET | admin/select-option/productables | — (no guard) |
| GET | admin/select-option/staff | — (no guard) |
| GET | admin/select-option/customers | — (no guard) |
| GET | admin/select-option/products/{productableType?} | — (no guard) |

# Permission Audit: Holes, Missing Guards, and Suggestions

> This document identifies gaps in the permission system: endpoints without guards, unused permissions, inconsistencies, and suggestions for improvement.
>
> **Source data**: 160 permissions in `PermissionEnum.php`, 28 admin policies, 177 `Gate::authorize()` calls across 60 controllers, 87 total admin controllers.

---

## 1. Endpoints Without Permission Guard

These admin endpoints are behind `auth:staff` (authentication) but have NO `Gate::authorize()` or `abort_unless(can())` call. Any authenticated staff member can access them regardless of their assigned permissions.

> **Last updated**: This audit reflects the permission guard fixes applied in this batch (Order preview, Order Items, Payment Gateway Settings).

### 1.1 Select Option Endpoints (9 endpoints — intentional, but worth noting)

| Controller | URI | Notes |
|---|---|---|
| CategorySelectOptionController | admin/select-option/categories | Dropdown list for forms |
| BlogCategorySelectOptionController | admin/select-option/blog-categories | Dropdown list for forms |
| TermSelectOptionController | admin/select-option/terms | Dropdown list for forms |
| VendorSelectOptionController | admin/select-option/vendors | Dropdown list for forms |
| TeacherSelectOptionController | admin/select-option/teachers | Dropdown list for forms (note: route name has typo "teacherss") |
| ProductableSelectOptionController | admin/select-option/productables | Dropdown list for forms |
| StaffSelectOptionController | admin/select-option/staff | Dropdown list for forms |
| CustomerSelectOptionController | admin/select-option/customers | Dropdown list for forms |
| ProductSelectOptionController | admin/select-option/products/{productableType?} | Dropdown list for forms |

**Suggestion**: These are likely intentional — any authenticated staff needs dropdown lists to fill forms. However, if any select option exposes sensitive data (e.g., staff email/phone in staff select), consider adding `view_any` permissions for the relevant resources.

### 1.2 File Upload Endpoints (3 endpoints — SECURITY CONCERN)

| Controller | URI | Risk |
|---|---|---|
| UploadMediaController | admin/media/upload | Any staff can upload media files |
| ViewMediaController | admin/media/{media} | Any staff can view any media |
| UploadPrivateController | admin/private-file/upload | Any staff can upload private files |

**Suggestion**: Add `files.create` guard to upload endpoints and `files.view` to view endpoint. The `files.*` permission group exists in the enum but upload/view media controllers bypass it entirely.

### 1.3 Payment Gateway Settings (3 endpoints — ✅ FIXED)

| Controller | URI | Risk |
|---|---|---|
| PaymentGatewaySettingsController@index | admin/settings/payment-gateways | Any staff can view payment gateway configs (may expose credentials) |
| PaymentGatewaySettingsController@show | admin/settings/payment-gateways/{gateway} | Any staff can view specific gateway config |
| PaymentGatewaySettingsController@update | admin/settings/payment-gateways/{gateway} | Any staff can modify payment gateway settings |

**Status**: ✅ **FIXED** — `Gate::authorize('view-payment', Setting::class)` added to index/show and `Gate::authorize('update-payment', Setting::class)` to update. Requires `settings.payment_view` and `settings.payment_update` (new permissions). `SettingPolicy` gained `viewPayment()` and `updatePayment()` methods.

### 1.4 Order Preview & Items (3 endpoints — ✅ FIXED)

| Controller | URI | Risk |
|---|---|---|
| OrderCalculationController | admin/orders/preview | Any staff can preview order calculations |
| OrderItemController@index | admin/orders/{order}/order-items | Any staff can view order items |
| OrderItemController@show | admin/orders/{order}/order-items/{order_item} | Any staff can view a specific order item |

**Status**: ✅ **FIXED** — `Gate::authorize('create', Order::class)` added to `OrderCalculationController` (requires `orders.create`), `Gate::authorize('view', $order)` added to `OrderItemController@index`/`@show` (requires `orders.view`).

### 1.5 Profile & Password (3 endpoints — intentional, self-service)

| Controller | URI | Notes |
|---|---|---|
| StaffProfileController@show | admin/profile | Self-service: staff views own profile |
| StaffProfileController@update | admin/profile | Self-service: staff updates own profile |
| StaffChangePasswordController | admin/change-password | Self-service: staff changes own password |

**Status**: Intentional — these operate on the authenticated user's own data. No permission needed.

### 1.6 Permission List Endpoint (1 endpoint — intentional)

| Controller | URI | Notes |
|---|---|---|
| PermissionController | admin/permissions | Returns all available permissions for UI rendering |

**Status**: Likely intentional — needed by the role management UI to show available permissions. Any staff who can access the admin panel needs this to render permission assignment forms.

### 1.7 Wallet Update/Delete (2 endpoints — ✅ FIXED, routes removed)

| Controller | URI | Status |
|---|---|---|
| AdminWalletController@update | admin/users/{user}/wallet (PUT/PATCH) | ✅ Route removed |
| AdminWalletController@destroy | admin/users/{user}/wallet (DELETE) | ✅ Route removed |

**Status**: ✅ **FIXED** — `apiSingleton('wallet')` now uses `->only('show', 'store')`, removing the update/destroy routes that pointed to missing controller methods (previously would 500). The `wallets.update` and `wallets.delete` permissions were removed from the enum in this batch.

### 1.8 Auth Endpoints (7 endpoints — intentional, pre-auth)

| Controller | Notes |
|---|---|
| StaffInitiateAuthController | Pre-authentication: initiate login flow |
| StaffPasswordLoginController | Pre-authentication: password login |
| StaffOtpAuthenticationController | Pre-authentication: OTP verify |
| StaffResendOtpController | Pre-authentication: resend OTP |
| StaffForgotPasswordController | Pre-authentication: forgot password |
| StaffResetPasswordController | Pre-authentication: reset password |
| StaffLogoutController | Post-authentication: logout (no permission needed) |

**Status**: Intentional — auth endpoints cannot require permissions (you need to authenticate first).

---

## 2. Unused Permissions (defined in enum, never enforced)

These permissions exist in `PermissionEnum.php` but no policy method or controller `Gate::authorize()` call references them. They are "dead" permissions — assigned to roles but never checked.

| Permission Key | Expected Use | Status |
|---|---|---|
| `courses.update_own` | Course owner can edit own courses | No policy method checks this. `CoursePolicy::update()` uses `COURSES_UPDATE` only |
| `courses.delete_own` | Course owner can delete own courses | No policy method checks this |
| `seminars.update_own` | Seminar owner can edit own seminars | Unused |
| `seminars.delete_own` | Seminar owner can delete own seminars | Unused |
| `categories.update_own` | Category owner can edit own categories | Unused |
| `categories.delete_own` | Category owner can delete own categories | Unused |
| `files.update_own` | File owner can edit own files | Unused |
| `files.delete_own` | File owner can delete own files | Unused |
| `staff.impersonate` | Staff can impersonate other staff | No controller endpoint, no policy method. Defined but never enforced |
| `wallet_campaigns.process_bonus` | Process wallet campaign bonus allocation | No controller endpoint, no policy method |
| `enrollments.create` | Create enrollment | `EnrollmentPolicy` has no `create()` method. Enrollments are auto-created from orders, not manually |
| `blog_posts.publish` | Publish a blog post | No controller endpoint or policy method for publishing |
| `blog_posts.feature` | Feature a blog post | No controller endpoint or policy method for featuring |
| `audits.compliance_reports_view` | View compliance reports | Used via `abort_unless(can())` in `ComplianceReportController` — NOT via `Gate::authorize()` + policy. Inconsistent enforcement pattern |
| `audits.suspicious_activity_view` | View suspicious activity reports | Used via `abort_unless(can())` in `SuspiciousActivityController` — NOT via `Gate::authorize()` + policy. Inconsistent enforcement pattern |

**Removed from enum in this batch** (now truly gone, no longer assignable): `staff.manage_roles`, `wallets.update`, `wallets.delete`. The `wallets.update`/`wallets.delete` removal resolves the earlier dead-permission finding; `staff.manage_roles` removal means role assignment is no longer gated by a dedicated permission.

**Suggestion**: For permissions that are genuinely unused (no business need), remove them from `config/permission-generator.php` and regenerate. For permissions that SHOULD be enforced (e.g., `staff.impersonate`, `blog_posts.publish`), add the corresponding controller endpoints and policy methods.

---

## 3. Inconsistencies

### 3.1 BlogCategoryPolicy bypasses Gate::before super-admin bypass — ✅ FIXED

**Issue**: `BlogCategoryPolicy` used `$user->hasPermissionTo(PermissionEnum::BLOG_CATEGORY_VIEW_ANY->value)` instead of `$user->can(...)`. The spatie `hasPermissionTo()` method checks the permission registry directly, bypassing Laravel's `Gate` system. This means the `Gate::before` super-admin bypass (which returns `true` for any `Staff` with `is_admin=true`) did NOT apply to blog category operations.

**Status**: ✅ **FIXED** — `BlogCategoryPolicy` now uses `$user->can(PermissionEnum::BLOG_CATEGORY_*->value)` in all methods, matching the pattern used by all other policies. Super-admin bypass now applies to blog categories.

### 3.2 ComplianceReport and SuspiciousActivity use abort_unless instead of Gate::authorize

**Issue**: `ComplianceReportController` and `SuspiciousActivityController` use `abort_unless(auth()->user()->can(PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW->value), 403)` instead of `Gate::authorize()`. While functionally similar, this bypasses the policy system and the `Gate::before` hook.

**Impact**: Super-admin bypass (`Gate::before`) does NOT apply to these endpoints. A super-admin without explicitly assigned `audits.compliance_reports_view` or `audits.suspicious_activity_view` permissions will be denied.

**Fix**: Replace `abort_unless(auth()->user()->can(...), 403)` with `Gate::authorize('complianceReport', AdminActionLog::class)` and add corresponding policy methods to `AdminActionLogPolicy`, or create a dedicated `AuditPolicy`. This ensures the `Gate::before` super-admin bypass applies consistently.

### 3.3 DigitalAssetPolicy uses FILE_* permissions

**Issue**: `DigitalAssetPolicy` guards `DigitalAsset` model using `FILE_*` permissions (`files.view_any`, `files.create`, `files.view`, `files.update`, `files.delete`). There are no `digital_assets.*` permissions in the enum.

**Impact**: The permission key for digital asset endpoints is `files.*`, not `digital_assets.*`. This is by design (digital assets are managed as files) but may confuse developers.

**Suggestion**: Document this mapping clearly. If digital assets should have their own permission group, add `digital_assets` to `config/permission-generator.php` and update the policy. Otherwise, add a comment in the policy explaining the intentional reuse of `files.*`.

### 3.4 PaymentPolicy uses PAYMENT_UPDATE for refund and deliver

**Issue**: `PaymentPolicy` maps both `refund` and `deliver` abilities to `PAYMENT_UPDATE`. This means a staff member with `payments.update` can both refund and deliver payments.

**Suggestion**: If refund and deliver are distinct business operations with different risk levels, consider splitting into separate permissions (e.g., `payments.refund`, `payments.deliver`). If they're intentionally the same action class, document why they share a permission.

### 3.5 EnrollmentPolicy maps update and changeStatus to the same permission

**Issue**: Both `update()` and `changeStatus()` in `EnrollmentPolicy` map to `ENROLLMENT_UPDATE`. A staff member who can update enrollment data can also change enrollment status (and vice versa).

**Suggestion**: If changing status is a more sensitive operation, consider a separate `enrollments.change_status` permission. If they're intentionally the same access level, no change needed.

### 3.6 Enum-passing style inconsistency (cosmetic)

**Issue**: ~10 policies pass `PermissionEnum::X` directly to `$user->can()`, while ~18 policies use `PermissionEnum::X->value`. Both work in Laravel 12 (`Gate::inspect()` calls `enum_value()` which handles backed enums). This is purely cosmetic.

**Files using enum directly (no ->value)**: StaffPolicy, VendorPolicy, TermPolicy, RefundPolicy (partial), SettingPolicy, HomePageBlockPolicy, PartnerPolicy, StudentStoryPolicy, BlogCategoryPolicy.

**Suggestion**: Standardize on one style. Using `->value` is more explicit and avoids confusion. (Note: `SettingPolicy`'s new `viewPayment()`/`updatePayment()` methods use `->value`; `BlogCategoryPolicy` was converted to `->value` in this batch.)

### 3.7 Route name typo: "teacherss"

**Issue**: In `select_option.php`, the route name for teachers select option is `admin.select-option.teacherss` (double "s"). All other select option routes use singular resource names.

**Fix**: Rename to `admin.select-option.teachers` in the route definition.

---

## 4. Deliberate Self-Bypass in StaffPolicy

### 4.1 StaffPolicy::view() — self-view without permission

**Code**: `$user->can(PermissionEnum::STAFF_VIEW->value) || $user->id === $model->id`

**Impact**: Any staff member can view their own profile record without `staff.view` permission. This is deliberate (self-service) and low risk.

### 4.2 StaffPolicy::update() — self-update without permission

**Code**: `if ($user->id === $model->id) return true;`

**Impact**: Any staff member can update their own Staff record without `staff.update` permission. This is potentially risky depending on what fields are editable — if a staff member can change their own `is_admin` flag or role assignments, this is a privilege escalation vulnerability.

**Suggestion**: Review what fields `StaffController@update` allows editing when `$staff->id === auth()->id()`. If the self-update path allows changing `is_admin`, `active`, or role assignments, restrict it to profile fields only (name, email, phone, avatar) and require `staff.update` for sensitive fields.

---

## 5. Global Super-Admin Bypass

**Location**: `app/Providers/AuthServiceProvider.php:82-89`

**Code**:
```php
Gate::before(function ($user, $ability) {
    if ($user instanceof Staff && $user->is_admin) {
        return true;
    }
    return null;
});
```

**Impact**: Any `Staff` with `is_admin = true` passes ALL `Gate::authorize()` checks, regardless of assigned permissions. This effectively makes `PermissionEnum` checks meaningless for admin staff.

**Exceptions** (super-admin bypass does NOT apply):
- `ComplianceReportController` — uses `abort_unless(can())` which... actually `can()` DOES go through Gate, so `Gate::before` DOES apply here. The inconsistency is code style (abort_unless vs Gate::authorize), not bypass behavior.
- `SuspiciousActivityController` — same as above. `can()` goes through Gate, so super-admin bypass applies.

**Actual exception list** (super-admin bypass genuinely does NOT apply):
- None remaining — the `BlogCategoryPolicy` exception was fixed in this batch (converted `hasPermissionTo()` → `can()`).

**Suggestion**: The super-admin bypass is a common pattern, but consider whether `is_admin` should be the only criterion or whether super-admin should also have all permissions explicitly assigned. Document the bypass clearly for security reviewers.

---

## 6. Missing Policy Methods

These policy methods are expected by the standard CRUD pattern but are absent:

| Policy | Missing Methods | Impact |
|---|---|---|
| `WalletPolicy` | `update()`, `delete()` | Now moot — the wallet update/destroy routes were removed (`->only('show','store')`) in this batch. `wallets.update` and `wallets.delete` permissions removed from enum. |
| `PaymentPolicy` | `view()`, `create()`, `update()`, `delete()` | Standard CRUD methods absent. Uses custom abilities: `refund`, `deliver`, `reverse`, `inquire`, `viewAny`. The `PaymentController` calls standard `view`, `create`, `update`, `delete` abilities which are NOT defined in the policy — these would fail unless the Gate falls back to a default deny. |
| `EnrollmentPolicy` | `create()` | `ENROLLMENT_CREATE` permission unused. Enrollments are auto-created, not manually created via admin. |
| `AdminActionLogPolicy` | Only `viewAny()` and `view()` exist | Read-only audit log — no create/update/delete. Intentional. But `AUDIT_COMPLIANCE_REPORTS_VIEW` and `AUDIT_SUSPICIOUS_ACTIVITY_VIEW` are checked in controllers, not via policy methods. |
| `SettingPolicy` | Only `viewAny()` and `update()` exist | No `view()`, `create()`, `delete()`. Intentional — settings are a singleton-like resource. **Updated in this batch**: added `viewPayment()` and `updatePayment()` for the newly-guarded payment gateway settings. |
| `ReviewPolicy` | No `create()` | Intentional — reviews are submitted by customers, not admins. |

**Critical**: `PaymentPolicy` is missing `view()`, `create()`, `update()`, `delete()` methods, but `PaymentController` calls `Gate::authorize('view', ...)`, `Gate::authorize('create', ...)`, `Gate::authorize('update', ...)`, `Gate::authorize('delete', ...)`. This means these Gate calls will return `false` (no policy method found → default deny) UNLESS the super-admin bypass applies. Non-admin staff CANNOT perform standard payment CRUD operations. This may be intentional (payments are only managed via custom abilities like `refund`, `deliver`, `reverse`) or a bug.

---

## 7. Summary of Recommendations

### ✅ Resolved in this batch
1. ✅ Payment gateway settings now guarded (`settings.payment_view` / `settings.payment_update` via `SettingPolicy::viewPayment`/`updatePayment`)
2. ✅ `BlogCategoryPolicy` converted to `can()` — super-admin bypass now applies to blog categories
3. ✅ `OrderItemController` index/show guarded with `orders.view`
4. ✅ `OrderCalculationController` (order preview) guarded with `orders.create`
5. ✅ Dead permissions removed from enum: `staff.manage_roles`, `wallets.update`, `wallets.delete`
6. ✅ Wallet update/destroy routes removed (`->only('show','store')`) — no more dangling 500 routes

### Remaining — High Priority (security concerns)
1. Add permission guards to file upload controllers (`UploadMediaController`, `UploadPrivateController`, `ViewMediaController`)
2. Review `StaffPolicy::update()` self-bypass — ensure self-update cannot change `is_admin` or role assignments

### Remaining — Medium Priority (consistency)
3. Fix route name typo: `admin.select-option.teacherss` → `admin.select-option.teachers`
4. Verify `PaymentController` standard CRUD vs `PaymentPolicy` custom abilities — add missing policy methods or use custom abilities in controller

### Remaining — Low Priority (cleanup)
5. Remove or implement unused permissions: `staff.impersonate`, `blog_posts.publish`, `blog_posts.feature`, `wallet_campaigns.process_bonus`, all `*_own` variants
6. Standardize enum-passing style (use `->value` everywhere)
7. Consider splitting `payments.update` into `payments.refund` and `payments.deliver` if they're different risk levels
8. Document the `DigitalAssetPolicy` → `files.*` permission mapping in the policy file

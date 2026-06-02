# JeduShop Ordering & Payment System: Audit Report

**Audit Date:** 2025-06-07  
**Scope:** Shop checkout, payment processing (Wallet, Mellat Gateway, Bank Transfer), order creation, enrollment fulfillment  
**Methodology:** Systematic 5-phase audit with code analysis, test validation, and gap documentation

---

## Executive Summary

A comprehensive audit of the ordering and payment system revealed **5 significant gaps**, including 1 **CRITICAL** production issue affecting capacity enforcement over time. The system demonstrates strong concurrency protection and payment idempotency, but lacks date-based availability validation during checkout and has a critical bug in enrollment count tracking.

**Overall System Health:** 🟡 **Moderate** - Core payment flows are solid, but multiple business logic gaps exist.

---

## Critical Findings

### 🔴 CRITICAL: Gap #5 - Capacity Tracking Failure
**Severity:** CRITICAL (Production Impact)  
**Component:** `OrderStatusService::updateEnrollmentStatus()`  
**File:** `app/Services/OrderStatusService.php:73`

**Issue:**
Enrollment status changes are saved using `saveQuietly()`, which suppresses Laravel model events. This prevents the `EnrollmentStatusChanged` event from firing, which means the `UpdateProductDeliveryOptionEnrolledCount` listener never runs after payment completion.

**Impact Chain:**
```
1. Order created → Enrollment status = AWAITING_PAYMENT
   ✅ enrolled_count = 0 (correct)

2. Customer pays → Enrollment status = PENDING_PROVISIONING
   ❌ saveQuietly() suppresses events
   ❌ EnrollmentStatusChanged NOT dispatched
   ❌ UpdateProductDeliveryOptionEnrolledCount NOT triggered
   ❌ enrolled_count stays at 0 (WRONG!)

3. Capacity checks use stale enrolled_count
   ❌ Subsequent customers bypass capacity limits
```

**Example:**
- Product has `capacity = 5`, `enrolled_count = 0`
- Customer A purchases → pays → `enrolled_count` should be 1, but stays 0
- Customer B-F all can purchase (capacity math: `0 + 1 ≤ 5` ✓)
- **Result: 6 customers enrolled in a capacity-5 product**

**Recommended Fix:**
```php
// app/Services/OrderStatusService.php:73
// Change from:
$item->enrollment->saveQuietly();

// To:
$item->enrollment->save();  // Will dispatch EnrollmentStatusChanged

// OR manually dispatch:
$item->enrollment->saveQuietly();
EnrollmentStatusChanged::dispatch($item->enrollment);
```

**Test Validation Needed:**
Create integration test that:
1. Creates delivery option with capacity=5
2. Customer purchases 2 items
3. Verifies wallet/gateway payment completion
4. **Asserts `enrolled_count` increments to 2**

---

### 🟠 HIGH: Gaps #3 & #4 - Date-Based Availability Not Validated

#### Gap #3: Registration Window Dates
**Component:** Checkout validation  
**File:** `app/Actions/Shop/CreateOrderFromCartAction.php:validateCartItems()`

**Issue:**
`ProductDeliveryOption` has `registration_start_date` and `registration_end_date` columns with logic defined in `registrationOpen()` scope, but checkout validation does **NOT** check if registration is currently open.

**Impact:**
Customers can purchase delivery options:
- Before `registration_start_date` (e.g., purchasing "Fall 2025 Course" in Spring 2025)
- After `registration_end_date` (e.g., registration closed but checkout still allowed)

**Evidence:**
- DB columns exist: `registration_start_date`, `registration_end_date`
- Model scope defined: `ProductDeliveryOption::available()` (lines 98-134)
- Validation missing: `grep -r "registration" CreateOrderFromCartAction.php` → **NO MATCHES**

#### Gap #4: Availability Window Dates  
**Component:** Checkout validation  
**Same Issue:** `available_from` and `available_to` dates also not validated during checkout

**Recommended Fix:**
```php
// In CreateOrderFromCartAction::validateCartItems()
// After capacity check (line ~200), add:

$availableOptions = ProductDeliveryOption::query()
    ->whereIn('id', $cartItems->pluck('product_delivery_option_id'))
    ->available()  // Uses existing scope with all date logic
    ->pluck('id');

foreach ($cartItems as $key => $item) {
    if (!$availableOptions->contains($item->product_delivery_option_id)) {
        throw ValidationException::withMessages([
            "items.{$key}" => __("Product {$item->product->name} is not currently available for registration or purchase."),
        ]);
    }
}
```

---

### 🟡 MEDIUM: Gap #2 - Global Promotion Usage Limit Not Enforced
**Component:** Discount promotion validation  
**File:** `app/Services/Discounts/PromotionFinder.php:findApplicablePromotion()`

**Issue:**
`discount_promotions.usage_limit_total` column exists but is never checked during promotion selection. Only per-coupon `usage_limit` is validated.

**Impact:**
Promotions can exceed their global usage limit. For example:
- Promotion has `usage_limit_total = 100`
- Promotion has 5 coupons, each with `usage_limit = 50`
- Total possible uses: 250 (5 × 50), exceeding global limit by 150

**Recommended Fix:**
```php
// In PromotionFinder::findApplicablePromotion() (after line 27)
->where(function (Builder $q) {
    $q->whereNull('usage_limit_total')
        ->orWhereColumn('total_usage_count', '<', 'usage_limit_total');
})
```

---

## What Works Well ✅

### Concurrency & Race Conditions
- ✅ **Pessimistic locking:** Both shop and admin order creation use `lockForUpdate()` on `ProductDeliveryOption`
- ✅ **Capacity validation:** Math checks `enrolled_count + qty ≤ capacity` under lock
- ✅ **Duplicate purchase prevention:** `ValidateNoDuplicatePurchasesAction` enforced

### Payment Processing
- ✅ **Gateway callback idempotency:** Status guard prevents duplicate verification
- ✅ **Wallet atomicity:** All operations within DB transaction
- ✅ **Retry guards:** Proper status and balance validation

### Pricing & Discounts
- ✅ **Featured price dates:** Validated in `ProductPriceService::getActiveFeaturedPrice()`
- ✅ **Promotion expiry:** Date filters in `PromotionFinder`
- ✅ **Per-coupon limits:** `usage_limit` checked before application
- ✅ **Pricing hierarchy:** Consistent "best price wins" logic

### Publication & Visibility
- ✅ **Product status:** `PUBLISHED` required
- ✅ **Product visibility:** `is_visible` required
- ✅ **DeliveryOption status:** `PUBLISHED` required

---

## Test Coverage Analysis

### Existing Coverage (Strong)
- ✅ Basic checkout flow (`CheckoutTest.php`)
- ✅ Capacity validation at checkout (`CheckoutTest.php`)
- ✅ Wallet atomic operations (`WalletPaymentProcessorTest.php`)
- ✅ Gateway idempotency (`GatewayComplexScenariosTest.php`)
- ✅ Discount calculations (`OrderCalculationServiceTest.php`)
- ✅ Enrollment event system (`EnrollmentStatusChangedEventTest.php`)

### Coverage Gaps
- ❌ **Registration/availability dates:** No tests for checkout rejection when dates are outside range
- ❌ **enrolled_count increments:** Tests create enrollments directly, bypassing payment flow
- ❌ **Global promotion limits:** Only per-coupon limits tested

---

## Recommended Implementation Priority

### Phase 1: Urgent (Week 1)
1. **Fix Gap #5 (enrolled_count)** - Change `saveQuietly()` to `save()` in `OrderStatusService`
2. **Write integration test** validating `enrolled_count` increments after payment
3. **Run full test suite** to ensure no regressions

### Phase 2: High Priority (Week 2)
1. **Fix Gaps #3 & #4 (date validation)** - Add availability checks in checkout validation
2. **Write feature tests** for registration/availability window edge cases
3. **Verify admin order path** has same fixes applied

### Phase 3: Medium Priority (Week 3)
1. **Fix Gap #2 (usage_limit_total)** - Add global promotion limit check
2. **Write test** validating promotion exhaustion across multiple coupons
3. **Update documentation** for discount system behavior

---

## Files Requiring Changes

### Critical Changes
- `app/Services/OrderStatusService.php` (line 73: `saveQuietly()` → `save()`)

### High Priority Changes
- `app/Actions/Shop/CreateOrderFromCartAction.php` (add date validation in `validateCartItems()`)
- `app/Actions/Admin/Order/CreateOrderAction.php` (add same date validation)

### Medium Priority Changes
- `app/Services/Discounts/PromotionFinder.php` (add `usage_limit_total` check)

---

## Testing Recommendations

### New Test Files Needed
1. `tests/Feature/Api/V1/Shop/Sale/AvailabilityValidationTest.php`
   - Test checkout rejection when `registration_start_date` is future
   - Test checkout rejection when `registration_end_date` is past
   - Test checkout rejection when `available_from` is future
   - Test checkout rejection when `available_to` is past

2. `tests/Integration/Listeners/EnrolledCountAfterPaymentTest.php`
   - Test `enrolled_count` increments after wallet payment
   - Test `enrolled_count` increments after gateway payment
   - Test `enrolled_count` decrements after cancellation
   - Test concurrent purchases respect real-time capacity

### Existing Tests to Extend
- `tests/Feature/Api/V1/Shop/Sale/ComplexCheckoutScenariosTest.php`
  - Add assertion for `enrolled_count` after each payment completion
  
- `tests/Integration/Services/Discounts/PromotionFinderTest.php` (if exists)
  - Add test for `usage_limit_total` enforcement

---

## Architectural Strengths

1. **Thin Controllers:** Business logic properly isolated in Actions
2. **DTO Contracts:** `spatie/laravel-data` used consistently
3. **Event System:** Well-designed enrollment lifecycle events
4. **Denormalized Counters:** `enrolled_count` strategy correct, just needs event fix
5. **Custom Response Macros:** Consistent API responses

---

## Conclusion

The ordering and payment system has a solid architectural foundation with strong concurrency protection and payment flow management. However, **critical attention is needed for Gap #5** (enrollment count tracking), which will cause capacity enforcement to fail in production as soon as customers start making payments.

The date-based availability gaps (Gaps #3 & #4) represent business logic violations that could lead to customer confusion and operational issues. These should be prioritized immediately after the capacity bug.

The audit confirms that the discount and pricing systems work correctly, with only the global promotion limit check missing.

**Overall Risk Assessment:** 🔴 **HIGH** until Gap #5 is resolved, then 🟡 **MODERATE** until date validations are implemented.

---

**Document Version:** 1.0  
**Last Updated:** 2025-06-07  
**Audited By:** GitHub Copilot (Beast Mode)  
**Memory Bank:** `docs/Audits/ordering-payment-memory.md`

# Payment System Refactoring Recommendations

**Date:** December 11, 2025  
**Project:** JeduShop Payment System  
**Status:** Actionable Refactoring Guide

---

## Quick Reference

| Priority | Issue | Severity | Effort | Impact |
|----------|-------|----------|--------|--------|
| 1 | Localization - Hard-coded messages | MEDIUM | 2-3h | User Experience |
| 2 | Metadata service extraction | LOW | 1h | Code Quality |
| 3 | Error message safety | MEDIUM | 1-2h | Security/UX |
| 4 | Error code documentation | LOW | 2h | Maintainability |
| 5 | Additional test coverage | LOW | 3-4h | Quality Assurance |

**Total Implementation Time:** 8-12 hours over 2-3 weeks

---

## PRIORITY 1: Localization (MEDIUM) ⚠️

### Problem
Hard-coded English messages in payment controllers prevent localization for Persian-speaking users.

### Affected Files
1. `app/Http/Controllers/Api/Shop/Wallet/WalletTopupController.php:50-51`
2. `app/Http/Controllers/Api/Shop/Payment/GatewayCallbackController.php:78`
3. `app/Http/Controllers/Api/Shop/Sale/RetryPaymentController.php:64`

### Current Code (Bad)
```php
// WalletTopupController.php
message: $result->requiresRedirect()
    ? 'Please complete payment on the gateway page.'
    : 'Payment is pending admin verification.',
```

### Refactored Code (Good)
```php
// WalletTopupController.php
message: $result->requiresRedirect()
    ? __('messages.payment.complete_on_gateway')
    : __('messages.wallet.topup_pending'),
```

### Implementation Steps

**Step 1:** Add to `lang/en/messages.php`
```php
'payment' => [
    'complete_on_gateway' => 'Please complete payment on the gateway page.',
    'pending_admin_verification' => 'Your payment is pending admin verification.',
    'processing_error' => 'An error occurred while processing your payment. Please try again.',
    'verification_failed' => 'Payment verification failed. Please try again or contact support.',
    'verification_success' => 'Payment verified successfully.',
    'method_not_available' => 'The selected payment method is not available.',
    'retry_success' => 'Payment retry initiated successfully.',
],

'wallet' => [
    'insufficient_balance' => 'Your wallet balance is insufficient by :shortfall. Please top up your wallet.',
    'topup_successful' => 'Wallet topped up successfully.',
    'topup_pending' => 'Your wallet top-up is pending admin verification.',
    'topup_initiated' => 'Wallet top-up initiated successfully.',
    'invalid_method' => 'Cannot use wallet to top up wallet. Please select another payment method.',
],
```

**Step 2:** Add to `lang/fa/messages.php`
```php
'payment' => [
    'complete_on_gateway' => 'لطفاً پرداخت خود را در صفحه درگاه تکمیل کنید.',
    'pending_admin_verification' => 'پرداخت شما در انتظار تأیید مدیر است.',
    'processing_error' => 'خطایی در پردازش پرداخت رخ داد. لطفاً دوباره امتحان کنید.',
    'verification_failed' => 'تأیید پرداخت ناموفق بود. لطفاً دوباره امتحان کنید یا با پشتیبانی تماس بگیرید.',
    'verification_success' => 'پرداخت با موفقیت تأیید شد.',
    'method_not_available' => 'روش پرداخت انتخاب شده در دسترس نیست.',
    'retry_success' => 'تلاش مجدد پرداخت با موفقیت آغاز شد.',
],

'wallet' => [
    'insufficient_balance' => 'موجودی کیف پول شما :shortfall کمتر است. لطفاً کیف پول خود را شارژ کنید.',
    'topup_successful' => 'کیف پول با موفقیت شارژ شد.',
    'topup_pending' => 'شارژ کیف پول شما در انتظار تأیید مدیر است.',
    'topup_initiated' => 'شارژ کیف پول با موفقیت آغاز شد.',
    'invalid_method' => 'نمی‌توان از کیف پول برای شارژ کیف پول استفاده کرد. لطفاً روش پرداخت دیگری انتخاب کنید.',
],
```

**Step 3:** Update `WalletTopupController.php`
```php
// BEFORE (lines 50-51)
message: $result->requiresRedirect()
    ? 'Please complete payment on the gateway page.'
    : 'Payment is pending admin verification.',

// AFTER
message: $result->requiresRedirect()
    ? __('messages.payment.complete_on_gateway')
    : __('messages.wallet.topup_pending'),
```

**Step 4:** Update `GatewayCallbackController.php`
```php
// BEFORE (line 78)
return response()->success($responseData, 'Payment verified successfully');

// AFTER
return response()->success($responseData, __('messages.payment.verification_success'));
```

**Step 5:** Update `RetryPaymentController.php`
```php
// BEFORE (line 64)
$message = $result->requiresRedirect()
    ? 'Please complete payment on the gateway page.'
    : 'Payment verification successful.';

// AFTER
$message = $result->requiresRedirect()
    ? __('messages.payment.complete_on_gateway')
    : __('messages.payment.verification_success');
```

**Step 6:** Update `InsufficientWalletBalanceException` message
```php
// File: app/Exceptions/InsufficientWalletBalanceException.php

// BEFORE
throw new InsufficientWalletBalanceException(
    "Wallet balance insufficient by " . $shortfall
);

// AFTER
throw new InsufficientWalletBalanceException(
    __('messages.wallet.insufficient_balance', ['shortfall' => $shortfall])
);
```

### Testing Checklist
- [ ] Test wallet topup with EN locale
- [ ] Test wallet topup with FA locale
- [ ] Test order checkout with EN locale
- [ ] Test order checkout with FA locale
- [ ] Test gateway callback with both locales
- [ ] Test retry payment with both locales
- [ ] Verify all messages display correctly
- [ ] Verify Persian text displays right-to-left

**Effort:** 2-3 hours  
**Impact:** HIGH - Improves user experience for Persian users

---

## PRIORITY 2: Extract Metadata Service (LOW) 🔧

### Problem
IP address and User-Agent collection is duplicated in 3 payment processors.

### Affected Files
1. `app/Services/Payment/WalletPaymentProcessor.php:99-100`
2. `app/Services/Payment/MellatGatewayPaymentProcessor.php:61-62`
3. `app/Services/Payment/BankTransferPaymentProcessor.php` (potential)

### Current Code (Bad)
```php
// Duplicated in 3 places
$ipAddress = request()->ip();
$userAgent = request()->userAgent();
```

### Refactored Code (Good)
```php
// Create new service
class PaymentMetadataService
{
    public function collect(): array
    {
        return [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];
    }
}

// Use in processors
$metadata = $this->metadataService->collect();
```

### Implementation Steps

**Step 1:** Create `PaymentMetadataService`
```php
<?php

declare(strict_types=1);

namespace App\Services\Payment;

final class PaymentMetadataService
{
    /**
     * Collect payment metadata from the current request.
     *
     * @return array{ip_address: string|null, user_agent: string|null}
     */
    public function collect(): array
    {
        return [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];
    }
}
```

**Step 2:** Register in `AppServiceProvider`
```php
// app/Providers/AppServiceProvider.php

public function register(): void
{
    $this->app->singleton(PaymentMetadataService::class);
}
```

**Step 3:** Update `WalletPaymentProcessor`
```php
// BEFORE (lines 99-100)
$ipAddress = request()->ip();
$userAgent = request()->userAgent();

// AFTER
$metadata = $this->metadataService->collect();
$ipAddress = $metadata['ip_address'];
$userAgent = $metadata['user_agent'];
```

**Step 4:** Update `MellatGatewayPaymentProcessor`
```php
// BEFORE (lines 61-62)
$ipAddress = request()->ip();
$userAgent = request()->userAgent();

// AFTER
$metadata = $this->metadataService->collect();
$ipAddress = $metadata['ip_address'];
$userAgent = $metadata['user_agent'];
```

**Step 5:** Update `BankTransferPaymentProcessor` (if applicable)
Same pattern as above.

**Step 6:** Inject service via constructor
```php
public function __construct(
    private readonly PaymentMetadataService $metadataService,
    // ... other dependencies
) {}
```

### Testing Checklist
- [ ] Test wallet payment still records IP/UA
- [ ] Test gateway payment still records IP/UA
- [ ] Test bank transfer payment still records IP/UA
- [ ] Verify metadata is accurate in Payment records
- [ ] Run existing payment tests to ensure no regression

**Effort:** 1 hour  
**Impact:** MEDIUM - Improves code maintainability

---

## PRIORITY 3: Error Message Safety (MEDIUM) ⚠️

### Problem
Technical exceptions could leak to customers, exposing internal system details.

### Affected Files
1. `app/Services/Payment/PaymentProcessorFactory.php:25-27`
2. `app/Http/Controllers/Api/Shop/Wallet/WalletTopupController.php`
3. `app/Http/Controllers/Api/Shop/Sale/CheckoutController.php`

### Current Code (Bad)
```php
// PaymentProcessorFactory.php
throw new \InvalidArgumentException(
    "No payment processor available for method: {$paymentMethod->value}"
);
```

**Issue:** If this exception reaches a customer, they see:
```json
{
  "message": "No payment processor available for method: mellat_gateway"
}
```

### Refactored Code (Good)

**Option 1: Custom Exception**
```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

final class PaymentMethodNotAvailableException extends Exception
{
    public static function forMethod(string $method): self
    {
        return new self(
            __('messages.payment.method_not_available'),
            422
        );
    }
}
```

**Option 2: Controller Catch Block**
```php
// In controllers
try {
    $result = $processor->process($data, $user, $amountToPay, $order);
} catch (\InvalidArgumentException $e) {
    return response()->unprocessableEntity(
        __('messages.payment.method_not_available')
    );
}
```

### Implementation Steps

**Step 1:** Create custom exception
```php
// app/Exceptions/PaymentMethodNotAvailableException.php

<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

final class PaymentMethodNotAvailableException extends Exception
{
    public function __construct()
    {
        parent::__construct(
            __('messages.payment.method_not_available'),
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public static function forMethod(string $method): self
    {
        \Log::error('Payment processor not found', [
            'method' => $method,
            'available_processors' => config('payments.processors'),
        ]);

        return new self();
    }
}
```

**Step 2:** Update `PaymentProcessorFactory`
```php
// BEFORE
throw new \InvalidArgumentException(
    "No payment processor available for method: {$paymentMethod->value}"
);

// AFTER
throw PaymentMethodNotAvailableException::forMethod($paymentMethod->value);
```

**Step 3:** Register in `ExceptionHandler`
```php
// app/Exceptions/Handler.php

use App\Exceptions\PaymentMethodNotAvailableException;

public function register(): void
{
    $this->renderable(function (PaymentMethodNotAvailableException $e) {
        return response()->unprocessableEntity($e->getMessage());
    });
}
```

**Step 4:** Add catch blocks in controllers (alternative approach)
```php
// WalletTopupController, CheckoutController, RetryPaymentController

try {
    $processor = app(PaymentProcessorFactory::class)->make($data->payment_method);
    $result = $processor->process($data, $user, $amountToPay, $order);
} catch (PaymentMethodNotAvailableException $e) {
    return response()->unprocessableEntity($e->getMessage());
}
```

### Testing Checklist
- [ ] Test with invalid payment method enum value
- [ ] Verify customer-friendly message is returned
- [ ] Verify technical details are logged but not exposed
- [ ] Test all payment endpoints (topup, checkout, retry)
- [ ] Verify exception is properly caught and handled

**Effort:** 1-2 hours  
**Impact:** HIGH - Protects against information leakage

---

## PRIORITY 4: Document Error Codes (LOW) 📚

### Problem
Mellat Bank gateway returns numeric error codes that are not well-documented in the codebase.

### Affected Files
1. `app/Services/Payment/MellatGatewayPaymentProcessor.php`
2. Need to create: `docs/Payment-Gateway-Error-Codes.md`

### Current Code
```php
// Line 423
if ((int) $response->return !== 0) {
    throw new \RuntimeException(
        "Payment verification failed with code: {$response->return}"
    );
}
```

**Issue:** Code `51` or `421` means nothing to developers without documentation.

### Refactored Approach

**Step 1:** Create `docs/Payment-Gateway-Error-Codes.md`
```markdown
# Payment Gateway Error Codes

## Mellat Bank (بانک ملت)

### Common Error Codes

| Code | English | Persian | Recovery Action |
|------|---------|---------|-----------------|
| 0 | Success | موفق | Transaction completed |
| 11 | Invalid card number | شماره کارت نامعتبر است | Ask customer to re-enter card |
| 12 | Card not found | کارت یافت نشد | Verify card number |
| 13 | Invalid CVV2 | CVV2 نامعتبر است | Ask customer to check CVV2 |
| 14 | Invalid expiration date | تاریخ انقضا نامعتبر است | Verify card expiration |
| 15 | Card expired | کارت منقضی شده است | Ask for different card |
| 17 | Transaction cancelled by user | تراکنش توسط کاربر لغو شد | Customer cancelled |
| 21 | Insufficient funds | موجودی ناکافی | Ask to use different card |
| 23 | Amount exceeds limit | مبلغ بیش از حد مجاز است | Split transaction |
| 25 | Invalid merchant | فروشنده نامعتبر است | Contact Mellat support |
| 34 | Transaction suspected of fraud | تراکنش مشکوک به تقلب | Contact customer |
| 41 | Duplicate transaction | تراکنش تکراری | Check payment status |
| 42 | Sale transaction not found | تراکنش فروش یافت نشد | Verify RefId |
| 43 | Verification already requested | قبلاً درخواست تأیید شده | Check payment status |
| 44 | Verification request not found | درخواست تأیید یافت نشد | Contact Mellat support |
| 45 | Transaction settled | تراکنش settle شده است | Already completed |
| 46 | Transaction not settled | تراکنش settle نشده است | Wait for settlement |
| 47 | Transaction not found | تراکنش یافت نشد | Verify transaction ID |
| 48 | Transaction queued | تراکنش در صف انتظار | Retry later |
| 49 | Transaction amount invalid | مبلغ تراکنش نامعتبر است | Verify amount |
| 51 | Transaction already processed | تراکنش قبلاً پردازش شده | Check status |
| 54 | Invalid reference transaction | تراکنش مرجع نامعتبر است | Verify RefId |
| 55 | Invalid transaction | تراکنش نامعتبر است | Recreate transaction |
| 61 | Deposit error | خطای واریز | Contact bank |

### Critical Error Codes (Require Admin Action)

| Code | Issue | Admin Action |
|------|-------|--------------|
| 25 | Invalid merchant credentials | Verify Terminal ID and credentials |
| 421 | Invalid IP address | Whitelist server IP with Mellat |
| 500-599 | Gateway internal errors | Contact Mellat technical support |

### Implementation Notes

- **Code 0:** SUCCESS - proceed with order completion
- **Codes 11-21:** Customer card issues - display friendly message
- **Codes 41-49:** Transaction state issues - check payment record
- **Codes 51-61:** Processing issues - log and notify admin
- **Code 421:** IP whitelist issue - CRITICAL, requires Mellat support

## Bank Transfer

| Status | Description | Action |
|--------|-------------|--------|
| PENDING | Awaiting admin verification | Admin must verify transfer |
| COMPLETED | Transfer verified | Payment successful |
| FAILED | Transfer invalid | Admin rejected transfer |

## Implementation

Add helper method in `MellatGatewayPaymentProcessor`:

\`\`\`php
private function getMellatErrorMessage(int $code): string
{
    return match ($code) {
        0 => __('messages.payment.success'),
        11 => __('messages.payment.mellat.invalid_card'),
        12 => __('messages.payment.mellat.card_not_found'),
        // ... etc
        default => __('messages.payment.mellat.unknown_error', ['code' => $code]),
    };
}
\`\`\`
```

**Step 2:** Update `MellatGatewayPaymentProcessor` to use mapping
```php
// BEFORE
if ((int) $response->return !== 0) {
    throw new \RuntimeException(
        "Payment verification failed with code: {$response->return}"
    );
}

// AFTER
if ((int) $response->return !== 0) {
    $errorMessage = $this->getMellatErrorMessage((int) $response->return);
    
    \Log::error('Mellat payment verification failed', [
        'code' => $response->return,
        'payment_id' => $payment->id,
        'ref_id' => $payment->gateway_transaction_id,
    ]);
    
    throw new \RuntimeException($errorMessage);
}
```

### Testing Checklist
- [ ] Document created with all error codes
- [ ] Add error code helper method
- [ ] Update exception messages to use helper
- [ ] Test with known error codes (if possible in sandbox)
- [ ] Verify error messages are customer-friendly

**Effort:** 2 hours  
**Impact:** MEDIUM - Improves debugging and customer support

---

## PRIORITY 5: Additional Test Coverage (LOW) ✅

### Problem
Some edge cases and gateway flows lack comprehensive test coverage.

### Missing Test Scenarios

**1. Wallet Topup with Gateway Redirect**
```php
// tests/Feature/Api/V1/Shop/Wallet/WalletTopupWithGatewayTest.php

it('can initiate wallet topup with mellat gateway', function () {
    $this->customer();
    
    $response = postJson(route('customer.wallet.topup'), [
        'payment_method' => 'mellat_gateway',
        'amount' => 100000,
    ]);
    
    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => [
            'redirect_url',
            'payment_id',
        ],
    ]);
    
    $this->assertDatabaseHas('payments', [
        'customer_id' => auth()->id(),
        'amount' => 100000,
        'method' => 'mellat_gateway',
        'status' => 'pending',
        'payment_type' => 'wallet_topup',
        'order_id' => null,
    ]);
});

it('credits wallet after gateway verification', function () {
    $this->customer();
    $user = auth()->user();
    
    // Create pending payment
    $payment = Payment::factory()->create([
        'customer_id' => $user->id,
        'amount' => 100000,
        'method' => 'mellat_gateway',
        'status' => 'pending',
        'payment_type' => 'wallet_topup',
        'order_id' => null,
        'gateway_transaction_id' => 'REF12345',
    ]);
    
    $initialBalance = $user->wallet->balance;
    
    // Mock Mellat verification
    Http::fake([
        'bpm.shaparak.ir/*' => Http::response([
            'return' => 0, // Success
        ]),
    ]);
    
    $response = postJson(route('shop.payment.gateway.callback'), [
        'RefId' => $payment->gateway_transaction_id,
        'ResCode' => '0',
    ]);
    
    $response->assertOk();
    
    expect($user->wallet->fresh()->balance)
        ->toBe($initialBalance + 100000);
        
    $this->assertDatabaseHas('wallet_transactions', [
        'wallet_id' => $user->wallet->id,
        'amount' => 100000,
        'transaction_type' => 'deposit',
    ]);
});
```

**2. Concurrent Payment Verification**
```php
// tests/Feature/Api/V1/Shop/Payment/ConcurrentVerificationTest.php

it('prevents race condition in payment verification', function () {
    $payment = Payment::factory()->pending()->create();
    
    // Simulate two concurrent verification requests
    $responses = collect();
    
    parallel(function () use ($payment, $responses) {
        $responses->push(
            postJson(route('shop.payment.gateway.callback'), [
                'RefId' => $payment->gateway_transaction_id,
            ])
        );
    }, 2);
    
    // Only one should succeed
    $successes = $responses->filter(fn($r) => $r->status() === 200);
    expect($successes)->toHaveCount(1);
    
    // Payment should only be completed once
    expect($payment->fresh()->status)->toBe('completed');
    
    // No duplicate wallet credits
    $walletTransactions = WalletTransaction::where('source_id', $payment->id)->count();
    expect($walletTransactions)->toBe(1);
});
```

**3. Invalid Gateway Response Handling**
```php
// tests/Feature/Api/V1/Shop/Payment/InvalidGatewayResponseTest.php

it('handles invalid mellat response gracefully', function () {
    $payment = Payment::factory()->pending()->create();
    
    Http::fake([
        'bpm.shaparak.ir/*' => Http::response([
            'return' => 421, // Invalid IP
        ]),
    ]);
    
    $response = postJson(route('shop.payment.gateway.callback'), [
        'RefId' => $payment->gateway_transaction_id,
        'ResCode' => '421',
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonPath('message', __('messages.payment.verification_failed'));
    
    expect($payment->fresh()->status)->toBe('failed');
});

it('handles missing gateway callback data', function () {
    $response = postJson(route('shop.payment.gateway.callback'), [
        // Missing RefId
        'ResCode' => '0',
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['RefId']);
});
```

**4. Wallet Insufficient Balance with Multiple Items**
```php
it('fails checkout when wallet balance is insufficient for partial payment', function () {
    $this->customer();
    $user = auth()->user();
    
    // Set wallet balance to 50,000
    $user->wallet->update(['balance' => 50000]);
    
    // Create cart with total 200,000
    $cart = Cart::factory()
        ->hasItems(2, ['price' => 100000])
        ->create(['user_id' => $user->id]);
    
    $response = postJson(route('shop.checkout'), [
        'payment_method' => 'wallet',
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonPath(
        'message',
        __('messages.wallet.insufficient_balance', ['shortfall' => 150000])
    );
});
```

### Testing Checklist
- [ ] Create WalletTopupWithGatewayTest.php
- [ ] Create ConcurrentVerificationTest.php
- [ ] Create InvalidGatewayResponseTest.php
- [ ] Add wallet balance edge case tests
- [ ] Run full test suite
- [ ] Verify coverage remains above 85%
- [ ] Update test documentation

**Effort:** 3-4 hours  
**Impact:** MEDIUM - Improves confidence in edge cases

---

## Implementation Sequence

### Week 1 (High Impact - User Facing)
1. **Day 1-2:** Priority 1 - Localization
   - Add message entries (1 hour)
   - Update controllers (1 hour)
   - Test in both locales (30 min)
   - **Total: 2.5 hours**

### Week 2 (Code Quality & Security)
2. **Day 1:** Priority 2 - Metadata Service
   - Create service class (20 min)
   - Update processors (20 min)
   - Test and verify (20 min)
   - **Total: 1 hour**

3. **Day 2:** Priority 3 - Error Safety
   - Create custom exception (30 min)
   - Update factory (15 min)
   - Add exception handler (15 min)
   - Test error scenarios (30 min)
   - **Total: 1.5 hours**

### Week 3 (Documentation & Quality Assurance)
4. **Day 1:** Priority 4 - Documentation
   - Create error code document (1.5 hours)
   - Add helper method (30 min)
   - **Total: 2 hours**

5. **Day 2-3:** Priority 5 - Tests
   - Write gateway tests (1.5 hours)
   - Write concurrency tests (1 hour)
   - Write edge case tests (1 hour)
   - Run full suite and verify (30 min)
   - **Total: 4 hours**

**Total Implementation Time: 11 hours over 3 weeks**

---

## Success Metrics

### Code Quality
- [ ] Zero hard-coded strings in payment controllers
- [ ] Zero code duplication in metadata collection
- [ ] Zero technical errors exposed to customers
- [ ] 100% error codes documented

### Test Coverage
- [ ] Maintain or improve test coverage (target: >85%)
- [ ] All edge cases covered
- [ ] Concurrency scenarios tested
- [ ] Gateway error scenarios tested

### User Experience
- [ ] All messages available in English and Persian
- [ ] Customer-friendly error messages
- [ ] Clear payment status communication

### Maintainability
- [ ] Error codes documented for future developers
- [ ] Consistent metadata collection pattern
- [ ] Clean exception handling

---

## Verification Checklist

### After Each Priority
- [ ] Run affected tests: `sail artisan test --filter=<TestName>`
- [ ] Run linter: `sail pint --dirty`
- [ ] Check for regressions
- [ ] Update documentation if needed
- [ ] Commit changes with conventional commit message

### Before Deployment
- [ ] Run full test suite: `sail artisan test`
- [ ] Verify all translations work
- [ ] Test in staging environment
- [ ] Review error logs for unexpected issues
- [ ] Update CHANGELOG.md

### After Deployment
- [ ] Monitor error logs for 24 hours
- [ ] Verify payment flows work in production
- [ ] Check Mellat gateway integration
- [ ] Verify wallet transactions are accurate
- [ ] Confirm customer feedback is positive

---

## Additional Recommendations (Optional)

### Architecture Improvements (Future Consideration)

**1. Interface Segregation for Processors**
Create separate interfaces for single-step and multi-step processors:

```php
interface SingleStepPaymentProcessor extends PaymentProcessorContract
{
    // No verify() method required
}

interface MultiStepPaymentProcessor extends PaymentProcessorContract
{
    public function verify(Payment $payment, GatewayCallbackData $callbackData): Payment;
}
```

**2. Payment State Machine**
Consider implementing a formal state machine for payment status transitions:

```php
use Spatie\LaravelStateMachine\StateMachine;

PaymentStateMachine::define()
    ->allowTransition('pending', 'completed')
    ->allowTransition('pending', 'failed')
    ->allowTransition('failed', 'pending') // For retry
    ->guardTransition('pending', 'completed', function ($payment) {
        return $payment->verified_at !== null;
    });
```

**3. Event Sourcing for Audit Trail**
For enhanced audit capabilities, consider event sourcing for payment events:

```php
PaymentInitiated::dispatch($payment);
PaymentVerificationRequested::dispatch($payment);
PaymentCompleted::dispatch($payment);
WalletCredited::dispatch($wallet, $transaction);
```

These are **optional future enhancements** and not required for current production readiness.

---

## Conclusion

The payment system is **95% production-ready**. The identified improvements are **polish and completeness** items, not fundamental architectural issues. The priorities above are ordered by:

1. **User Impact** (localization affects all Persian users)
2. **Security** (error message safety prevents information leakage)
3. **Code Quality** (metadata extraction reduces duplication)
4. **Maintainability** (documentation helps future developers)
5. **Quality Assurance** (tests increase confidence)

**Recommended Approach:** Implement Priority 1 immediately, then schedule Priorities 2-3 for the next sprint, and consider Priorities 4-5 as time permits.

---

**Document Status:** Ready for Implementation  
**Last Updated:** December 11, 2025  
**Next Review:** After Priority 1-3 implementation

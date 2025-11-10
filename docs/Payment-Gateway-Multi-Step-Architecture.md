# Payment Gateway Multi-Step Architecture Design

## Overview

This document outlines the architectural changes required to support multi-step payment gateways (like Mellat, Zarinpal, PayPal, Stripe) while maintaining backward compatibility with single-step payments (Wallet, Bank Transfer).

## Current vs. Proposed Architecture

### Current Flow (Single-Step Payments)
```
Admin creates payment → Processor executes → Payment record created (COMPLETED) → Event dispatched → Order updated
```

### Proposed Flow (Multi-Step Gateway Payments)
```
Admin initiates payment → Processor creates PENDING payment → Returns redirect URL
    ↓
Customer redirects to gateway → Completes payment on gateway
    ↓
Gateway callback → Verify transaction → Update payment to COMPLETED → Event dispatched → Order updated
```

## 1. Contract Changes

### Current Contract (app/Contracts/Payment/PaymentProcessorContract.php)
```php
interface PaymentProcessorContract
{
    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): Payment;

    public function canHandle(PaymentMethodEnum $paymentMethod): bool;
}
```

### Proposed New Contract
```php
<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;

interface PaymentProcessorContract
{
    /**
     * Process the payment for the given order.
     * 
     * @return PaymentProcessResultData Contains payment record and optional redirect URL
     */
    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): PaymentProcessResultData;

    /**
     * Verify a payment after callback from gateway.
     * Only required for multi-step processors.
     * 
     * @param Payment $payment The pending payment to verify
     * @param array $callbackData Data from gateway callback
     * @return Payment Updated payment with final status
     */
    public function verify(Payment $payment, array $callbackData): Payment;

    /**
     * Check if this processor requires customer redirect (multi-step).
     */
    public function requiresRedirect(): bool;

    /**
     * Check if this processor can handle the given payment method.
     */
    public function canHandle(PaymentMethodEnum $paymentMethod): bool;
}
```

## 2. New Data Transfer Objects

### PaymentProcessResultData
```php
<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use App\Models\Payment;
use Spatie\LaravelData\Data;

final class PaymentProcessResultData extends Data
{
    public function __construct(
        public readonly Payment $payment,
        public readonly ?string $redirect_url = null,
        public readonly ?array $redirect_data = null,
        public readonly ?string $redirect_method = 'GET', // GET or POST
    ) {}

    /**
     * Create result for completed payment (single-step).
     */
    public static function completed(Payment $payment): self
    {
        return new self(
            payment: $payment,
            redirect_url: null
        );
    }

    /**
     * Create result for pending payment requiring redirect (multi-step).
     */
    public static function pendingWithRedirect(
        Payment $payment,
        string $redirectUrl,
        ?array $redirectData = null,
        string $method = 'GET'
    ): self {
        return new self(
            payment: $payment,
            redirect_url: $redirectUrl,
            redirect_data: $redirectData,
            redirect_method: $method
        );
    }

    /**
     * Check if this result requires customer redirect.
     */
    public function requiresRedirect(): bool
    {
        return !is_null($this->redirect_url);
    }
}
```

### GatewayCallbackData
```php
<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

final class GatewayCallbackData extends Data
{
    public function __construct(
        #[Required]
        public readonly string $payment_uuid,
        
        #[Required]
        public readonly array $gateway_response,
    ) {}
}
```

## 3. Updated Processors

### Single-Step Processor Example (WalletPaymentProcessor)
```php
<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;

final class WalletPaymentProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::WALLET;
    }

    public function requiresRedirect(): bool
    {
        return false; // Single-step payment
    }

    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): PaymentProcessResultData {
        // ... existing wallet payment logic ...

        $payment = $order->payments()->create([
            'customer_id' => $order->customer_id,
            'amount'      => $amountToPay,
            'method'      => PaymentMethodEnum::WALLET,
            'status'      => PaymentStatusEnum::COMPLETED, // Immediate completion
            // ... other fields
        ]);

        // Dispatch completion event immediately for single-step payments
        PaymentCompletedEvent::dispatch($payment);

        return PaymentProcessResultData::completed($payment);
    }

    public function verify(Payment $payment, array $callbackData): Payment
    {
        // Not needed for single-step payments
        throw new \BadMethodCallException('Wallet payments do not require verification');
    }
}
```

### Multi-Step Processor Example (MellatGatewayPaymentProcessor)

```php
<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\Gateway\MellatException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

final class MellatGatewayPaymentProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::MELLAT_GATEWAY;
    }

    public function requiresRedirect(): bool
    {
        return true; // Multi-step payment requiring redirect
    }

    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): PaymentProcessResultData {
        // Step 1: Send payment request to Mellat Gateway
        $refId = $this->sendPayRequest($amountToPay, $order, $paymentData);

        // Step 2: Create PENDING payment record
        $payment = $order->payments()->create([
            'customer_id' => $order->customer_id,
            'created_by'  => $adminUser instanceof Staff ? $adminUser->id : null,
            'amount'      => $amountToPay,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
            'status'      => PaymentStatusEnum::PENDING, // CRITICAL: Must be PENDING
            'admin_notes' => $paymentData->admin_notes,
            'data'        => [
                'gateway'           => 'mellat',
                'transaction_id'    => $refId,
                'initiated_at'      => now()->toISOString(),
            ],
        ]);

        Log::info("Mellat payment initiated", [
            'payment_id' => $payment->id,
            'order_id'   => $order->increment_id,
            'ref_id'     => $refId,
        ]);

        // Step 3: Return redirect URL to gateway
        $gatewayUrl = config('payments.mellat.gateway_url');
        
        return PaymentProcessResultData::pendingWithRedirect(
            payment: $payment,
            redirectUrl: $gatewayUrl,
            redirectData: [
                'RefId' => $refId,
                // Add any other POST data required by Mellat
            ],
            method: 'POST'
        );
    }

    public function verify(Payment $payment, array $callbackData): Payment
    {
        // Step 4: Verify the callback from Mellat
        Log::info("Verifying Mellat payment", [
            'payment_id'    => $payment->id,
            'callback_data' => $callbackData,
        ]);

        try {
            // Extract data from callback
            $refId       = $callbackData['RefId'] ?? null;
            $resCode     = $callbackData['ResCode'] ?? null;
            $saleOrderId = $callbackData['SaleOrderId'] ?? null;

            // Validate callback data
            if (!$refId || !$resCode) {
                throw new MellatException('Invalid callback data from Mellat');
            }

            // Check if transaction was successful
            if ($resCode !== '0') {
                // Payment failed
                $payment->update([
                    'status' => PaymentStatusEnum::FAILED,
                    'data'   => array_merge($payment->data ?? [], [
                        'callback_received_at' => now()->toISOString(),
                        'callback_data'        => $callbackData,
                        'failure_reason'       => $this->getMellatErrorMessage($resCode),
                    ]),
                ]);

                Log::warning("Mellat payment failed", [
                    'payment_id' => $payment->id,
                    'res_code'   => $resCode,
                ]);

                return $payment;
            }

            // Call bpVerifyRequest to verify with Mellat servers
            $verifyResult = $this->verifyWithMellat($refId, $saleOrderId);

            if ($verifyResult !== true) {
                // Verification failed
                $payment->update([
                    'status' => PaymentStatusEnum::FAILED,
                    'data'   => array_merge($payment->data ?? [], [
                        'callback_received_at' => now()->toISOString(),
                        'verification_failed'  => true,
                        'callback_data'        => $callbackData,
                    ]),
                ]);

                Log::error("Mellat verification failed", [
                    'payment_id' => $payment->id,
                    'ref_id'     => $refId,
                ]);

                return $payment;
            }

            // Call bpSettleRequest to settle the transaction
            $settleResult = $this->settleWithMellat($refId, $saleOrderId);

            if ($settleResult !== true) {
                // Settlement failed - this is critical, payment was verified but not settled
                $payment->update([
                    'status' => PaymentStatusEnum::FAILED,
                    'data'   => array_merge($payment->data ?? [], [
                        'callback_received_at' => now()->toISOString(),
                        'verification_success' => true,
                        'settlement_failed'    => true,
                        'callback_data'        => $callbackData,
                    ]),
                ]);

                Log::critical("Mellat settlement failed after successful verification", [
                    'payment_id' => $payment->id,
                    'ref_id'     => $refId,
                ]);

                return $payment;
            }

            // SUCCESS! Payment verified and settled
            $payment->update([
                'status' => PaymentStatusEnum::COMPLETED,
                'data'   => array_merge($payment->data ?? [], [
                    'callback_received_at' => now()->toISOString(),
                    'verified_at'          => now()->toISOString(),
                    'settled_at'           => now()->toISOString(),
                    'callback_data'        => $callbackData,
                ]),
            ]);

            // Dispatch completion event ONLY after successful verification
            PaymentCompletedEvent::dispatch($payment);

            Log::info("Mellat payment completed successfully", [
                'payment_id' => $payment->id,
                'ref_id'     => $refId,
            ]);

            return $payment;

        } catch (\Exception $e) {
            Log::error("Error verifying Mellat payment", [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);

            $payment->update([
                'status' => PaymentStatusEnum::FAILED,
                'data'   => array_merge($payment->data ?? [], [
                    'verification_error' => $e->getMessage(),
                    'callback_data'      => $callbackData,
                ]),
            ]);

            throw $e;
        }
    }

    private function sendPayRequest(int $amountToPay, Order $order, PaymentCreateData $paymentData): string
    {
        // Existing implementation...
        // Returns RefId from Mellat
    }

    private function verifyWithMellat(string $refId, string $saleOrderId): bool
    {
        $config = $this->getConfig();
        
        try {
            $soap = new \SoapClient(config('payments.mellat.server_url'));
            $response = $soap->bpVerifyRequest([
                'terminalId'     => $config['terminalId'],
                'userName'       => $config['username'],
                'userPassword'   => $config['password'],
                'orderId'        => $saleOrderId,
                'orderIdLong'    => $saleOrderId,
                'saleReferenceId'=> $refId,
            ]);

            return $response->return === '0';
        } catch (\SoapFault $e) {
            Log::error("Mellat verification SOAP error", ['error' => $e->getMessage()]);
            throw new MellatException('Gateway verification failed: ' . $e->getMessage());
        }
    }

    private function settleWithMellat(string $refId, string $saleOrderId): bool
    {
        $config = $this->getConfig();
        
        try {
            $soap = new \SoapClient(config('payments.mellat.server_url'));
            $response = $soap->bpSettleRequest([
                'terminalId'     => $config['terminalId'],
                'userName'       => $config['username'],
                'userPassword'   => $config['password'],
                'orderId'        => $saleOrderId,
                'orderIdLong'    => $saleOrderId,
                'saleReferenceId'=> $refId,
            ]);

            return $response->return === '0' || $response->return === '45'; // 45 = already settled
        } catch (\SoapFault $e) {
            Log::error("Mellat settlement SOAP error", ['error' => $e->getMessage()]);
            throw new MellatException('Gateway settlement failed: ' . $e->getMessage());
        }
    }

    private function getMellatErrorMessage(string $code): string
    {
        // Map Mellat error codes to messages
        $errors = [
            '11' => 'Invalid card number',
            '12' => 'No sufficient funds',
            '13' => 'Incorrect PIN',
            '14' => 'Allowable number of PIN tries exceeded',
            '15' => 'Invalid card',
            '16' => 'Allowable number of transactions exceeded',
            '17' => 'User canceled transaction',
            '18' => 'Expired card',
            '19' => 'Allowable number of amount exceeded',
            '111'=> 'Invalid issuer',
            '112'=> 'Card holder has restrictions',
            '113'=> 'Issuer has problems',
            '114'=> 'Invalid merchant',
            '21' => 'Invalid transaction',
            '23' => 'Invalid currency',
            '24' => 'Invalid amount',
            '25' => 'Invalid CVV2',
            '31' => 'Invalid response',
            '32' => 'Invalid format',
            '33' => 'Invalid account',
            '34' => 'System error',
            '35' => 'Invalid date',
            '41' => 'Duplicated order ID',
            '42' => 'Transaction not found',
            '43' => 'Verification already done',
            '44' => 'Verification request not found',
            '45' => 'Transaction already settled',
            '46' => 'Settlement request not found',
            '47' => 'Settlement already done',
            '48' => 'Transaction already reversed',
            '49' => 'Refund request not found',
            '412'=> 'Incorrect billing information',
            '413'=> 'Invalid authentication',
            '414'=> 'Invalid terminal ID',
            '415'=> 'Transaction in progress',
            '416'=> 'Amount exceeds merchant balance',
            '417'=> 'Transaction repeat limit exceeded',
            '418'=> 'Unacceptable IP address',
            '419'=> 'Invalid payment information',
            '421'=> 'Invalid merchant signature',
            '51' => 'Duplicated transaction',
            '54' => 'Original transaction not found',
            '55' => 'Invalid transaction',
            '56' => 'Transaction system error',
            '57' => 'Original transaction timeout',
            '58' => 'Transaction failed',
            '61' => 'Verification error',
        ];

        return $errors[$code] ?? "Unknown error (Code: $code)";
    }

    private function getConfig(): array
    {
        return [
            'terminalId'  => config('payments.mellat.terminal_id'),
            'username'    => config('payments.mellat.username'),
            'password'    => config('payments.mellat.password'),
            'callbackUrl' => config('payments.mellat.callback_url'),
        ];
    }
}
```

## 4. Updated CreatePaymentAction

```php
<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreatePaymentAction
{
    public function __construct(
        private PaymentProcessorFactory $processorFactory,
    ) {}

    /**
     * Initiate a payment for an order.
     * 
     * Returns a PaymentProcessResultData which may contain:
     * - For single-step payments: completed payment with no redirect
     * - For multi-step payments: pending payment with redirect URL
     */
    public function handle(Order $order, PaymentCreateData $data, Staff $admin): PaymentProcessResultData
    {
        return DB::transaction(function () use ($order, $data, $admin): PaymentProcessResultData {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            // Check if order is free
            if ($order->grand_total <= 0) {
                if ($order->payments()->where('status', 'completed')->exists()) {
                    // Return already completed free payment
                    return PaymentProcessResultData::completed(
                        $order->payments()->where('status', 'completed')->first()
                    );
                }

                return $this->createFreeOrderPayment($order, $data, $admin);
            }

            // Validate order state
            $this->validateOrderState($order);

            $amount = $this->calculateRequiredPayment($order);

            $processor = $this->processorFactory->make(PaymentMethodEnum::from($data->method));

            return $processor->process($order, $data, $admin, $amount);
        });
    }

    private function createFreeOrderPayment(
        Order $order,
        PaymentCreateData $data,
        Staff $admin
    ): PaymentProcessResultData {
        $payment = Payment::create([
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'amount'      => 0,
            'method'      => PaymentMethodEnum::NO_PAYMENT->value,
            'status'      => PaymentStatusEnum::COMPLETED->value,
            'created_by'  => $admin->id,
            'admin_notes' => $data->admin_notes ?? 'Free order automatically completed.',
        ]);

        PaymentCompletedEvent::dispatch($payment);

        return PaymentProcessResultData::completed($payment);
    }

    private function validateOrderState(Order $order): void
    {
        if ($order->balance_due <= 0) {
            throw ValidationException::withMessages([
                'payment' => __('messages.order.already_fully_paid', ['order_id' => $order->increment_id]),
            ]);
        }

        if ($order->payments()->where('status', PaymentStatusEnum::PENDING)->exists()) {
            throw ValidationException::withMessages([
                'payment' => __('messages.order.payment_already_pending', ['order_id' => $order->increment_id]),
            ]);
        }
    }

    private function calculateRequiredPayment(Order $order): int
    {
        $hasCompletedPayments = $order->payments()->where('status', 'completed')->exists();

        if (! $hasCompletedPayments) {
            return $order->items->sum('total');
        }

        return $order->balance_due;
    }
}
```

## 5. New Callback Handling

### VerifyPaymentAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\Shop\Payment;

use App\Data\Shop\Payment\GatewayCallbackData;
use App\Models\Payment;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class VerifyPaymentAction
{
    public function __construct(
        private PaymentProcessorFactory $processorFactory,
    ) {}

    /**
     * Verify a pending payment after gateway callback.
     */
    public function handle(GatewayCallbackData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            // Find payment by UUID
            $payment = Payment::query()
                ->where('uuid', $data->payment_uuid)
                ->lockForUpdate()
                ->firstOrFail();

            // Ensure payment is in PENDING state
            if ($payment->status !== PaymentStatusEnum::PENDING) {
                throw ValidationException::withMessages([
                    'payment' => "Payment {$payment->uuid} is not in pending state.",
                ]);
            }

            // Get the processor
            $processor = $this->processorFactory->make($payment->method);

            // Verify the payment
            return $processor->verify($payment, $data->gateway_response);
        });
    }
}
```

### GatewayCallbackController (Shop API)
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Payment;

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Shop - Payment Gateway
 * 
 * Handles callbacks from payment gateways after customer completes payment.
 */
final class GatewayCallbackController extends Controller
{
    /**
     * Handle callback from payment gateway.
     * 
     * This endpoint receives the callback from the payment gateway after
     * the customer completes (or cancels) their payment.
     * 
     * @responseFile 200 storage/responses/shop/payment/verify.json
     * @responseFile 422 storage/responses/422.json
     */
    public function __invoke(Request $request, VerifyPaymentAction $action)
    {
        Log::info("Gateway callback received", [
            'data' => $request->all(),
            'ip'   => $request->ip(),
        ]);

        // Build callback data
        $callbackData = new GatewayCallbackData(
            payment_uuid: $request->input('payment_uuid'),
            gateway_response: $request->all()
        );

        try {
            $payment = $action->handle($callbackData);

            // Redirect customer based on payment status
            if ($payment->status === PaymentStatusEnum::COMPLETED) {
                return redirect()->route('shop.payment.success', ['payment' => $payment->uuid])
                    ->with('success', 'Payment completed successfully');
            }

            return redirect()->route('shop.payment.failed', ['payment' => $payment->uuid])
                ->with('error', 'Payment failed. Please try again.');

        } catch (\Exception $e) {
            Log::error("Gateway callback error", [
                'error'   => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return redirect()->route('shop.payment.error')
                ->with('error', 'An error occurred while processing your payment.');
        }
    }
}
```

## 6. Updated PaymentController (Admin)

```php
/**
 * Store a newly created payment for an order.
 *
 * For single-step payments (Wallet, Bank Transfer), the payment is completed immediately.
 * For multi-step payments (Online Gateway), returns redirect information for customer.
 *
 * @responseFile 201 responses/admin/payment/process-result.json
 * @responseFile 403 responses/403.json
 */
public function store(
    PaymentCreateData $data,
    Order $order,
    CreatePaymentAction $action
): ApiResponseInterface {
    Gate::authorize('create', Order::class);
    
    $result = $action->handle($order, $data, auth('staff')->user());

    // Return different response based on whether redirect is required
    return response()->created([
        'payment'      => PaymentData::from($result->payment),
        'requires_redirect' => $result->requiresRedirect(),
        'redirect_url' => $result->redirect_url,
        'redirect_data'=> $result->redirect_data,
        'redirect_method' => $result->redirect_method,
    ]);
}
```

## 7. Route Additions

```php
// routes/Api/V1/shop/shop.php

use App\Http\Controllers\Api\Shop\Payment\GatewayCallbackController;

// Gateway callback endpoint (public - no auth required)
Route::post('/payment/gateway/callback', GatewayCallbackController::class)
    ->name('shop.payment.gateway.callback');
```

## 8. Migration: Add UUID to payments table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
        });

        // Generate UUIDs for existing payments
        DB::table('payments')->whereNull('uuid')->update([
            'uuid' => DB::raw('UUID()')
        ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
```

## 9. Configuration Changes

```php
// config/payments.php

return [
    'mellat' => [
        'terminal_id' => env('MELLAT_TERMINAL_ID'),
        'username'    => env('MELLAT_USERNAME'),
        'password'    => env('MELLAT_PASSWORD'),
        'server_url'  => env('MELLAT_SERVER_URL', 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl'),
        'gateway_url' => env('MELLAT_GATEWAY_URL', 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat'),
        'callback_url'=> env('MELLAT_CALLBACK_URL', route('shop.payment.gateway.callback')),
    ],
];
```

## 10. Testing Strategy

### Test Cases Required:

1. **Single-Step Payment Tests** (Wallet, BankTransfer)
   - Should complete immediately
   - Should NOT return redirect URL
   - Should dispatch `PaymentCompletedEvent` immediately

2. **Multi-Step Payment Tests** (Gateway)
   - Should create PENDING payment
   - Should return redirect URL
   - Should NOT dispatch `PaymentCompletedEvent` yet
   - Should handle callback correctly
   - Should verify transaction
   - Should update status to COMPLETED/FAILED
   - Should dispatch event only after successful verification

3. **Edge Cases**
   - Duplicate callback handling
   - Expired payment verification
   - Failed verification
   - Network errors during verification
   - Concurrent callback requests

## 11. Frontend Integration

### Admin Frontend (After creating payment):

```javascript
// After admin creates payment
const response = await createPayment(orderId, paymentData);

if (response.requires_redirect) {
    // For gateway payments, show redirect UI to customer
    window.open(response.redirect_url, '_blank');
    // Or show a "Payment initiated, customer redirected to gateway" message
} else {
    // For wallet/bank transfer, payment is complete
    showSuccessMessage('Payment completed successfully');
    refreshOrderDetails();
}
```

### Customer Frontend (Gateway redirect):

```html
<!-- Auto-submit form for POST redirect -->
<form id="gateway-form" method="POST" action="{{ $redirectUrl }}">
    @foreach($redirectData as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>

<script>
    document.getElementById('gateway-form').submit();
</script>
```

## 12. Summary of Key Changes

| Component | Change Required | Priority |
|-----------|----------------|----------|
| `PaymentProcessorContract` | Add `verify()` and `requiresRedirect()` methods | HIGH |
| `PaymentProcessResultData` | New DTO for process results | HIGH |
| `GatewayCallbackData` | New DTO for callbacks | HIGH |
| All Processors | Update `process()` return type | HIGH |
| `WalletPaymentProcessor` | Implement new methods | HIGH |
| `BankTransferPaymentProcessor` | Implement new methods | HIGH |
| `MellatGatewayPaymentProcessor` | Complete implementation with `verify()` | HIGH |
| `CreatePaymentAction` | Update return type | HIGH |
| `VerifyPaymentAction` | New action for verification | HIGH |
| `GatewayCallbackController` | New controller for callbacks | HIGH |
| Routes | Add callback route | HIGH |
| Migration | Add UUID to payments | HIGH |
| Tests | Add comprehensive test coverage | HIGH |
| Documentation | Update API docs | MEDIUM |

## 13. Migration Path

1. **Phase 1**: Add new DTOs and update contracts
2. **Phase 2**: Update existing processors (Wallet, BankTransfer)
3. **Phase 3**: Complete Mellat implementation
4. **Phase 4**: Update CreatePaymentAction
5. **Phase 5**: Add callback handling (Action + Controller)
6. **Phase 6**: Add routes and middleware
7. **Phase 7**: Update tests
8. **Phase 8**: Update frontend integration
9. **Phase 9**: Deploy and monitor

## 14. Security Considerations

1. **Callback Verification**: Always verify the callback actually came from the gateway (signature verification, IP whitelisting)
2. **Idempotency**: Handle duplicate callbacks gracefully
3. **State Validation**: Always check payment is in PENDING before verification
4. **Pessimistic Locking**: Use database locks to prevent race conditions
5. **Logging**: Comprehensive logging for audit trail
6. **Timeout Handling**: Set reasonable timeouts for gateway communications
7. **Error Handling**: Never expose sensitive gateway details in public errors

## 15. Monitoring & Observability

1. Log all gateway interactions with unique trace IDs
2. Monitor payment completion rates
3. Alert on high failure rates
4. Track average time from initiation to completion
5. Monitor for stuck PENDING payments
6. Dashboard for payment status overview

---

## Conclusion

This architecture maintains **backward compatibility** with existing single-step processors while properly supporting multi-step gateway payments. The key innovation is the `PaymentProcessResultData` which allows processors to indicate whether customer redirect is required, and the separate `verify()` method for completing the payment after callback.

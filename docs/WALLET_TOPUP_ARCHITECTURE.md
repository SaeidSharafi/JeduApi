# Wallet Top-Up Architecture: Payment System Integration

## The Core Challenge

Our payment system is **currently Order-centric**. Every `Payment` record **MUST** have an `order_id` (non-nullable foreign key). However, wallet top-up is **NOT an order** — it's a standalone financial transaction where a customer deposits money into their wallet balance.

## Current Payment System Architecture

### Payment Model Structure
```php
// app/Models/Payment.php
Schema::create('payments', function (Blueprint $table) {
    $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete(); // ❌ REQUIRED
    $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
    $table->unsignedBigInteger('amount');
    $table->string('method'); // mellat_gateway, bank_transfer, wallet, etc.
    $table->string('status'); // pending, completed, failed
    // ... gateway metadata ...
});
```

### Payment Processor Contract
```php
interface PaymentProcessorContract
{
    public function process(
        Order $order,              // ❌ Requires an Order
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): PaymentProcessResultData;

    public function verify(Payment $payment, array $callbackData): Payment;
}
```

### All Processors Expect an Order
- `WalletPaymentProcessor` - Debits wallet to pay for an **order**
- `MellatGatewayPaymentProcessor` - Redirects to gateway to pay for an **order**
- `BankTransferPaymentProcessor` - Records bank transfer for an **order**

## Why This Is a Problem for Wallet Top-Up

1. **Wallet top-up is NOT an order** - A customer charging their wallet is not purchasing a product
2. **Schema constraint** - `order_id` is a required foreign key - we can't create a Payment without an Order
3. **Business logic mismatch** - All payment processors are designed around the "pay for an order" use case
4. **Circular dependency** - We need a Payment to credit the wallet, but Payment requires an Order

## Solution: Make Payment.order_id Nullable

### The Database Change

**Migration:**
```php
Schema::table('payments', function (Blueprint $table) {
    $table->foreignId('order_id')
        ->nullable()  // ✅ Make it optional
        ->change();
});
```

**Why this works:**
- **Order payments continue unchanged** - All existing order payment flows keep working exactly as before
- **Wallet top-ups** - Can now create Payment records with `order_id = null`
- **Simple & clean** - One small schema change unlocks the entire use case

### Updated Payment Model Rules

A `Payment` record can now represent **two distinct transaction types**:

1. **Order Payment** (`order_id` is present):
   - Customer paying for a specific order
   - Uses: WALLET, MELLAT_GATEWAY, BANK_TRANSFER methods
   - Triggers: Order fulfillment (enrollments) on completion

2. **Wallet Top-Up** (`order_id` is NULL):
   - Customer depositing money into their wallet
   - Uses: MELLAT_GATEWAY, BANK_TRANSFER methods only (NOT wallet!)
   - Triggers: Wallet credit transaction on completion

## Architecture After the Change

### 1. Payment Processor Signature Update

**Current:**
```php
public function process(
    Order $order,              // ❌ Required
    PaymentCreateData $paymentData,
    Authenticatable $adminUser,
    int $amountToPay
): PaymentProcessResultData;
```

**New:**
```php
public function process(
    ?Order $order,              // ✅ Optional
    PaymentCreateData $paymentData,
    Authenticatable $adminUser,
    int $amountToPay
): PaymentProcessResultData;
```

### 2. Processor Implementation Rules

Each processor must handle both cases:

#### MellatGatewayPaymentProcessor
```php
public function process(
    ?Order $order,
    PaymentCreateData $paymentData,
    Authenticatable $adminUser,
    int $amountToPay
): PaymentProcessResultData {
    $transactionReference = $this->referenceService->generate();
    
    // Create payment record (order_id may be null for wallet top-up)
    $payment = Payment::create([
        'order_id'    => $order?->id,           // ✅ Nullable
        'customer_id' => $order?->customer_id ?? $adminUser->id, // For wallet: use authenticated user
        'amount'      => $amountToPay,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY->value,
        'status'      => PaymentStatusEnum::PENDING->value,
        // ... gateway metadata ...
    ]);
    
    // Request token from gateway (same for both cases)
    $gatewayResponse = $this->requestGatewayToken($payment, $amountToPay);
    
    return PaymentProcessResultData::redirect($payment, $gatewayResponse['redirect_url']);
}
```

#### WalletPaymentProcessor
```php
public function process(
    ?Order $order,
    PaymentCreateData $paymentData,
    Authenticatable $adminUser,
    int $amountToPay
): PaymentProcessResultData {
    // ❌ Wallet processor ONLY works for orders
    if ($order === null) {
        throw new \InvalidArgumentException('Wallet payment processor requires an order. Cannot use wallet to top up wallet.');
    }
    
    // ... existing order payment logic ...
}
```

### 3. Payment Completion Event Handler

**Update:** `PaymentCompletedListener` must detect payment type:

```php
final class PaymentCompletedListener
{
    public function handle(PaymentCompletedEvent $event): void
    {
        $payment = $event->payment;
        
        // Case 1: Order Payment - Trigger fulfillment
        if ($payment->order_id !== null) {
            $this->processOrderPaymentCompletion($payment);
            return;
        }
        
        // Case 2: Wallet Top-Up - Credit wallet
        if ($payment->order_id === null) {
            $this->processWalletTopupCompletion($payment);
            return;
        }
    }
    
    private function processOrderPaymentCompletion(Payment $payment): void
    {
        // Existing: Create enrollments, update order status, send emails
    }
    
    private function processWalletTopupCompletion(Payment $payment): void
    {
        // NEW: Credit the user's wallet
        $wallet = Wallet::firstOrCreate(['user_id' => $payment->customer_id]);
        
        app(TopupWalletAction::class)->handle($payment);
    }
}
```

### 4. New Action: TopupWalletAction

```php
<?php

namespace App\Actions\Shop\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Payment;
use App\Models\Wallet;

final readonly class TopupWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransaction,
    ) {}

    /**
     * Credit a user's wallet based on a completed payment.
     * 
     * This is triggered automatically when a wallet top-up payment is verified.
     */
    public function handle(Payment $completedPayment): void
    {
        // Validate this is a wallet top-up payment
        if ($completedPayment->order_id !== null) {
            throw new \InvalidArgumentException('This payment is for an order, not a wallet top-up.');
        }
        
        if ($completedPayment->status !== PaymentStatusEnum::COMPLETED) {
            throw new \InvalidArgumentException('Payment must be completed to credit wallet.');
        }
        
        // Get or create wallet
        $wallet = Wallet::firstOrCreate(['user_id' => $completedPayment->customer_id]);
        
        // Create transaction data
        $transactionData = new RecordTransactionData(
            wallet_id: $wallet->id,
            amount: $completedPayment->amount,
            type: TransactionTypeEnum::CREDIT->value,
            description: "Wallet top-up via {$completedPayment->method->value}",
            reference_number: $completedPayment->uuid,
            source_type: TransactionSourceEnum::PAYMENT->value,
            source_id: $completedPayment->id,
            metadata: [
                'payment_method' => $completedPayment->method->value,
                'gateway_reference' => $completedPayment->last_gateway_reference,
                'top_up_timestamp' => now()->toIso8601String(),
            ],
        );
        
        // Record the transaction (handles locking, balance update, risk assessment)
        $this->recordTransaction->handle($transactionData);
    }
}
```

### 5. New Shop Controller: WalletTopupController

```php
<?php

namespace App\Http\Controllers\Api\Shop\Wallet;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Data\Shop\Wallet\WalletTopupRequestData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payment\PaymentProcessorFactory;

/**
 * @group Wallet Management
 * @subgroup Wallet Top-Up
 * 
 * @authenticated
 */
final class WalletTopupController extends Controller
{
    /**
     * Initiate a wallet top-up payment.
     * 
     * This endpoint allows an authenticated user to add funds to their wallet.
     * 
     * **Supported Payment Methods:**
     * - `mellat_gateway`: Redirects to Mellat Bank gateway
     * - `bank_transfer`: Manual bank transfer (requires admin verification)
     * 
     * **Response Types:**
     * - **Redirect Required:** `requires_redirect = true` - Frontend must redirect user to `redirect_url`
     * - **No Redirect:** `requires_redirect = false` - Payment is pending admin verification
     * 
     * @responseFile storage/responses/shop/wallet/topup-result.json
     */
    public function topup(
        WalletTopupRequestData $data,
        PaymentProcessorFactory $processorFactory
    ): ApiResponseInterface {
        /** @var User $user */
        $user = auth()->user();
        
        $paymentMethod = PaymentMethodEnum::from($data->payment_method);
        
        // Validate: Cannot use wallet to top up wallet
        if ($paymentMethod === PaymentMethodEnum::WALLET) {
            throw ValidationException::withMessages([
                'payment_method' => ['Cannot use wallet to top up wallet. Please use a different payment method.'],
            ]);
        }
        
        // Get the appropriate payment processor
        $processor = $processorFactory->make($paymentMethod);
        
        // Create PaymentCreateData
        $paymentData = new PaymentCreateData(
            method: $paymentMethod->value,
            data: $data->payment_data,  // For bank_transfer: transaction_id, sender_name, etc.
            admin_notes: null,
        );
        
        // Process payment WITHOUT an order (order = null)
        $result = $processor->process(
            paymentData: $paymentData,              // ✅ No order for wallet top-up
            user: $user,
            amountToPay: $data->amount,
            order: null
        );
        
        // Return result (redirect URL for gateways, or pending status for bank transfer)
        return apiResponse()->created([
            'payment'           => PaymentData::from($result->payment),
            'requires_redirect' => $result->requiresRedirect(),
            'redirect_url'      => $result->redirect_url,
            'redirect_data'     => $result->redirect_data,
            'message'           => $result->requiresRedirect()
                ? 'Redirecting to payment gateway...'
                : 'Payment is pending verification.',
        ]);
    }
}
```

### 6. New DTO: WalletTopupRequestData

```php
<?php

namespace App\Data\Shop\Wallet;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

final class WalletTopupRequestData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(10000)]
        public int $amount,                  // Minimum 10,000 Rials

        #[Required]
        public string $payment_method,       // mellat_gateway, bank_transfer

        #[Nullable]
        public ?array $payment_data = null,  // For bank_transfer: transaction_id, sender_name, etc.
    ) {}

    public static function rules(): array
    {
        return [
            'amount'         => ['required', 'integer', 'min:10000'],
            'payment_method' => ['required', 'in:mellat_gateway,bank_transfer'],
            'payment_data'   => ['nullable', 'array'],
        ];
    }

    /** @codeCoverageIgnore */
    public static function bodyParameters(): array
    {
        return [
            'amount'         => [
                'description' => 'The amount to add to the wallet (in Rials). Minimum: 10,000.',
                'example'     => 500000,
            ],
            'payment_method' => [
                'description' => 'The payment method to use for the top-up.',
                'example'     => 'mellat_gateway',
            ],
            'payment_data'   => [
                'description' => 'Additional payment method data (e.g., bank transfer details).',
                'example'     => [
                    'transaction_id'   => 'TXN123456',
                    'sender_name'      => 'John Doe',
                    'transaction_date' => '2025-12-07',
                ],
            ],
        ];
    }
}
```

### 7. Route Registration

```php
// routes/Api/V1/customer.php

Route::prefix('wallet')->name('wallet.')->group(function () {
    Route::post('topup', [WalletTopupController::class, 'topup'])->name('topup');
    // Future: GET balance, GET transactions, etc.
});
```

### 8. Payment Verification Flow (Unchanged!)

The existing `VerifyPaymentAction` and callback flow work **perfectly** for both cases:

```php
// app/Actions/Shop/Payment/VerifyPaymentAction.php
public function handle(GatewayCallbackData $data): Payment
{
    return DB::transaction(function () use ($data): Payment {
        $payment = Payment::where('uuid', $data->payment_uuid)
            ->lockForUpdate()
            ->firstOrFail();
        
        if ($payment->status !== PaymentStatusEnum::PENDING) {
            throw new InvalidPaymentStateException('Payment is not in pending state.');
        }
        
        $processor = $this->processorFactory->make($payment->method);
        
        // Verify the payment (works for both order payments and wallet top-ups)
        $verifiedPayment = $processor->verify($payment, $data->gateway_response);
        
        // If completed, PaymentCompletedEvent is dispatched
        // Listener detects payment type (order or wallet) and acts accordingly
        
        return $verifiedPayment;
    });
}
```

**The beauty:** No changes needed! The event listener handles routing based on `order_id`.

## Migration Steps

### Step 1: Schema Migration
```bash
sail artisan make:migration make_order_id_nullable_in_payments_table
```

```php
public function up(): void
{
    Schema::table('payments', function (Blueprint $table) {
        $table->foreignId('order_id')
            ->nullable()
            ->change();
    });
}

public function down(): void
{
    // Cannot rollback safely if wallet top-up payments exist
    Schema::table('payments', function (Blueprint $table) {
        $table->foreignId('order_id')
            ->nullable(false)
            ->change();
    });
}
```

### Step 2: Update Payment Processor Contract & Implementations

1. Make `Order $order` parameter nullable in `PaymentProcessorContract`
2. Update all processor implementations:
   - `MellatGatewayPaymentProcessor` - Handle null order for wallet top-up
   - `BankTransferPaymentProcessor` - Handle null order for wallet top-up
   - `WalletPaymentProcessor` - Throw exception if order is null
3. Update `CreatePaymentAction` (admin) - Keep as-is (always has an order)

### Step 3: Update Payment Completed Listener

Add logic to detect and handle wallet top-up payments (order_id is null).

### Step 4: Create Shop Layer Wallet Actions & Controller

1. `app/Actions/Shop/Wallet/TopupWalletAction.php` - Credit wallet from completed payment
2. `app/Http/Controllers/Api/Shop/Wallet/WalletTopupController.php` - Customer-facing endpoint
3. `app/Data/Shop/Wallet/WalletTopupRequestData.php` - Request DTO

### Step 5: Add Route

```php
Route::post('wallet/topup', [WalletTopupController::class, 'topup'])->name('wallet.topup');
```

### Step 6: Write Tests

```php
// tests/Integration/Actions/Shop/Wallet/TopupWalletActionTest.php
it('credits wallet when payment is completed', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 100000]);
    
    // Create a wallet top-up payment (order_id = null)
    $payment = Payment::factory()->create([
        'order_id'    => null,  // ✅ Wallet top-up
        'customer_id' => $user->id,
        'amount'      => 500000,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);
    
    $action = app(TopupWalletAction::class);
    $action->handle($payment);
    
    expect($wallet->fresh()->balance)->toBe(600000);
});

// tests/Feature/Api/Shop/Wallet/WalletTopupTest.php
it('customer can initiate wallet top-up', function () {
    $this->customer();
    
    $response = postJson(route('wallet.topup'), [
        'amount'         => 500000,
        'payment_method' => 'mellat_gateway',
    ]);
    
    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => [
            'payment',
            'requires_redirect',
            'redirect_url',
        ],
    ]);
    
    $this->assertDatabaseHas('payments', [
        'order_id'    => null,  // ✅ No order
        'customer_id' => $this->user->id,
        'amount'      => 500000,
        'method'      => 'mellat_gateway',
        'status'      => 'pending',
    ]);
});
```

## Benefits of This Approach

✅ **Minimal Changes:** One schema change + small processor updates  
✅ **Reuses Existing Infrastructure:** Gateway integration, verification flow, event system  
✅ **Type Safe:** Nullable Order in contract makes intent explicit  
✅ **Backward Compatible:** All existing order payment flows unchanged  
✅ **Clean Separation:** Event listener routes payment completion based on type  
✅ **Immutable Ledger:** Wallet transactions still use the same robust `RecordWalletTransactionAction`  

## Alternative Rejected Approaches

### ❌ Option 1: Create a Separate WalletTopup Model
- **Problem:** Duplicates all payment gateway integration logic
- **Problem:** Two parallel systems for the same thing (Mellat gateway, verification, callbacks)
- **Problem:** More code to maintain

### ❌ Option 2: Create a Dummy "Wallet Top-Up" Order
- **Problem:** Semantic mismatch - an order is a purchase, not a deposit
- **Problem:** Confuses order analytics and reporting
- **Problem:** Requires handling "fake orders" in fulfillment logic

### ❌ Option 3: Keep order_id Required and Only Support Admin Deposits
- **Problem:** Blocks customer self-service - they can't add funds themselves
- **Problem:** Admin bottleneck for simple financial operations

## Conclusion

**Making `Payment.order_id` nullable is the cleanest and most pragmatic solution.** It acknowledges that payments can serve two purposes (paying for orders OR depositing funds), and it reuses all the existing payment infrastructure without duplication.

The Payment model becomes a **general-purpose financial transaction record** that can be linked to an order OR stand alone as a wallet top-up.

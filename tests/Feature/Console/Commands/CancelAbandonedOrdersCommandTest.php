<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderCancellationReasonEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionErrorCodeEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\travelTo;

mutates(App\Console\Commands\CancelAbandonedOrdersCommand::class);

it('cancels abandoned orders older than timeout threshold', function (): void {
    // Create an abandoned order (35 minutes old, pending, no payment)
    $user   = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $term   = Term::factory()->create();
    $course = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'status'           => PublicationStatusEnum::PUBLISHED,
    ]);

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);

    // Travel back in time to create an old order
    travelTo(now()->subMinutes(35));

    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    $orderItem = OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $deliveryOption->id,
    ]);

    $enrollment = Enrollment::factory()->create([
        'order_id'                   => $order->id,
        'order_item_id'              => $orderItem->id,
        'customer_id'                => $user->id,
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::AWAITING_PAYMENT,
    ]);

    Carbon::setTestNow();

    // Run the command
    artisan('orders:cancel-abandoned --timeout=30')
        ->assertExitCode(0)
        ->expectsOutputToContain('Checking for abandoned orders')
        ->expectsOutput('Found 1 abandoned order(s).')
        ->expectsOutput("✓ Cancelled order #{$order->increment_id} (ID: {$order->id})")
        ->expectsOutput('Successfully cancelled 1 out of 1 abandoned order(s).')
        ->assertExitCode(0);

    // Assert order and enrollment were cancelled
    assertDatabaseHas('orders', [
        'id'     => $order->id,
        'status' => OrderStatusEnum::CANCELLED->value,
    ]);

    $order->refresh();
    expect($order->cancelled_at)->not->toBeNull()
        ->and($order->cancelled_reason)->toBe(OrderCancellationReasonEnum::ABANDONED);

    assertDatabaseHas('enrollments', [
        'id'                => $enrollment->id,
        'enrollment_status' => EnrollmentStatusEnum::CANCELLED->value,
    ]);
});

it('does not cancel recent pending orders', function (): void {
    $user = User::factory()->create();

    // Create a recent order (only 10 minutes old)
    travelTo(now()->subMinutes(10));

    Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    Carbon::setTestNow();

    artisan('orders:cancel-abandoned --timeout=30')
        ->expectsOutput('No abandoned orders found.')
        ->assertExitCode(0);
});

it('does not cancel orders with completed payment', function (): void {
    $user = User::factory()->create();

    travelTo(now()->subMinutes(35));

    Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);
    Payment::factory()
        ->create([
            'order_id'    => Order::latest()->first()->id,
            'customer_id' => $user->id,
            'amount'      => 100,
            'method'      => PaymentMethodEnum::BANK_TRANSFER,
            'status'      => PaymentStatusEnum::COMPLETED->value,
        ]);
    Carbon::setTestNow();

    artisan('orders:cancel-abandoned --timeout=30')
        ->expectsOutputToContain('Checking for abandoned orders')
        ->expectsOutput('No abandoned orders found.')
        ->assertExitCode(0);
});

it('cancels abandoned orders whose payment attempts never completed', function (PaymentStatusEnum $status): void {
    $user = User::factory()->create();

    travelTo(now()->subMinutes(35));

    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $user->id,
        'status'      => $status->value,
    ]);

    Carbon::setTestNow();

    artisan('orders:cancel-abandoned --timeout=30')
        ->expectsOutput('Found 1 abandoned order(s).')
        ->expectsOutput("✓ Cancelled order #{$order->increment_id} (ID: {$order->id})")
        ->assertExitCode(0);

    assertDatabaseHas('orders', [
        'id'     => $order->id,
        'status' => OrderStatusEnum::CANCELLED->value,
    ]);
})->with([
    'failed attempt'  => PaymentStatusEnum::FAILED,
    'pending attempt' => PaymentStatusEnum::PENDING,
]);

it('closes pending payment attempts when cancelling an abandoned order', function (): void {
    $user = User::factory()->create();

    travelTo(now()->subMinutes(35));

    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    $payment = Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $user->id,
        'status'      => PaymentStatusEnum::PENDING,
    ]);

    $transaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000012',
        'initiated_at'          => now()->subMinutes(35),
        'completed_at'          => null,
    ]);

    Carbon::setTestNow();

    artisan('orders:cancel-abandoned --timeout=30')
        ->expectsOutput('Found 1 abandoned order(s).')
        ->assertExitCode(0);

    $transaction->refresh();
    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED)
        ->and($transaction->status)->toBe(PaymentTransactionStatusEnum::FAILED)
        ->and($transaction->error_code)->toBe(PaymentTransactionErrorCodeEnum::ORDER_CLOSED->value);
});

it('does not cancel an old order with a recent payment attempt', function (): void {
    $user = User::factory()->create();

    travelTo(now()->subMinutes(45));

    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $user->id,
        'status'      => PaymentStatusEnum::FAILED->value,
    ]);

    Carbon::setTestNow();

    // The customer retried 10 minutes ago, so the inactivity window restarted.
    travelTo(now()->subMinutes(10));
    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $user->id,
        'status'      => PaymentStatusEnum::FAILED->value,
    ]);
    Carbon::setTestNow();

    artisan('orders:cancel-abandoned --timeout=30')
        ->expectsOutput('No abandoned orders found.')
        ->assertExitCode(0);

    assertDatabaseHas('orders', [
        'id'     => $order->id,
        'status' => OrderStatusEnum::PENDING->value,
    ]);
});

it('does not cancel an old order that was updated recently', function (): void {
    $user = User::factory()->create();

    travelTo(now()->subMinutes(45));

    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
    ]);

    Carbon::setTestNow();

    // Any activity on the order restarts the inactivity window.
    $order->touch();

    artisan('orders:cancel-abandoned --timeout=30')
        ->expectsOutput('No abandoned orders found.')
        ->assertExitCode(0);

    assertDatabaseHas('orders', [
        'id'     => $order->id,
        'status' => OrderStatusEnum::PENDING->value,
    ]);
});

it('does not cancel orders that are already completed or cancelled', function (): void {
    $user = User::factory()->create();

    travelTo(now()->subMinutes(35));

    // Already completed
    Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::COMPLETED,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    // Already cancelled
    Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::CANCELLED,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    Carbon::setTestNow();

    artisan('orders:cancel-abandoned --timeout=30')
        ->expectsOutputToContain('Checking for abandoned orders')
        ->expectsOutput('No abandoned orders found.')
        ->assertExitCode(0);
});

it('supports dry-run mode without making changes', function (): void {
    $user = User::factory()->create();

    travelTo(now()->subMinutes(35));

    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    Carbon::setTestNow();

    artisan('orders:cancel-abandoned --timeout=30 --dry-run')
        ->expectsOutput('Found 1 abandoned order(s).')
        ->expectsOutput('DRY RUN MODE - No changes will be made')
        ->assertExitCode(0);

    // Order should still be pending
    assertDatabaseHas('orders', [
        'id'     => $order->id,
        'status' => OrderStatusEnum::PENDING->value,
    ]);
});

it('cancels multiple abandoned orders at once', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    travelTo(now()->subMinutes(40));

    $order1 = Order::factory()->create([
        'customer_id' => $user1->id,
        'status'      => OrderStatusEnum::PENDING,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    $order2 = Order::factory()->create([
        'customer_id' => $user2->id,
        'status'      => OrderStatusEnum::PENDING,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    $order3 = Order::factory()->create([
        'customer_id' => $user3->id,
        'status'      => OrderStatusEnum::PENDING,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    Carbon::setTestNow();

    artisan('orders:cancel-abandoned --timeout=30')
        ->expectsOutput('Found 3 abandoned order(s).')
        ->expectsOutput('Successfully cancelled 3 out of 3 abandoned order(s).')
        ->assertExitCode(0);

    assertDatabaseHas('orders', ['id' => $order1->id, 'status' => OrderStatusEnum::CANCELLED->value]);
    assertDatabaseHas('orders', ['id' => $order2->id, 'status' => OrderStatusEnum::CANCELLED->value]);
    assertDatabaseHas('orders', ['id' => $order3->id, 'status' => OrderStatusEnum::CANCELLED->value]);
});

it('respects custom timeout parameter', function (): void {
    $user = User::factory()->create();

    // Create order 45 minutes old
    travelTo(now()->subMinutes(45));

    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    Carbon::setTestNow();

    // With 60 minute timeout, should not cancel
    artisan('orders:cancel-abandoned --timeout=60')
        ->expectsOutput('No abandoned orders found.')
        ->assertExitCode(0);

    assertDatabaseHas('orders', [
        'id'     => $order->id,
        'status' => OrderStatusEnum::PENDING->value,
    ]);

    // With 30 minute timeout, should cancel
    artisan('orders:cancel-abandoned --timeout=30')
        ->expectsOutput('Found 1 abandoned order(s).')
        ->assertExitCode(0);

    assertDatabaseHas('orders', [
        'id'     => $order->id,
        'status' => OrderStatusEnum::CANCELLED->value,
    ]);
});

it('releases reservations and cancels enrollments only for unpaid items', function (): void {
    $user   = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $term   = Term::factory()->create();
    $course = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'status'           => PublicationStatusEnum::PUBLISHED,
    ]);

    $deliveryOption1 = ProductDeliveryOption::factory()->create([
        'product_id'     => $product->id,
        'status'         => PublicationStatusEnum::PUBLISHED,
        'reserved_count' => 1,
    ]);

    $deliveryOption2 = ProductDeliveryOption::factory()->create([
        'product_id'     => $product->id,
        'status'         => PublicationStatusEnum::PUBLISHED,
        'reserved_count' => 1,
    ]);

    travelTo(now()->subMinutes(35));

    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'status'      => OrderStatusEnum::PENDING,
        // 'payment_status' field removed - calculated accessorPENDING,
    ]);

    $item1 = OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $deliveryOption1->id,
        'status'                     => OrderItemStatusEnum::PENDING,
    ]);

    $item2 = OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $deliveryOption2->id,
        'status'                     => OrderItemStatusEnum::COMPLETED,
    ]);

    // One enrollment is awaiting payment (should be cancelled)
    $enrollment1 = Enrollment::factory()->create([
        'order_id'                   => $order->id,
        'order_item_id'              => $item1->id,
        'customer_id'                => $user->id,
        'product_delivery_option_id' => $deliveryOption1->id,
        'enrollment_status'          => EnrollmentStatusEnum::AWAITING_PAYMENT,
    ]);

    // Another enrollment is already active (should NOT be cancelled)
    $enrollment2 = Enrollment::factory()->create([
        'order_id'                   => $order->id,
        'order_item_id'              => $item2->id,
        'customer_id'                => $user->id,
        'product_delivery_option_id' => $deliveryOption2->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    Carbon::setTestNow();

    artisan('orders:cancel-abandoned --timeout=30')
        ->assertExitCode(0);

    assertDatabaseHas('enrollments', [
        'id'                => $enrollment1->id,
        'enrollment_status' => EnrollmentStatusEnum::CANCELLED->value,
    ]);

    assertDatabaseHas('enrollments', [
        'id'                => $enrollment2->id,
        'enrollment_status' => EnrollmentStatusEnum::ACTIVE->value, // Unchanged
    ]);

    // The pending line still held a seat; the completed line already consumed it.
    expect($deliveryOption1->fresh()->reserved_count)->toBe(0)
        ->and($deliveryOption2->fresh()->reserved_count)->toBe(1);
});

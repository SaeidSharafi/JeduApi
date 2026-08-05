<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Creates a COMPLETED NO_PAYMENT record for a free order and fires the
 * PaymentCompletedEvent inside a transaction.
 *
 * Shared by the shop checkout flow (CreateOrderFromCartAction) and the admin
 * payment flow (CreatePaymentAction) so both paths are atomic and consistent.
 */
final readonly class CompleteFreeOrderPaymentAction
{
    /**
     * @param  Order  $order  The free order (grand_total <= 0).
     * @param  Authenticatable|null  $actor  Who triggered the payment (customer or admin).
     * @param  string|null  $adminNotes  Optional admin notes; defaults to a canned message.
     */
    public function handle(Order $order, ?Authenticatable $actor = null, ?string $adminNotes = null): PaymentProcessResultData
    {
        $payment = Payment::create([
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'amount'      => 0,
            'method'      => PaymentMethodEnum::NO_PAYMENT->value,
            'purpose'     => PaymentPurposeEnum::ORDER->value,
            'status'      => PaymentStatusEnum::COMPLETED->value,
            'created_by'  => $actor instanceof Staff ? $actor->id : null,
            'admin_notes' => $adminNotes ?? 'Free order automatically completed.',
        ]);

        PaymentCompletedEvent::dispatch($payment);

        return PaymentProcessResultData::completed($payment);
    }
}

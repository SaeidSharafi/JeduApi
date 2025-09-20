<?php

declare(strict_types=1);

namespace App\Data\Casts;

use App\Contracts\WalletTransactionSourceableContract;
use App\Data\Admin\Order\OrderData;
use App\Data\Admin\Order\OrderListItemData;
use App\Data\Admin\Payment\PaymentData;
use App\Data\Admin\Refund\RefundData;
use App\Data\Admin\Staff\ShowStaffData;
use App\Data\Admin\Staff\StaffListItemData;
use App\Data\Admin\WalletCampaign\WalletCampaignData;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Staff;
use App\Models\WalletCampaign;
use InvalidArgumentException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * @codeCoverageIgnore
 */
final readonly class TransactionSourceCast implements Cast
{
    public function __construct(private bool $short = false) {}

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (! $value) {
            return null;
        }
        if (! ($value instanceof WalletTransactionSourceableContract)) {
            throw new InvalidArgumentException('Value must implement WalletTransactionSourceableContract, '.gettype($value).' given.');
        }

        return match (true) {
            $value instanceof Staff          => $this->getStaffData($value),
            $value instanceof Order          => $this->getOrderData($value),
            $value instanceof Refund         => $this->getRefundData($value),
            $value instanceof Payment        => $this->getPaymentData($value),
            $value instanceof WalletCampaign => WalletCampaignData::from($value),
            default                          => null,
        };

    }

    private function getStaffData($value): ShowStaffData|StaffListItemData
    {
        if ($this->short) {
            return StaffListItemData::from($value);
        }

        return ShowStaffData::from($value);
    }

    private function getOrderData($value): OrderData|OrderListItemData
    {
        if ($this->short) {
            return OrderListItemData::from($value);
        }

        return OrderData::from($value);
    }

    private function getRefundData($value): RefundData
    {
        return RefundData::from($value->order);
    }

    private function getPaymentData($value): PaymentData
    {
        return PaymentData::from($value->order);
    }
}

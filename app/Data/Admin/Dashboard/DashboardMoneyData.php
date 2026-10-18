<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** Money position of the selected period: settlement mix, product-type mix, free orders and the offline remainder. */
final class DashboardMoneyData extends Data
{
    public function __construct(
        public DashboardPaymentMixData $payment_mix,
        public DashboardProductTypeMixData $product_type_mix,
        public int $free_orders,
        public int $offline_balance,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Dashboard\DashboardMoneyData;
use App\Data\Admin\Dashboard\DashboardPeriodQueryData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Dashboard\DashboardBlockCache;
use App\Services\Dashboard\DashboardPeriod;
use App\Services\Dashboard\DashboardPeriodWindow;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Dashboard
 *
 * @authenticated
 */
final class MoneyController extends Controller
{
    /**
     * Every product-type bucket the response must carry, in contract order.
     *
     * @var list<string>
     */
    private const array PRODUCT_TYPE_BUCKETS = ['course', 'bundle', 'seminar', 'digital_asset', 'unknown'];

    /**
     * Get the money position of the selected period.
     *
     * Splits the paid-order cohort into full settlement and Prepayment deposit, groups the same
     * cohort by product type, counts free orders, and reports the offline remainder collected in
     * person. Free orders never enter the mixes.
     *
     * @responseFile 200 resources/responses/admin/dashboard/money.json
     */
    public function __invoke(DashboardPeriodQueryData $query, DashboardBlockCache $cache): ApiResponseInterface
    {
        Gate::authorize(PermissionEnum::DASHBOARD_FINANCIALS->value);

        $period = DashboardPeriod::resolve($query->period);

        $block = $cache->remember(
            CacheKey::AdminDashboardMoney,
            ['period'    => $period->key()],
            fn (): array => $this->moneyBlock($period->current()),
            $query->fresh,
        );

        return apiResponse()->success(DashboardMoneyData::from($block['payload']), metadata: $block['metadata']);
    }

    /**
     * @return array{
     *     payment_mix: array{
     *         full: array{orders: int, amount: int, share_pct: float},
     *         deposit: array{orders: int, amount: int, share_pct: float},
     *     },
     *     product_type_mix: array<string, array{orders: int, amount: int, share_pct: float}>,
     *     free_orders: int,
     *     offline_balance: int,
     * }
     */
    private function moneyBlock(DashboardPeriodWindow $window): array
    {
        $settlement = $this->settlementMix($window);

        return [
            'payment_mix'      => $this->paymentMixPayload($settlement),
            'product_type_mix' => $this->productTypeMixPayload($this->productTypeMix($window)),
            'free_orders'      => $settlement['free_orders'],
            'offline_balance'  => $this->offlineBalance($window),
        ];
    }

    /**
     * Order ids that carry a completed order payment, deduplicated so a retried payment cannot
     * duplicate an order in an aggregate.
     */
    private function paidOrderIds(): Builder
    {
        return DB::table('payments')
            ->select('order_id')
            ->where('status', PaymentStatusEnum::COMPLETED->value)
            ->where('purpose', PaymentPurposeEnum::ORDER->value)
            ->whereNotNull('order_id')
            ->distinct();
    }

    /**
     * Counts free orders in the window and sums the paid, non-free cohort by settlement type.
     *
     * Free orders carry a completed `no_payment` record but no money, so they are counted here and
     * excluded from every mix and from the offline remainder.
     *
     * @return array{
     *     free_orders: int,
     *     cohort_orders: int,
     *     cohort_amount: int,
     *     deposit_orders: int,
     *     deposit_amount: int,
     * }
     */
    private function settlementMix(DashboardPeriodWindow $window): array
    {
        $prepaymentOrders = DB::table('order_items')
            ->select('order_id')
            ->where('payment_type', OrderItemPaymentTypeEnum::PRE_PAYMENT->value)
            ->distinct();

        $totals = (array) Order::query()
            ->leftJoinSub($this->paidOrderIds(), 'paid_order', 'paid_order.order_id', '=', 'orders.id')
            ->leftJoinSub($prepaymentOrders, 'prepayment_order', 'prepayment_order.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$window->start, $window->end])
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.grand_total <= 0 THEN 1 ELSE 0 END), 0) AS free_orders')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN orders.grand_total > 0 AND paid_order.order_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS cohort_orders',
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN orders.grand_total > 0 AND paid_order.order_id IS NOT NULL THEN orders.grand_total ELSE 0 END), 0) AS cohort_amount',
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN orders.grand_total > 0 AND paid_order.order_id IS NOT NULL AND prepayment_order.order_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS deposit_orders',
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN orders.grand_total > 0 AND paid_order.order_id IS NOT NULL AND prepayment_order.order_id IS NOT NULL THEN orders.grand_total ELSE 0 END), 0) AS deposit_amount',
            )
            ->toBase()
            ->first();

        return [
            'free_orders'    => (int) ($totals['free_orders'] ?? 0),
            'cohort_orders'  => (int) ($totals['cohort_orders'] ?? 0),
            'cohort_amount'  => (int) ($totals['cohort_amount'] ?? 0),
            'deposit_orders' => (int) ($totals['deposit_orders'] ?? 0),
            'deposit_amount' => (int) ($totals['deposit_amount'] ?? 0),
        ];
    }

    /**
     * @param  array{free_orders: int, cohort_orders: int, cohort_amount: int, deposit_orders: int, deposit_amount: int}  $settlement
     * @return array{
     *     full: array{orders: int, amount: int, share_pct: float},
     *     deposit: array{orders: int, amount: int, share_pct: float},
     * }
     */
    private function paymentMixPayload(array $settlement): array
    {
        return [
            'full' => $this->mixBucket(
                $settlement['cohort_orders'] - $settlement['deposit_orders'],
                $settlement['cohort_amount'] - $settlement['deposit_amount'],
                $settlement['cohort_amount'],
            ),
            'deposit' => $this->mixBucket(
                $settlement['deposit_orders'],
                $settlement['deposit_amount'],
                $settlement['cohort_amount'],
            ),
        ];
    }

    /**
     * Line value of the paid, non-free cohort per product type.
     *
     * Standalone lines follow the delivery option's product morph alias; a Bundle Purchase is one
     * `bundle` line at its `selling_price` and its component lines (`bundle_purchase_id` set) never
     * count. A line without a delivery option or product falls into `unknown`.
     *
     * @return array<string, array{orders: int, amount: int}>
     */
    private function productTypeMix(DashboardPeriodWindow $window): array
    {
        $knownTypes       = array_values(array_diff(self::PRODUCT_TYPE_BUCKETS, ['unknown']));
        $typePlaceholders = implode(', ', array_fill(0, count($knownTypes), '?'));
        $typeExpression   = "CASE WHEN products.productable_type IN ({$typePlaceholders}) THEN products.productable_type ELSE ? END";

        $standaloneLines = DB::table('order_items as items')
            ->join('orders', 'orders.id', '=', 'items.order_id')
            ->leftJoinSub($this->paidOrderIds(), 'paid_order', 'paid_order.order_id', '=', 'orders.id')
            ->leftJoin('product_delivery_options as options', 'options.id', '=', 'items.product_delivery_option_id')
            ->leftJoin('products', 'products.id', '=', 'options.product_id')
            ->whereBetween('orders.created_at', [$window->start, $window->end])
            ->where('orders.grand_total', '>', 0)
            ->whereNotNull('paid_order.order_id')
            ->whereNull('items.bundle_purchase_id')
            ->select('items.order_id as order_id')
            ->selectRaw("{$typeExpression} as type", [...$knownTypes, 'unknown'])
            ->selectRaw('items.total as amount');

        $bundleLines = DB::table('bundle_purchases as purchases')
            ->join('orders', 'orders.id', '=', 'purchases.order_id')
            ->leftJoinSub($this->paidOrderIds(), 'paid_order', 'paid_order.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$window->start, $window->end])
            ->where('orders.grand_total', '>', 0)
            ->whereNotNull('paid_order.order_id')
            ->selectRaw("purchases.order_id as order_id, 'bundle' as type, purchases.selling_price as amount");

        $rows = DB::query()
            ->fromSub($standaloneLines->unionAll($bundleLines), 'mix')
            ->select('type')
            ->selectRaw('COUNT(DISTINCT order_id) as orders')
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->groupBy('type')
            ->get();

        $buckets = array_fill_keys(self::PRODUCT_TYPE_BUCKETS, ['orders' => 0, 'amount' => 0]);

        foreach ($rows as $row) {
            $type = is_string($row->type) && array_key_exists($row->type, $buckets) ? $row->type : 'unknown';

            $buckets[$type]['orders'] += (int) $row->orders;
            $buckets[$type]['amount'] += (int) $row->amount;
        }

        return $buckets;
    }

    /**
     * @param  array<string, array{orders: int, amount: int}>  $buckets
     * @return array<string, array{orders: int, amount: int, share_pct: float}>
     */
    private function productTypeMixPayload(array $buckets): array
    {
        $total = array_sum(array_column($buckets, 'amount'));

        $payload = [];

        foreach ($buckets as $type => $bucket) {
            $payload[$type] = $this->mixBucket($bucket['orders'], $bucket['amount'], $total);
        }

        return $payload;
    }

    /**
     * The money a Prepayment order still collects in person.
     *
     * `order_items.price` is the unit price and `order_items.total` is the line total — for a
     * Prepayment line that total is `prepayment_amount × qty_ordered` — so the remainder is
     * `price × qty_ordered − total`. Only Prepayment lines of the paid, non-free cohort count.
     */
    private function offlineBalance(DashboardPeriodWindow $window): int
    {
        $balance = DB::table('order_items as items')
            ->join('orders', 'orders.id', '=', 'items.order_id')
            ->leftJoinSub($this->paidOrderIds(), 'paid_order', 'paid_order.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$window->start, $window->end])
            ->where('orders.grand_total', '>', 0)
            ->whereNotNull('paid_order.order_id')
            ->where('items.payment_type', OrderItemPaymentTypeEnum::PRE_PAYMENT->value)
            ->selectRaw('COALESCE(SUM(items.price * items.qty_ordered - items.total), 0) AS offline_balance')
            ->value('offline_balance');

        return (int) $balance;
    }

    /**
     * @return array{orders: int, amount: int, share_pct: float}
     */
    private function mixBucket(int $orders, int $amount, int $cohortAmount): array
    {
        return [
            'orders'    => $orders,
            'amount'    => $amount,
            'share_pct' => $cohortAmount > 0 ? round($amount / $cohortAmount * 100, 1) : 0.0,
        ];
    }
}

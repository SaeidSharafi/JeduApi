<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\System\MorphTypeEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the product, category and vendor rankings of one dashboard period.
 *
 * Cohort: orders created in the period with at least one completed order payment and a positive
 * grand total. Free orders are excluded: they carry no collectable revenue and a zero denominator
 * for the paid ratio.
 *
 * A line is worth `total × (order paid ÷ order grand total) − total_refunded`, where the order's
 * paid amount is the sum of its completed order payments.
 *
 * Bundle handling: Bundle Component Order Items (`order_items.bundle_purchase_id IS NOT NULL`) are
 * excluded, and each Bundle Purchase contributes once at `selling_price × paid ratio`, net of the
 * `total_refunded` sums of its component lines. It is grouped as the Bundle product and attributed
 * to that product's vendor, never to its component products or vendors.
 *
 * Category attribution unions two `categorizables` sources: the productable behind the product
 * (`course`, `seminar`, `digital_asset` or `bundle`) and the product itself (`product`). A line
 * contributes its full value to every distinct category of either source, so category revenue
 * deliberately overlaps and must not be summed into a period total.
 *
 * `share_pct` is one decimal of the cohort's total included line revenue, computed before any cap,
 * so a cap leaves the remainder visible instead of renormalising the returned rows to 100 %.
 *
 * A line with no vendor cannot be attributed and is omitted from the vendor leaderboard.
 */
final class DashboardRankingsReport
{
    private const int TOP_PRODUCTS_LIMIT = 6;

    private const int CATEGORIES_LIMIT = 6;

    private const int VENDORS_LIMIT = 4;

    /**
     * The cohort's total included line revenue is computed once, before any cap or dedup, and is
     * the denominator every row's `share_pct` uses.
     *
     * @return array{
     *     top_products: list<array{name: string, orders: int, revenue: int, share_pct: float}>,
     *     categories: list<array{name: string, orders: int, revenue: int, share_pct: float}>,
     *     vendors: list<array{name: string, orders: int, revenue: int, share_pct: float}>,
     * }
     */
    public function build(DashboardPeriodWindow $window): array
    {
        $lines         = $this->rankingLines($window);
        $cohortRevenue = array_sum(array_column($lines, 'value'));

        return [
            'top_products' => $this->products($lines, $cohortRevenue),
            'categories'   => $this->categories($lines, $cohortRevenue),
            'vendors'      => $this->vendors($lines, $cohortRevenue),
        ];
    }

    /**
     * One normalized row per sellable line of the cohort.
     *
     * @return list<array{
     *     order_id: int,
     *     product_id: int|null,
     *     productable_type: string|null,
     *     productable_id: int|null,
     *     product_name: string|null,
     *     snapshot_name: string,
     *     vendor_id: int|null,
     *     vendor_name: string|null,
     *     value: float,
     * }>
     */
    private function rankingLines(DashboardPeriodWindow $window): array
    {
        $completedOrderPayments = DB::table('payments')
            ->selectRaw('order_id, SUM(amount) AS paid')
            ->where('status', PaymentStatusEnum::COMPLETED->value)
            ->where('purpose', PaymentPurposeEnum::ORDER->value)
            ->whereNotNull('order_id')
            ->groupBy('order_id');

        $rows = $this->standaloneLineQuery($window, $completedOrderPayments)
            ->unionAll($this->bundleLineQuery($window, $completedOrderPayments))
            ->get();

        $lines = [];

        foreach ($rows as $row) {
            $grandTotal = (int) $row->order_grand_total;
            $ratio      = $grandTotal > 0 ? (float) $row->order_paid / $grandTotal : 0.0;

            $lines[] = [
                'order_id'         => (int) $row->order_id,
                'product_id'       => $row->product_id       === null ? null : (int) $row->product_id,
                'productable_type' => $row->productable_type === null ? null : (string) $row->productable_type,
                'productable_id'   => $row->productable_id   === null ? null : (int) $row->productable_id,
                'product_name'     => $this->normalizedName($row->product_name),
                'snapshot_name'    => (string) $row->snapshot_name,
                'vendor_id'        => $row->vendor_id === null ? null : (int) $row->vendor_id,
                'vendor_name'      => $this->normalizedName($row->vendor_name),
                'value'            => (int) $row->line_total * $ratio - (int) $row->line_refunded,
            ];
        }

        return $lines;
    }

    /**
     * Standalone order items: Bundle Component Order Items are excluded here.
     */
    private function standaloneLineQuery(
        DashboardPeriodWindow $window,
        Builder $completedOrderPayments,
    ): Builder {
        return DB::table('order_items as items')
            ->join('orders as orders', 'orders.id', '=', 'items.order_id')
            ->joinSub($completedOrderPayments, 'order_payments', 'order_payments.order_id', '=', 'orders.id')
            ->leftJoin('product_delivery_options as options', 'options.id', '=', 'items.product_delivery_option_id')
            ->leftJoin('products as products', 'products.id', '=', 'options.product_id')
            ->leftJoin('vendors as vendors', 'vendors.id', '=', 'items.vendor_id')
            ->whereNull('items.bundle_purchase_id')
            ->whereBetween('orders.created_at', [$window->start, $window->end])
            ->where('orders.grand_total', '>', 0)
            ->select([
                'items.order_id as order_id',
                'products.id as product_id',
                'products.productable_type as productable_type',
                'products.productable_id as productable_id',
                'products.name as product_name',
                'items.name as snapshot_name',
                'items.vendor_id as vendor_id',
                'vendors.name as vendor_name',
                'items.total as line_total',
                'items.total_refunded as line_refunded',
                'orders.grand_total as order_grand_total',
                'order_payments.paid as order_paid',
            ]);
    }

    /**
     * Bundle Purchases: one line at `selling_price`, net of the component lines' refunds.
     */
    private function bundleLineQuery(
        DashboardPeriodWindow $window,
        Builder $completedOrderPayments,
    ): Builder {
        $componentRefunds = DB::table('order_items')
            ->selectRaw('bundle_purchase_id, COALESCE(SUM(total_refunded), 0) AS refunded')
            ->whereNotNull('bundle_purchase_id')
            ->groupBy('bundle_purchase_id');

        return DB::table('bundle_purchases as purchases')
            ->join('orders as orders', 'orders.id', '=', 'purchases.order_id')
            ->joinSub($completedOrderPayments, 'order_payments', 'order_payments.order_id', '=', 'orders.id')
            ->leftJoin('product_delivery_options as options', 'options.id', '=', 'purchases.product_delivery_option_id')
            ->leftJoin('products as products', 'products.id', '=', 'options.product_id')
            ->leftJoin('vendors as vendors', 'vendors.id', '=', 'products.vendor_id')
            ->leftJoinSub($componentRefunds, 'component_refunds', 'component_refunds.bundle_purchase_id', '=', 'purchases.id')
            ->whereBetween('orders.created_at', [$window->start, $window->end])
            ->where('orders.grand_total', '>', 0)
            ->select([
                'purchases.order_id as order_id',
                'products.id as product_id',
                'products.productable_type as productable_type',
                'products.productable_id as productable_id',
                'products.name as product_name',
                'purchases.name as snapshot_name',
                'products.vendor_id as vendor_id',
                'vendors.name as vendor_name',
                'purchases.selling_price as line_total',
                DB::raw('COALESCE(component_refunds.refunded, 0) as line_refunded'),
                'orders.grand_total as order_grand_total',
                'order_payments.paid as order_paid',
            ]);
    }

    /**
     * Group lines by resolved product, or by the name snapshot when the product is unresolvable.
     *
     * @param  list<array{
     *     order_id: int,
     *     product_id: int|null,
     *     productable_type: string|null,
     *     productable_id: int|null,
     *     product_name: string|null,
     *     snapshot_name: string,
     *     vendor_id: int|null,
     *     vendor_name: string|null,
     *     value: float,
     * }>  $lines
     * @return list<array{name: string, orders: int, revenue: int, share_pct: float}>
     */
    private function products(array $lines, float $cohortRevenue): array
    {
        $groups = [];

        foreach ($lines as $line) {
            $name = $line['product_name'] ?? $line['snapshot_name'];

            if ($name === '') {
                continue;
            }

            $key = $line['product_id'] === null ? 'name:'.$name : 'product:'.$line['product_id'];

            $groups[$key] ??= ['name' => $name, 'order_ids' => [], 'revenue' => 0.0];
            $groups[$key]['order_ids'][$line['order_id']] = true;
            $groups[$key]['revenue'] += $line['value'];
        }

        return $this->rankedRows($groups, self::TOP_PRODUCTS_LIMIT, $cohortRevenue);
    }

    /**
     * Group lines by the order-item vendor; a Bundle Purchase already carries its product's vendor.
     *
     * @param  list<array{
     *     order_id: int,
     *     product_id: int|null,
     *     productable_type: string|null,
     *     productable_id: int|null,
     *     product_name: string|null,
     *     snapshot_name: string,
     *     vendor_id: int|null,
     *     vendor_name: string|null,
     *     value: float,
     * }>  $lines
     * @return list<array{name: string, orders: int, revenue: int, share_pct: float}>
     */
    private function vendors(array $lines, float $cohortRevenue): array
    {
        $groups = [];

        foreach ($lines as $line) {
            if ($line['vendor_id'] === null || $line['vendor_name'] === null) {
                continue;
            }

            $key = 'vendor:'.$line['vendor_id'];

            $groups[$key] ??= ['name' => $line['vendor_name'], 'order_ids' => [], 'revenue' => 0.0];
            $groups[$key]['order_ids'][$line['order_id']] = true;
            $groups[$key]['revenue'] += $line['value'];
        }

        return $this->rankedRows($groups, self::VENDORS_LIMIT, $cohortRevenue);
    }

    /**
     * Attribute every line to each distinct catalogue category of its productable and of the
     * product itself, preserving overlap but never counting the same category twice for one line.
     *
     * @param  list<array{
     *     order_id: int,
     *     product_id: int|null,
     *     productable_type: string|null,
     *     productable_id: int|null,
     *     product_name: string|null,
     *     snapshot_name: string,
     *     vendor_id: int|null,
     *     vendor_name: string|null,
     *     value: float,
     * }>  $lines
     * @return list<array{name: string, orders: int, revenue: int, share_pct: float}>
     */
    private function categories(array $lines, float $cohortRevenue): array
    {
        $links  = $this->categoryLinks($lines);
        $groups = [];

        foreach ($lines as $line) {
            $categories = [];

            if ($line['productable_type'] !== null && $line['productable_id'] !== null) {
                $productable = $line['productable_type'].':'.$line['productable_id'];

                foreach ($links[$productable] ?? [] as $category) {
                    $categories[$category['id']] = $category['name'];
                }
            }

            if ($line['product_id'] !== null) {
                foreach ($links[MorphTypeEnum::PRODUCT->value.':'.$line['product_id']] ?? [] as $category) {
                    $categories[$category['id']] = $category['name'];
                }
            }

            foreach ($categories as $id => $name) {
                $key = 'category:'.$id;

                $groups[$key] ??= ['name' => $name, 'order_ids' => [], 'revenue' => 0.0];
                $groups[$key]['order_ids'][$line['order_id']] = true;
                $groups[$key]['revenue'] += $line['value'];
            }
        }

        return $this->rankedRows($groups, self::CATEGORIES_LIMIT, $cohortRevenue);
    }

    /**
     * Category links of the productables and products referenced by the cohort, keyed `type:id`.
     *
     * @param  list<array{
     *     order_id: int,
     *     product_id: int|null,
     *     productable_type: string|null,
     *     productable_id: int|null,
     *     product_name: string|null,
     *     snapshot_name: string,
     *     vendor_id: int|null,
     *     vendor_name: string|null,
     *     value: float,
     * }>  $lines
     * @return array<string, list<array{id: int, name: string}>>
     */
    private function categoryLinks(array $lines): array
    {
        $idsByType = [];

        foreach ($lines as $line) {
            if ($line['productable_type'] !== null && $line['productable_id'] !== null) {
                $idsByType[$line['productable_type']][$line['productable_id']] = true;
            }

            if ($line['product_id'] !== null) {
                $idsByType[MorphTypeEnum::PRODUCT->value][$line['product_id']] = true;
            }
        }

        if ($idsByType === []) {
            return [];
        }

        $query = DB::table('categorizables')
            ->join('categories', 'categories.id', '=', 'categorizables.category_id')
            ->select([
                'categorizables.categorizable_type as categorizable_type',
                'categorizables.categorizable_id as categorizable_id',
                'categories.id as category_id',
                'categories.name as category_name',
            ])
            ->where(function (Builder $outer) use ($idsByType): void {
                foreach ($idsByType as $type => $ids) {
                    $outer->orWhere(function (Builder $inner) use ($type, $ids): void {
                        $inner->where('categorizables.categorizable_type', $type)
                            ->whereIn('categorizables.categorizable_id', array_keys($ids));
                    });
                }
            });

        $links = [];

        foreach ($query->get() as $row) {
            $links[$row->categorizable_type.':'.$row->categorizable_id][] = [
                'id'   => (int) $row->category_id,
                'name' => (string) $row->category_name,
            ];
        }

        return $links;
    }

    /**
     * Sort by revenue, then orders, then name; cap the list and share the cohort total.
     *
     * `share_pct` is one decimal of the cohort's total included line revenue, which is computed
     * before the cap and before category dedup, so the returned rows never renormalise to 100 %.
     *
     * @param  array<string, array{name: string, order_ids: array<int, true>, revenue: float}>  $groups
     * @return list<array{name: string, orders: int, revenue: int, share_pct: float}>
     */
    private function rankedRows(array $groups, int $limit, float $cohortRevenue): array
    {
        $rows = [];

        foreach ($groups as $group) {
            $rows[] = [
                'name'    => $group['name'],
                'orders'  => count($group['order_ids']),
                'revenue' => (int) round($group['revenue']),
            ];
        }

        usort($rows, static fn (array $first, array $second): int => [
            $second['revenue'], $second['orders'], $first['name'],
        ] <=> [
            $first['revenue'], $first['orders'], $second['name'],
        ]);

        $rows = array_slice($rows, 0, $limit);

        return array_map(static fn (array $row): array => [
            'name'      => $row['name'],
            'orders'    => $row['orders'],
            'revenue'   => $row['revenue'],
            'share_pct' => $cohortRevenue > 0
                ? round($row['revenue'] / $cohortRevenue * 100, 1)
                : 0.0,
        ], $rows);
    }

    private function normalizedName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $name = mb_trim($value);

        return $name === '' ? null : $name;
    }
}

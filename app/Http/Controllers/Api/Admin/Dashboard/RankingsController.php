<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Dashboard\DashboardPeriodQueryData;
use App\Data\Admin\Dashboard\DashboardRankingsData;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardBlockCache;
use App\Services\Dashboard\DashboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Dashboard
 *
 * @authenticated
 *
 * Computes the product, category and vendor rankings of the dashboard.
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
 * Category attribution follows the productable behind the product (`course`, `seminar`,
 * `digital_asset` or `bundle`) through `categorizables`, because that is where the catalogue
 * categories live. A line contributes its full value to every one of those categories, so category
 * revenue deliberately overlaps and must not be summed into a period total.
 *
 * A line with no vendor cannot be attributed and is omitted from the vendor leaderboard.
 */
final class RankingsController extends Controller
{
    private const int TOP_PRODUCTS_LIMIT = 6;

    private const int CATEGORIES_LIMIT = 6;

    private const int VENDORS_LIMIT = 4;

    /**
     * Get the top products, categories and vendors of the selected period.
     *
     * Revenue follows the order's paid ratio and is netted by line refunds. A Bundle Purchase
     * counts once as its Bundle product and belongs to the Bundle's vendor, so Bundle Component
     * Order Items never appear. Category revenue overlaps on purpose: a line counts in every
     * category of its productable, so category revenue must not be summed into a period total.
     *
     * @responseFile 200 resources/responses/admin/dashboard/rankings.json
     */
    public function __invoke(DashboardPeriodQueryData $query, DashboardBlockCache $cache): ApiResponseInterface
    {
        Gate::authorize(PermissionEnum::DASHBOARD_FINANCIALS->value);

        $period = DashboardPeriod::resolve($query->period);

        $block = $cache->remember(
            CacheKey::AdminDashboardRankings,
            ['period'    => $period->key()],
            fn (): array => $this->rankings($period->current()->start, $period->current()->end),
            $query->fresh,
        );

        return apiResponse()->success(DashboardRankingsData::from($block['payload']), metadata: $block['metadata']);
    }

    /**
     * @return array{
     *     top_products: list<array{name: string, orders: int, revenue: int, share_pct: float}>,
     *     categories: list<array{name: string, orders: int, revenue: int, share_pct: float}>,
     *     vendors: list<array{name: string, orders: int, revenue: int, share_pct: float}>,
     * }
     */
    private function rankings(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $lines = $this->rankingLines($start, $end);

        return [
            'top_products' => $this->products($lines),
            'categories'   => $this->categories($lines),
            'vendors'      => $this->vendors($lines),
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
    private function rankingLines(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $completedOrderPayments = DB::table('payments')
            ->selectRaw('order_id, SUM(amount) AS paid')
            ->where('status', PaymentStatusEnum::COMPLETED->value)
            ->where('purpose', PaymentPurposeEnum::ORDER->value)
            ->whereNotNull('order_id')
            ->groupBy('order_id');

        $rows = $this->standaloneLineQuery($start, $end, $completedOrderPayments)
            ->unionAll($this->bundleLineQuery($start, $end, $completedOrderPayments))
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
                'product_name'     => $this->name($row->product_name),
                'snapshot_name'    => (string) $row->snapshot_name,
                'vendor_id'        => $row->vendor_id === null ? null : (int) $row->vendor_id,
                'vendor_name'      => $this->name($row->vendor_name),
                'value'            => (int) $row->line_total * $ratio - (int) $row->line_refunded,
            ];
        }

        return $lines;
    }

    /**
     * Standalone order items: Bundle Component Order Items are excluded here.
     */
    private function standaloneLineQuery(
        CarbonImmutable $start,
        CarbonImmutable $end,
        Builder $completedOrderPayments,
    ): Builder {
        return DB::table('order_items as items')
            ->join('orders as orders', 'orders.id', '=', 'items.order_id')
            ->joinSub($completedOrderPayments, 'order_payments', 'order_payments.order_id', '=', 'orders.id')
            ->leftJoin('product_delivery_options as options', 'options.id', '=', 'items.product_delivery_option_id')
            ->leftJoin('products as products', 'products.id', '=', 'options.product_id')
            ->leftJoin('vendors as vendors', 'vendors.id', '=', 'items.vendor_id')
            ->whereNull('items.bundle_purchase_id')
            ->whereBetween('orders.created_at', [$start, $end])
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
        CarbonImmutable $start,
        CarbonImmutable $end,
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
            ->whereBetween('orders.created_at', [$start, $end])
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
    private function products(array $lines): array
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

        return $this->top($groups, self::TOP_PRODUCTS_LIMIT);
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
    private function vendors(array $lines): array
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

        return $this->top($groups, self::VENDORS_LIMIT);
    }

    /**
     * Attribute every line to each catalogue category of its productable, preserving overlap.
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
    private function categories(array $lines): array
    {
        $links  = $this->categoryLinks($lines);
        $groups = [];

        foreach ($lines as $line) {
            if ($line['productable_type'] === null || $line['productable_id'] === null) {
                continue;
            }

            $productable = $line['productable_type'].':'.$line['productable_id'];

            foreach ($links[$productable] ?? [] as $category) {
                $key = 'category:'.$category['id'];

                $groups[$key] ??= ['name' => $category['name'], 'order_ids' => [], 'revenue' => 0.0];
                $groups[$key]['order_ids'][$line['order_id']] = true;
                $groups[$key]['revenue'] += $line['value'];
            }
        }

        return $this->top($groups, self::CATEGORIES_LIMIT);
    }

    /**
     * Category links of the productables referenced by the cohort, keyed `type:id`.
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
            if ($line['productable_type'] === null || $line['productable_id'] === null) {
                continue;
            }

            $idsByType[$line['productable_type']][$line['productable_id']] = true;
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
     * Sort by revenue, then orders, then name; cap the list and compute its share of the capped total.
     *
     * `share_pct` is one decimal of the revenue included in this block (after the cap), not of the
     * whole cohort, so the returned rows always read as a self-contained ranking.
     *
     * @param  array<string, array{name: string, order_ids: array<int, true>, revenue: float}>  $groups
     * @return list<array{name: string, orders: int, revenue: int, share_pct: float}>
     */
    private function top(array $groups, int $limit): array
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

        $rows            = array_slice($rows, 0, $limit);
        $includedRevenue = array_sum(array_column($rows, 'revenue'));

        return array_map(static fn (array $row): array => [
            'name'      => $row['name'],
            'orders'    => $row['orders'],
            'revenue'   => $row['revenue'],
            'share_pct' => $includedRevenue > 0
                ? round($row['revenue'] / $includedRevenue * 100, 1)
                : 0.0,
        ], $rows);
    }

    private function name(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $name = mb_trim($value);

        return $name === '' ? null : $name;
    }
}

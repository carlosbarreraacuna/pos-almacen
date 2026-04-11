<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private function parseDateRange(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $to = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : Carbon::now()->endOfDay();

        return [$from, $to];
    }

    private function previousPeriod(Carbon $from, Carbon $to): array
    {
        $days     = (int) $from->diffInDays($to) + 1;
        $prevTo   = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();
        return [$prevFrom, $prevTo];
    }

    /**
     * KPIs con comparación vs período anterior.
     * GET /api/dashboard/summary
     */
    public function summary(Request $request): JsonResponse
    {
        [$from, $to]         = $this->parseDateRange($request);
        [$prevFrom, $prevTo] = $this->previousPeriod($from, $to);

        $current  = $this->periodKpis($from, $to);
        $previous = $this->periodKpis($prevFrom, $prevTo);

        $pct = function ($curr, $prev) {
            if ($prev > 0) return round((($curr - $prev) / $prev) * 100, 1);
            return $curr > 0 ? 100.0 : 0.0;
        };

        $wrap = fn($key) => [
            'current'  => $current[$key],
            'previous' => $previous[$key],
            'change'   => $pct($current[$key], $previous[$key]),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'period'      => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'prev_period' => ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString()],
                'revenue'       => $wrap('revenue'),
                'sales_count'   => $wrap('sales_count'),
                'avg_ticket'    => $wrap('avg_ticket'),
                'gross_profit'  => $wrap('gross_profit'),
                'discounts'     => $wrap('discounts'),
                'tax_collected' => $wrap('tax_collected'),
                'new_customers' => $wrap('new_customers'),
            ],
        ]);
    }

    private function periodKpis(Carbon $from, Carbon $to): array
    {
        // Base query — no usamos clone() del builder para evitar side-effects;
        // rehacemos la query cada vez con los mismos filtros.
        $base = fn() => Sale::whereBetween('sale_date', [$from, $to])
                            ->where('status', 'completed');

        $revenue      = (float) $base()->sum('total_amount');
        $salesCount   = (int)   $base()->count();
        $discounts    = (float) $base()->sum('discount_amount');
        $taxCollected = (float) $base()->sum('tax_amount');
        $avgTicket    = $salesCount > 0 ? round($revenue / $salesCount, 2) : 0.0;

        // Utilidad bruta: (precio_venta - costo) × cantidad para ventas del período
        $grossProfit = (float) DB::table('sale_items')
            ->join('sales',    'sale_items.sale_id',    '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw('COALESCE(SUM((sale_items.unit_price - products.cost_price) * sale_items.quantity), 0) as profit')
            ->value('profit') ?? 0;

        $newCustomers = (int) Customer::whereBetween('created_at', [$from, $to])->count();

        return [
            'revenue'       => $revenue,
            'sales_count'   => $salesCount,
            'avg_ticket'    => $avgTicket,
            'gross_profit'  => $grossProfit,
            'discounts'     => $discounts,
            'tax_collected' => $taxCollected,
            'new_customers' => $newCustomers,
        ];
    }

    /**
     * Tendencia de ventas por día/semana/mes.
     * GET /api/dashboard/sales-trend
     */
    public function salesTrend(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseDateRange($request);
        $days = (int) $from->diffInDays($to) + 1;

        $groupBy = $request->input('group_by');
        if (!in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = $days <= 31 ? 'day' : ($days <= 92 ? 'week' : 'month');
        }

        $format = match ($groupBy) {
            'week'  => 'IYYY"-W"IW',
            'month' => 'YYYY-MM',
            default => 'YYYY-MM-DD',
        };

        $rows = Sale::where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->selectRaw("TO_CHAR(sale_date, '{$format}') as period")
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('SUM(total_amount) as revenue')
            ->selectRaw('SUM(discount_amount) as discounts')
            ->groupByRaw("TO_CHAR(sale_date, '{$format}')")
            ->orderByRaw("TO_CHAR(sale_date, '{$format}') ASC")
            ->get();

        return response()->json([
            'success'  => true,
            'group_by' => $groupBy,
            'data'     => $rows,
        ]);
    }

    /**
     * Top productos más vendidos.
     * GET /api/dashboard/top-products
     */
    public function topProducts(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseDateRange($request);
        $limit = max(1, min(50, (int) $request->input('limit', 10)));

        $rows = DB::table('sale_items')
            ->join('sales',      'sale_items.sale_id',    '=', 'sales.id')
            ->join('products',   'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                products.image_url,
                products.stock_quantity as stock,
                products.min_stock_level,
                categories.name as category_name,
                SUM(sale_items.quantity) as units_sold,
                SUM(sale_items.total_amount) as revenue,
                COALESCE(SUM((sale_items.unit_price - products.cost_price) * sale_items.quantity), 0) as gross_profit
            ')
            ->groupBy(
                'products.id', 'products.name', 'products.sku',
                'products.image_url', 'products.stock_quantity',
                'products.min_stock_level', 'categories.name'
            )
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $totalRevenue = (float) DB::table('sales')
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->sum('total_amount');

        $data = $rows->map(function ($row) use ($totalRevenue) {
            $row->revenue_pct = $totalRevenue > 0
                ? round(($row->revenue / $totalRevenue) * 100, 1)
                : 0;
            $row->stock_status = $row->stock <= 0 ? 'out'
                : ($row->stock <= $row->min_stock_level ? 'low' : 'ok');
            return $row;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Top clientes por facturación.
     * GET /api/dashboard/top-customers
     */
    public function topCustomers(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseDateRange($request);
        $limit = max(1, min(50, (int) $request->input('limit', 10)));

        $rows = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->whereNotNull('sales.customer_id')
            ->selectRaw('
                customers.id,
                customers.name,
                customers.email,
                customers.phone,
                customers.customer_type,
                COUNT(sales.id) as purchases_count,
                SUM(sales.total_amount) as total_spent,
                AVG(sales.total_amount) as avg_ticket,
                MAX(sales.sale_date) as last_purchase
            ')
            ->groupBy(
                'customers.id', 'customers.name',
                'customers.email', 'customers.phone', 'customers.customer_type'
            )
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * Ventas por hora del día.
     * GET /api/dashboard/hourly
     */
    public function hourly(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseDateRange($request);

        $rows = Sale::where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->selectRaw('EXTRACT(HOUR FROM sale_date)::int as hour, COUNT(*) as sales_count, SUM(total_amount) as revenue')
            ->groupByRaw('EXTRACT(HOUR FROM sale_date)::int')
            ->orderByRaw('EXTRACT(HOUR FROM sale_date)::int ASC')
            ->get()
            ->keyBy('hour');

        $data = collect(range(0, 23))->map(function ($hour) use ($rows) {
            $row = $rows->get($hour);
            return [
                'hour'        => $hour,
                'label'       => sprintf('%02d:00', $hour),
                'sales_count' => $row ? (int)   $row->sales_count : 0,
                'revenue'     => $row ? (float) $row->revenue     : 0.0,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Ingresos por categoría.
     * GET /api/dashboard/by-category
     */
    public function byCategory(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseDateRange($request);

        $rows = DB::table('sale_items')
            ->join('sales',    'sale_items.sale_id',    '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw("
                COALESCE(categories.name, 'Sin categoría') as category_name,
                SUM(sale_items.quantity) as units_sold,
                SUM(sale_items.total_amount) as revenue
            ")
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * Salud del inventario.
     * GET /api/dashboard/inventory-health
     */
    public function inventoryHealth(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseDateRange($request);

        $totalValue = (float) DB::table('products')
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock_quantity * cost_price), 0) as value')
            ->value('value');

        $totalProducts = (int) DB::table('products')->where('is_active', true)->count();

        $outOfStock = (int) DB::table('products')
            ->where('is_active', true)
            ->where('stock_quantity', '<=', 0)
            ->count();

        $lowStock = (int) DB::table('products')
            ->where('is_active', true)
            ->whereRaw('stock_quantity <= min_stock_level')
            ->where('stock_quantity', '>', 0)
            ->count();

        $normalStock = max(0, $totalProducts - $outOfStock - $lowStock);

        // Entradas: compras y devoluciones
        $stockIn = (float) DB::table('stock_movements')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('type', ['purchase', 'return'])
            ->selectRaw('COALESCE(SUM(quantity), 0) as qty')
            ->value('qty');

        // Salidas: ventas, daños, pérdidas, transferencias
        $stockOut = (float) DB::table('stock_movements')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('type', ['sale', 'damage', 'loss', 'transfer'])
            ->selectRaw('COALESCE(SUM(quantity), 0) as qty')
            ->value('qty');

        // Productos activos con stock pero sin ventas en el período
        $noSalesInPeriod = (int) DB::table('products')
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->whereNotIn('id', function ($sub) use ($from, $to) {
                $sub->select('sale_items.product_id')
                    ->from('sale_items')
                    ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                    ->where('sales.status', 'completed')
                    ->whereBetween('sales.sale_date', [$from, $to]);
            })
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_value'        => $totalValue,
                'total_products'     => $totalProducts,
                'normal_stock'       => $normalStock,
                'low_stock'          => $lowStock,
                'out_of_stock'       => $outOfStock,
                'stock_in_period'    => $stockIn,
                'stock_out_period'   => $stockOut,
                'no_sales_in_period' => $noSalesInPeriod,
            ],
        ]);
    }
}

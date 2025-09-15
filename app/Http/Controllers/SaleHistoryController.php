<?php

namespace App\Http\Controllers;

use App\Models\SaleHistory;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class SaleHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SaleHistory::with(['customer', 'product']);

        // Filtros
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->get('product_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('last_purchase_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('last_purchase_date', '<=', $request->get('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('document_number', 'like', "%{$search}%");
                })->orWhereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'last_purchase_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $histories = $query->paginate($perPage);

        // Agregar atributos calculados
        $histories->getCollection()->transform(function ($history) {
            $history->suggested_quantity = $history->suggested_quantity;
            $history->repurchase_time = $history->repurchase_time;
            return $history;
        });

        return response()->json($histories);
    }

    /**
     * Display the specified resource.
     */
    public function show(SaleHistory $saleHistory): JsonResponse
    {
        $saleHistory->load(['customer', 'product']);
        $saleHistory->suggested_quantity = $saleHistory->suggested_quantity;
        $saleHistory->repurchase_time = $saleHistory->repurchase_time;
        
        return response()->json($saleHistory);
    }

    /**
     * Obtener recomendaciones para un cliente
     */
    public function customerRecommendations(Request $request, Customer $customer): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $recommendations = SaleHistory::getRecommendations($customer->id, $limit);
        
        return response()->json([
            'customer' => $customer,
            'recommendations' => $recommendations
        ]);
    }

    /**
     * Obtener productos que necesitan reabastecimiento próximo
     */
    public function upcomingNeeds(Request $request): JsonResponse
    {
        $days = $request->get('days', 30); // Próximos 30 días por defecto
        $limit = $request->get('limit', 20);
        
        $upcomingNeeds = SaleHistory::getUpcomingNeeds($days, $limit);
        
        return response()->json($upcomingNeeds);
    }

    /**
     * Obtener clientes frecuentes
     */
    public function frequentCustomers(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $minPurchases = $request->get('min_purchases', 5);
        
        $frequentCustomers = SaleHistory::with(['customer', 'product'])
            ->frequentPurchases($minPurchases)
            ->select('customer_id')
            ->selectRaw('COUNT(*) as products_count')
            ->selectRaw('SUM(total_quantity) as total_quantity')
            ->selectRaw('SUM(total_amount) as total_amount')
            ->selectRaw('AVG(average_quantity) as avg_quantity')
            ->selectRaw('MAX(last_purchase_date) as last_purchase')
            ->groupBy('customer_id')
            ->orderBy('total_amount', 'desc')
            ->limit($limit)
            ->get();
        
        return response()->json($frequentCustomers);
    }

    /**
     * Obtener productos más vendidos por historial
     */
    public function topProducts(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $dateFrom = $request->get('date_from', now()->subMonths(3));
        $dateTo = $request->get('date_to', now());
        
        $topProducts = SaleHistory::with(['product'])
            ->whereBetween('last_purchase_date', [$dateFrom, $dateTo])
            ->select('product_id')
            ->selectRaw('COUNT(DISTINCT customer_id) as unique_customers')
            ->selectRaw('SUM(total_quantity) as total_sold')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->selectRaw('AVG(average_quantity) as avg_quantity_per_customer')
            ->selectRaw('MAX(last_purchase_date) as last_sale')
            ->groupBy('product_id')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();
        
        return response()->json($topProducts);
    }

    /**
     * Obtener análisis de patrones de compra
     */
    public function purchasePatterns(Request $request): JsonResponse
    {
        $customerId = $request->get('customer_id');
        $productId = $request->get('product_id');
        
        $query = SaleHistory::with(['customer', 'product']);
        
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        if ($productId) {
            $query->where('product_id', $productId);
        }
        
        $patterns = $query->get()->map(function ($history) {
            return [
                'customer_id' => $history->customer_id,
                'customer_name' => $history->customer->name,
                'product_id' => $history->product_id,
                'product_name' => $history->product->name,
                'purchase_frequency' => $history->purchase_frequency,
                'average_quantity' => $history->average_quantity,
                'total_purchases' => $history->total_purchases,
                'last_purchase_date' => $history->last_purchase_date,
                'suggested_quantity' => $history->suggested_quantity,
                'repurchase_time' => $history->repurchase_time,
                'pattern_analysis' => [
                    'is_regular_customer' => $history->purchase_frequency <= 30,
                    'high_volume_buyer' => $history->average_quantity > 5,
                    'recent_activity' => $history->last_purchase_date->diffInDays(now()) <= 30,
                    'loyalty_score' => min(100, ($history->total_purchases * 10) + (100 - $history->last_purchase_date->diffInDays(now())))
                ]
            ];
        });
        
        return response()->json($patterns);
    }

    /**
     * Obtener estadísticas generales del historial
     */
    public function statistics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now()->endOfMonth());
        
        $stats = [
            'total_customer_product_combinations' => SaleHistory::whereBetween('last_purchase_date', [$dateFrom, $dateTo])->count(),
            'unique_customers' => SaleHistory::whereBetween('last_purchase_date', [$dateFrom, $dateTo])
                ->distinct('customer_id')->count('customer_id'),
            'unique_products' => SaleHistory::whereBetween('last_purchase_date', [$dateFrom, $dateTo])
                ->distinct('product_id')->count('product_id'),
            'total_quantity_sold' => SaleHistory::whereBetween('last_purchase_date', [$dateFrom, $dateTo])
                ->sum('total_quantity'),
            'total_revenue' => SaleHistory::whereBetween('last_purchase_date', [$dateFrom, $dateTo])
                ->sum('total_amount'),
            'average_purchase_frequency' => SaleHistory::whereBetween('last_purchase_date', [$dateFrom, $dateTo])
                ->avg('purchase_frequency'),
            'frequent_customers_count' => SaleHistory::whereBetween('last_purchase_date', [$dateFrom, $dateTo])
                ->frequentPurchases(5)->count(),
            'recent_customers_count' => SaleHistory::whereBetween('last_purchase_date', [$dateFrom, $dateTo])
                ->recentPurchases(30)->count(),
            'regular_customers_count' => SaleHistory::whereBetween('last_purchase_date', [$dateFrom, $dateTo])
                ->regularPurchases(30)->count()
        ];
        
        return response()->json($stats);
    }

    /**
     * Actualizar historial desde una venta específica
     */
    public function updateFromSale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id'
        ]);
        
        try {
            $updated = SaleHistory::updateFromSale($validated['sale_id']);
            
            return response()->json([
                'message' => 'Historial actualizado exitosamente',
                'updated_records' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar historial',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar historial antiguo
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'months_old' => 'required|integer|min:1|max:60'
        ]);
        
        $cutoffDate = now()->subMonths($validated['months_old']);
        
        $deletedCount = SaleHistory::where('last_purchase_date', '<', $cutoffDate)
            ->where('total_purchases', '<', 2) // Solo eliminar registros con pocas compras
            ->delete();
        
        return response()->json([
            'message' => 'Limpieza de historial completada',
            'deleted_records' => $deletedCount,
            'cutoff_date' => $cutoffDate
        ]);
    }

    /**
     * Exportar datos de historial
     */
    public function export(Request $request): JsonResponse
    {
        $format = $request->get('format', 'json'); // json, csv
        $customerId = $request->get('customer_id');
        $productId = $request->get('product_id');
        
        $query = SaleHistory::with(['customer', 'product']);
        
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        if ($productId) {
            $query->where('product_id', $productId);
        }
        
        $data = $query->get()->map(function ($history) {
            return [
                'customer_name' => $history->customer->name,
                'customer_document' => $history->customer->document_number,
                'product_name' => $history->product->name,
                'product_code' => $history->product->code,
                'total_purchases' => $history->total_purchases,
                'total_quantity' => $history->total_quantity,
                'total_amount' => $history->total_amount,
                'average_quantity' => $history->average_quantity,
                'purchase_frequency' => $history->purchase_frequency,
                'last_purchase_date' => $history->last_purchase_date->format('Y-m-d'),
                'suggested_quantity' => $history->suggested_quantity,
                'repurchase_time' => $history->repurchase_time
            ];
        });
        
        return response()->json([
            'format' => $format,
            'total_records' => $data->count(),
            'data' => $data
        ]);
    }
}

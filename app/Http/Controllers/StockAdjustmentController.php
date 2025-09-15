<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Warehouse;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Requests\UpdateStockAdjustmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class StockAdjustmentController extends Controller
{
    /**
     * Listar ajustes de stock
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockAdjustment::with([
            'warehouse:id,name,code',
            'location:id,name,code',
            'user:id,name',
            'approver:id,name'
        ]);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('adjustment_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('adjustment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('adjustment_date', '<=', $request->date_to);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'adjustment_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $adjustments = $query->paginate($perPage);

        return response()->json($adjustments);
    }

    /**
     * Crear nuevo ajuste
     */
    public function store(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'location_id' => 'nullable|exists:locations,id',
            'adjustment_date' => 'required|date',
            'type' => 'required|in:increase,decrease,recount,damage,expiry,theft,correction',
            'reason' => 'required|in:physical_count,damaged_goods,expired_goods,theft_loss,system_error,supplier_error,found_goods,other',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.current_quantity' => 'required|integer|min:0',
            'items.*.adjusted_quantity' => 'required|integer|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Crear ajuste
            $adjustment = StockAdjustment::create([
                'warehouse_id' => $validated['warehouse_id'],
                'location_id' => $validated['location_id'] ?? null,
                'user_id' => $request->user()->id,
                'adjustment_date' => $validated['adjustment_date'],
                'type' => $validated['type'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'status' => StockAdjustment::STATUS_DRAFT
            ]);

            // Crear items de ajuste
            foreach ($validated['items'] as $itemData) {
                // Obtener costo del producto si no se proporciona
                if (!isset($itemData['unit_cost'])) {
                    $product = Product::find($itemData['product_id']);
                    $itemData['unit_cost'] = $product->cost ?? 0;
                }

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $itemData['product_id'],
                    'current_quantity' => $itemData['current_quantity'],
                    'adjusted_quantity' => $itemData['adjusted_quantity'],
                    'unit_cost' => $itemData['unit_cost'],
                    'reason' => $itemData['reason'] ?? null,
                    'notes' => $itemData['notes'] ?? null
                ]);
            }

            $adjustment->load([
                'warehouse',
                'location',
                'items.product'
            ]);

            return response()->json([
                'message' => 'Ajuste creado exitosamente',
                'adjustment' => $adjustment
            ], 201);
        });
    }

    /**
     * Mostrar ajuste específico
     */
    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        $stockAdjustment->load([
            'warehouse',
            'location',
            'user',
            'approver',
            'items.product'
        ]);

        return response()->json($stockAdjustment);
    }

    /**
     * Actualizar ajuste (solo en estado draft o pending)
     */
    public function update(UpdateStockAdjustmentRequest $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        if (!in_array($stockAdjustment->status, [StockAdjustment::STATUS_DRAFT, StockAdjustment::STATUS_PENDING])) {
            return response()->json([
                'message' => 'Solo se pueden editar ajustes en estado borrador o pendiente'
            ], 422);
        }

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'location_id' => 'nullable|exists:locations,id',
            'adjustment_date' => 'required|date',
            'type' => 'required|in:increase,decrease,recount,damage,expiry,theft,correction',
            'reason' => 'required|in:physical_count,damaged_goods,expired_goods,theft_loss,system_error,supplier_error,found_goods,other',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.current_quantity' => 'required|integer|min:0',
            'items.*.adjusted_quantity' => 'required|integer|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated, $stockAdjustment) {
            // Actualizar ajuste
            $stockAdjustment->update([
                'warehouse_id' => $validated['warehouse_id'],
                'location_id' => $validated['location_id'] ?? null,
                'adjustment_date' => $validated['adjustment_date'],
                'type' => $validated['type'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null
            ]);

            // Eliminar items existentes
            $stockAdjustment->items()->delete();

            // Crear nuevos items
            foreach ($validated['items'] as $itemData) {
                if (!isset($itemData['unit_cost'])) {
                    $product = Product::find($itemData['product_id']);
                    $itemData['unit_cost'] = $product->cost ?? 0;
                }

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $stockAdjustment->id,
                    'product_id' => $itemData['product_id'],
                    'current_quantity' => $itemData['current_quantity'],
                    'adjusted_quantity' => $itemData['adjusted_quantity'],
                    'unit_cost' => $itemData['unit_cost'],
                    'reason' => $itemData['reason'] ?? null,
                    'notes' => $itemData['notes'] ?? null
                ]);
            }

            $stockAdjustment->load(['warehouse', 'location', 'items.product']);

            return response()->json([
                'message' => 'Ajuste actualizado exitosamente',
                'adjustment' => $stockAdjustment
            ]);
        });
    }

    /**
     * Enviar ajuste para aprobación
     */
    public function submit(StockAdjustment $stockAdjustment): JsonResponse
    {
        if ($stockAdjustment->status !== StockAdjustment::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Solo se pueden enviar ajustes en estado borrador'
            ], 422);
        }

        $stockAdjustment->status = StockAdjustment::STATUS_PENDING;
        $stockAdjustment->save();

        return response()->json([
            'message' => 'Ajuste enviado para aprobación exitosamente',
            'adjustment' => $stockAdjustment
        ]);
    }

    /**
     * Aprobar ajuste
     */
    public function approve(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        if (!$stockAdjustment->approve($request->user())) {
            return response()->json([
                'message' => 'No se puede aprobar el ajuste en su estado actual'
            ], 422);
        }

        return response()->json([
            'message' => 'Ajuste aprobado exitosamente',
            'adjustment' => $stockAdjustment->fresh(['warehouse', 'location', 'approver'])
        ]);
    }

    /**
     * Aplicar ajuste al inventario
     */
    public function apply(StockAdjustment $stockAdjustment): JsonResponse
    {
        if (!$stockAdjustment->apply()) {
            return response()->json([
                'message' => 'No se puede aplicar el ajuste en su estado actual'
            ], 422);
        }

        return response()->json([
            'message' => 'Ajuste aplicado exitosamente al inventario',
            'adjustment' => $stockAdjustment->fresh()
        ]);
    }

    /**
     * Cancelar ajuste
     */
    public function cancel(StockAdjustment $stockAdjustment): JsonResponse
    {
        if (!$stockAdjustment->cancel()) {
            return response()->json([
                'message' => 'No se puede cancelar el ajuste en su estado actual'
            ], 422);
        }

        return response()->json([
            'message' => 'Ajuste cancelado exitosamente',
            'adjustment' => $stockAdjustment->fresh()
        ]);
    }

    /**
     * Eliminar ajuste (solo en estado draft)
     */
    public function destroy(StockAdjustment $stockAdjustment): JsonResponse
    {
        if ($stockAdjustment->status !== StockAdjustment::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Solo se pueden eliminar ajustes en estado borrador'
            ], 422);
        }

        $stockAdjustment->delete();

        return response()->json([
            'message' => 'Ajuste eliminado exitosamente'
        ]);
    }

    /**
     * Obtener stock actual de productos para un almacén/ubicación
     */
    public function getCurrentStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'location_id' => 'nullable|exists:locations,id',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id'
        ]);

        $stocks = [];
        
        foreach ($validated['product_ids'] as $productId) {
            $query = ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $validated['warehouse_id']);
            
            if ($validated['location_id']) {
                $query->where('location_id', $validated['location_id']);
            }
            
            $currentStock = $query->sum('quantity');
            $product = Product::find($productId);
            
            $stocks[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'current_quantity' => $currentStock,
                'unit_cost' => $product->cost ?? 0
            ];
        }

        return response()->json($stocks);
    }

    /**
     * Obtener estadísticas de ajustes
     */
    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now()->endOfMonth());

        $stats = [
            'total_adjustments' => StockAdjustment::byDateRange($dateFrom, $dateTo)->count(),
            'pending_adjustments' => StockAdjustment::pending()->byDateRange($dateFrom, $dateTo)->count(),
            'approved_adjustments' => StockAdjustment::approved()->byDateRange($dateFrom, $dateTo)->count(),
            'applied_adjustments' => StockAdjustment::applied()->byDateRange($dateFrom, $dateTo)->count(),
            'cancelled_adjustments' => StockAdjustment::where('status', 'cancelled')->byDateRange($dateFrom, $dateTo)->count(),
            'total_value_adjustment' => StockAdjustment::byDateRange($dateFrom, $dateTo)->sum('total_value_adjustment'),
            'total_items_adjusted' => StockAdjustment::byDateRange($dateFrom, $dateTo)->sum('total_items'),
            'by_type' => StockAdjustment::selectRaw('type, COUNT(*) as count')
                ->byDateRange($dateFrom, $dateTo)
                ->groupBy('type')
                ->pluck('count', 'type'),
            'by_reason' => StockAdjustment::selectRaw('reason, COUNT(*) as count')
                ->byDateRange($dateFrom, $dateTo)
                ->groupBy('reason')
                ->pluck('count', 'reason'),
            'positive_adjustments' => StockAdjustment::where('total_value_adjustment', '>', 0)
                ->byDateRange($dateFrom, $dateTo)->count(),
            'negative_adjustments' => StockAdjustment::where('total_value_adjustment', '<', 0)
                ->byDateRange($dateFrom, $dateTo)->count()
        ];

        return response()->json($stats);
    }

    /**
     * Obtener tipos disponibles
     */
    public function getTypes(): JsonResponse
    {
        $types = StockAdjustment::getTypes();
        
        return response()->json($types);
    }

    /**
     * Obtener razones disponibles
     */
    public function getReasons(): JsonResponse
    {
        $reasons = StockAdjustment::getReasons();
        
        return response()->json($reasons);
    }

    /**
     * Obtener estados disponibles
     */
    public function getStatuses(): JsonResponse
    {
        $statuses = StockAdjustment::getStatuses();
        
        return response()->json($statuses);
    }

    /**
     * Generar ajuste basado en conteo físico
     */
    public function generateFromPhysicalCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'location_id' => 'nullable|exists:locations,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.counted_quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string'
        ]);

        $adjustmentItems = [];
        
        foreach ($validated['products'] as $productData) {
            // Obtener stock actual
            $query = ProductStock::where('product_id', $productData['product_id'])
                ->where('warehouse_id', $validated['warehouse_id']);
            
            if ($validated['location_id']) {
                $query->where('location_id', $validated['location_id']);
            }
            
            $currentStock = $query->sum('quantity');
            $countedQuantity = $productData['counted_quantity'];
            
            // Solo incluir si hay diferencia
            if ($currentStock != $countedQuantity) {
                $product = Product::find($productData['product_id']);
                
                $adjustmentItems[] = [
                    'product_id' => $productData['product_id'],
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'current_quantity' => $currentStock,
                    'adjusted_quantity' => $countedQuantity,
                    'quantity_adjustment' => $countedQuantity - $currentStock,
                    'unit_cost' => $product->cost ?? 0,
                    'value_adjustment' => ($countedQuantity - $currentStock) * ($product->cost ?? 0)
                ];
            }
        }

        if (empty($adjustmentItems)) {
            return response()->json([
                'message' => 'No se encontraron diferencias entre el stock actual y el conteo físico',
                'items' => []
            ]);
        }

        return response()->json([
            'message' => 'Ajuste generado basado en conteo físico',
            'items' => $adjustmentItems,
            'total_items' => count($adjustmentItems),
            'total_value_adjustment' => array_sum(array_column($adjustmentItems, 'value_adjustment'))
        ]);
    }
}
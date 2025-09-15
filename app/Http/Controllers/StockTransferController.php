<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Http\Requests\StoreStockTransferRequest;
use App\Http\Requests\UpdateStockTransferRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    /**
     * Listar transferencias de stock
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::with([
            'fromWarehouse:id,name,code',
            'toWarehouse:id,name,code',
            'fromLocation:id,name,code',
            'toLocation:id,name,code',
            'user:id,name',
            'approver:id,name',
            'receiver:id,name'
        ]);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transfer_number', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->from_warehouse_id);
        }

        if ($request->filled('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->to_warehouse_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transfer_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transfer_date', '<=', $request->date_to);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'transfer_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $transfers = $query->paginate($perPage);

        return response()->json($transfers);
    }

    /**
     * Crear nueva transferencia
     */
    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'from_location_id' => 'nullable|exists:locations,id',
            'to_location_id' => 'nullable|exists:locations,id',
            'transfer_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:transfer_date',
            'type' => 'required|in:internal,external,emergency,rebalance',
            'priority' => 'required|in:low,normal,high,urgent',
            'carrier' => 'nullable|string|max:255',
            'shipping_method' => 'nullable|string|max:255',
            'shipping_cost' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Crear transferencia
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'from_location_id' => $validated['from_location_id'] ?? null,
                'to_location_id' => $validated['to_location_id'] ?? null,
                'user_id' => $request->user()->id,
                'transfer_date' => $validated['transfer_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'type' => $validated['type'],
                'priority' => $validated['priority'],
                'carrier' => $validated['carrier'] ?? null,
                'shipping_method' => $validated['shipping_method'] ?? null,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => StockTransfer::STATUS_PENDING
            ]);

            // Crear items de transferencia
            $totalQuantity = 0;
            $totalValue = 0;

            foreach ($validated['items'] as $itemData) {
                // Verificar stock disponible
                $availableStock = ProductStock::where('product_id', $itemData['product_id'])
                    ->where('warehouse_id', $validated['from_warehouse_id'])
                    ->when($validated['from_location_id'], function ($q) use ($validated) {
                        return $q->where('location_id', $validated['from_location_id']);
                    })
                    ->sum('quantity');

                if ($availableStock < $itemData['quantity']) {
                    $product = Product::find($itemData['product_id']);
                    throw new \Exception("Stock insuficiente para el producto {$product->name}. Disponible: {$availableStock}, Solicitado: {$itemData['quantity']}");
                }

                $item = StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null
                ]);

                $totalQuantity += $itemData['quantity'];
                
                // Calcular valor basado en costo del producto
                $product = Product::find($itemData['product_id']);
                $totalValue += $itemData['quantity'] * ($product->cost ?? 0);
            }

            // Actualizar totales
            $transfer->update([
                'total_items' => count($validated['items']),
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue
            ]);

            $transfer->load([
                'fromWarehouse',
                'toWarehouse',
                'fromLocation',
                'toLocation',
                'items.product'
            ]);

            return response()->json([
                'message' => 'Transferencia creada exitosamente',
                'transfer' => $transfer
            ], 201);
        });
    }

    /**
     * Mostrar transferencia específica
     */
    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->load([
            'fromWarehouse',
            'toWarehouse',
            'fromLocation',
            'toLocation',
            'user',
            'approver',
            'receiver',
            'items.product'
        ]);

        return response()->json($stockTransfer);
    }

    /**
     * Actualizar transferencia (solo en estado pending)
     */
    public function update(UpdateStockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        if ($stockTransfer->status !== StockTransfer::STATUS_PENDING) {
            return response()->json([
                'message' => 'Solo se pueden editar transferencias en estado pendiente'
            ], 422);
        }

        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'from_location_id' => 'nullable|exists:locations,id',
            'to_location_id' => 'nullable|exists:locations,id',
            'transfer_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:transfer_date',
            'type' => 'required|in:internal,external,emergency,rebalance',
            'priority' => 'required|in:low,normal,high,urgent',
            'carrier' => 'nullable|string|max:255',
            'shipping_method' => 'nullable|string|max:255',
            'shipping_cost' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated, $stockTransfer) {
            // Actualizar transferencia
            $stockTransfer->update([
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'from_location_id' => $validated['from_location_id'] ?? null,
                'to_location_id' => $validated['to_location_id'] ?? null,
                'transfer_date' => $validated['transfer_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'type' => $validated['type'],
                'priority' => $validated['priority'],
                'carrier' => $validated['carrier'] ?? null,
                'shipping_method' => $validated['shipping_method'] ?? null,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null
            ]);

            // Eliminar items existentes
            $stockTransfer->items()->delete();

            // Crear nuevos items
            $totalQuantity = 0;
            $totalValue = 0;

            foreach ($validated['items'] as $itemData) {
                StockTransferItem::create([
                    'stock_transfer_id' => $stockTransfer->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null
                ]);

                $totalQuantity += $itemData['quantity'];
                
                $product = Product::find($itemData['product_id']);
                $totalValue += $itemData['quantity'] * ($product->cost ?? 0);
            }

            // Actualizar totales
            $stockTransfer->update([
                'total_items' => count($validated['items']),
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue
            ]);

            $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items.product']);

            return response()->json([
                'message' => 'Transferencia actualizada exitosamente',
                'transfer' => $stockTransfer
            ]);
        });
    }

    /**
     * Aprobar transferencia
     */
    public function approve(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if (!$stockTransfer->approve($request->user())) {
            return response()->json([
                'message' => 'No se puede aprobar la transferencia en su estado actual'
            ], 422);
        }

        return response()->json([
            'message' => 'Transferencia aprobada exitosamente',
            'transfer' => $stockTransfer->fresh(['fromWarehouse', 'toWarehouse', 'approver'])
        ]);
    }

    /**
     * Iniciar transferencia (cambiar a en tránsito)
     */
    public function startTransfer(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => 'nullable|string|max:255'
        ]);

        if (!$stockTransfer->startTransfer($validated['tracking_number'] ?? null)) {
            return response()->json([
                'message' => 'No se puede iniciar la transferencia en su estado actual'
            ], 422);
        }

        return response()->json([
            'message' => 'Transferencia iniciada exitosamente',
            'transfer' => $stockTransfer->fresh()
        ]);
    }

    /**
     * Completar transferencia
     */
    public function complete(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $validated = $request->validate([
            'receiving_notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:stock_transfer_items,id',
            'items.*.quantity_received' => 'required|integer|min:0'
        ]);

        return DB::transaction(function () use ($validated, $request, $stockTransfer) {
            // Actualizar cantidades recibidas
            foreach ($validated['items'] as $itemData) {
                $item = StockTransferItem::find($itemData['id']);
                $item->quantity_received = $itemData['quantity_received'];
                $item->save();
            }

            if (!$stockTransfer->complete($request->user(), $validated['receiving_notes'] ?? null)) {
                throw new \Exception('No se puede completar la transferencia en su estado actual');
            }

            return response()->json([
                'message' => 'Transferencia completada exitosamente',
                'transfer' => $stockTransfer->fresh(['fromWarehouse', 'toWarehouse', 'receiver'])
            ]);
        });
    }

    /**
     * Cancelar transferencia
     */
    public function cancel(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if (!$stockTransfer->cancel($validated['reason'])) {
            return response()->json([
                'message' => 'No se puede cancelar la transferencia en su estado actual'
            ], 422);
        }

        return response()->json([
            'message' => 'Transferencia cancelada exitosamente',
            'transfer' => $stockTransfer->fresh()
        ]);
    }

    /**
     * Obtener estadísticas de transferencias
     */
    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now()->endOfMonth());

        $stats = [
            'total_transfers' => StockTransfer::byDateRange($dateFrom, $dateTo)->count(),
            'pending_transfers' => StockTransfer::pending()->byDateRange($dateFrom, $dateTo)->count(),
            'in_transit_transfers' => StockTransfer::inTransit()->byDateRange($dateFrom, $dateTo)->count(),
            'completed_transfers' => StockTransfer::completed()->byDateRange($dateFrom, $dateTo)->count(),
            'cancelled_transfers' => StockTransfer::where('status', 'cancelled')->byDateRange($dateFrom, $dateTo)->count(),
            'total_value' => StockTransfer::byDateRange($dateFrom, $dateTo)->sum('total_value'),
            'total_items' => StockTransfer::byDateRange($dateFrom, $dateTo)->sum('total_items'),
            'by_type' => StockTransfer::selectRaw('type, COUNT(*) as count')
                ->byDateRange($dateFrom, $dateTo)
                ->groupBy('type')
                ->pluck('count', 'type'),
            'by_priority' => StockTransfer::selectRaw('priority, COUNT(*) as count')
                ->byDateRange($dateFrom, $dateTo)
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'overdue_transfers' => StockTransfer::where('expected_date', '<', now())
                ->whereIn('status', ['pending', 'approved', 'in_transit'])
                ->count()
        ];

        return response()->json($stats);
    }

    /**
     * Obtener estados disponibles
     */
    public function getStatuses(): JsonResponse
    {
        $statuses = StockTransfer::getStatuses();
        
        return response()->json($statuses);
    }

    /**
     * Obtener tipos disponibles
     */
    public function getTypes(): JsonResponse
    {
        $types = StockTransfer::getTypes();
        
        return response()->json($types);
    }

    /**
     * Obtener prioridades disponibles
     */
    public function getPriorities(): JsonResponse
    {
        $priorities = StockTransfer::getPriorities();
        
        return response()->json($priorities);
    }
}
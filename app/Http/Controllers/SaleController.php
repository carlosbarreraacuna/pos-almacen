<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Customer;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Listar todas las ventas
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer', 'user', 'saleItems.product', 'payments']);

        // Filtros
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        if ($request->has('today') && $request->boolean('today')) {
            $query->today();
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $sales = $query->paginate($perPage);

        return response()->json($sales);
    }

    /**
     * Crear una nueva venta
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'payment_method' => 'required|in:cash,card,transfer,check,credit',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            // Verificar stock disponible
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para el producto: {$product->name}. Stock disponible: {$product->stock}"
                    ]);
                }
            }

            // Crear la venta
            $sale = Sale::create([
                'customer_id' => $validated['customer_id'],
                'user_id' => auth()->id(),
                'sale_date' => now(),
                'payment_method' => $validated['payment_method'],
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'notes' => $validated['notes'] ?? null
            ]);

            // Crear los items de venta
            foreach ($validated['items'] as $item) {
                $sale->saleItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_rate' => $item['tax_rate'] ?? 0
                ]);

                // Actualizar stock
                $product = Product::find($item['product_id']);
                $product->decrement('stock', $item['quantity']);

                // Registrar movimiento de stock
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'type' => 'out',
                    'quantity' => $item['quantity'],
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'user_id' => auth()->id(),
                    'notes' => "Venta #{$sale->sale_number}"
                ]);
            }

            // Calcular totales
            $sale->calculateTotals();
            $sale->save();

            // Si es pago en efectivo o tarjeta, completar la venta
            if (in_array($validated['payment_method'], ['cash', 'card'])) {
                $sale->complete();
                
                // Crear el pago
                $sale->payments()->create([
                    'payment_method' => $validated['payment_method'],
                    'amount' => $sale->total_amount,
                    'payment_date' => now(),
                    'user_id' => auth()->id(),
                    'status' => 'completed'
                ]);
            }

            DB::commit();

            $sale->load(['customer', 'user', 'saleItems.product', 'payments']);

            return response()->json([
                'message' => 'Venta creada exitosamente',
                'sale' => $sale
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mostrar una venta específica
     */
    public function show(Sale $sale): JsonResponse
    {
        $sale->load([
            'customer',
            'user',
            'saleItems.product',
            'payments'
        ]);

        return response()->json($sale);
    }

    /**
     * Actualizar una venta (solo si está en borrador)
     */
    public function update(Request $request, Sale $sale): JsonResponse
    {
        if ($sale->status !== 'draft') {
            return response()->json([
                'message' => 'Solo se pueden editar ventas en borrador'
            ], 422);
        }

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method' => 'required|in:cash,card,transfer,check,credit',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        $sale->update($validated);
        $sale->calculateTotals();
        $sale->save();

        $sale->load(['customer', 'user', 'saleItems.product', 'payments']);

        return response()->json([
            'message' => 'Venta actualizada exitosamente',
            'sale' => $sale
        ]);
    }

    /**
     * Completar una venta
     */
    public function complete(Sale $sale): JsonResponse
    {
        if ($sale->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden completar ventas pendientes'
            ], 422);
        }

        $sale->complete();

        return response()->json([
            'message' => 'Venta completada exitosamente',
            'sale' => $sale
        ]);
    }

    /**
     * Cancelar una venta
     */
    public function cancel(Sale $sale): JsonResponse
    {
        if (!in_array($sale->status, ['draft', 'pending'])) {
            return response()->json([
                'message' => 'Solo se pueden cancelar ventas en borrador o pendientes'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Restaurar stock si la venta ya había afectado el inventario
            if ($sale->status === 'pending') {
                foreach ($sale->saleItems as $item) {
                    $product = Product::find($item->product_id);
                    $product->increment('stock', $item->quantity);

                    // Registrar movimiento de stock
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'type' => 'in',
                        'quantity' => $item->quantity,
                        'reference_type' => 'sale_cancellation',
                        'reference_id' => $sale->id,
                        'user_id' => auth()->id(),
                        'notes' => "Cancelación de venta #{$sale->sale_number}"
                    ]);
                }
            }

            $sale->cancel();

            DB::commit();

            return response()->json([
                'message' => 'Venta cancelada exitosamente',
                'sale' => $sale
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de ventas
     */
    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now()->endOfMonth());

        $stats = [
            'total_sales' => Sale::completed()->byDateRange($dateFrom, $dateTo)->sum('total_amount'),
            'sales_count' => Sale::completed()->byDateRange($dateFrom, $dateTo)->count(),
            'average_sale' => Sale::completed()->byDateRange($dateFrom, $dateTo)->avg('total_amount'),
            'today_sales' => Sale::completed()->today()->sum('total_amount'),
            'today_count' => Sale::completed()->today()->count(),
            'pending_sales' => Sale::where('payment_status', 'pending')->sum('total_amount'),
            'overdue_sales' => Sale::where('payment_status', 'overdue')->sum('total_amount')
        ];

        return response()->json($stats);
    }

    /**
     * Obtener ventas por método de pago
     */
    public function salesByPaymentMethod(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now()->endOfMonth());

        $salesByMethod = Sale::completed()
            ->byDateRange($dateFrom, $dateTo)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        return response()->json($salesByMethod);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Location;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'location']);

        // Filtros
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->get('brand_id'));
        }

        if ($request->has('low_stock')) {
            $query->lowStock();
        }

        if ($request->has('out_of_stock')) {
            $query->outOfStock();
        }

        $products = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => 'Productos obtenidos exitosamente'
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'barcode' => 'nullable|string|unique:products,barcode',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'min_stock_level' => 'required|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'unit_of_measure' => 'required|string',
            'weight' => 'nullable|numeric|min:0',
            'location_id' => 'nullable|exists:locations,id',
            'tax_rate' => 'nullable|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $product = Product::create($request->all());

            // Crear movimiento de stock inicial
            if ($product->stock_quantity > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'quantity' => $product->stock_quantity,
                    'previous_stock' => 0,
                    'new_stock' => $product->stock_quantity,
                    'notes' => 'Stock inicial',
                    'user_id' => auth()->id(),
                    'location_id' => $product->location_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $product->load(['category', 'brand', 'location']),
                'message' => 'Producto creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el producto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product->load(['category', 'brand', 'location', 'stockMovements.user']),
            'message' => 'Producto obtenido exitosamente'
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|unique:products,barcode,' . $product->id,
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'min_stock_level' => 'required|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'unit_of_measure' => 'required|string',
            'weight' => 'nullable|numeric|min:0',
            'location_id' => 'nullable|exists:locations,id',
            'tax_rate' => 'nullable|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $product->load(['category', 'brand', 'location']),
                'message' => 'Producto actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el producto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el producto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adjust stock for a product.
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer',
            'type' => 'required|in:adjustment,damage,loss',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $previousStock = $product->stock_quantity;
            $newStock = $previousStock + $request->quantity;

            if ($newStock < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El stock no puede ser negativo'
                ], 400);
            }

            $product->update(['stock_quantity' => $newStock]);

            StockMovement::create([
                'product_id' => $product->id,
                'type' => $request->type,
                'quantity' => $request->quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'notes' => $request->notes,
                'user_id' => auth()->id(),
                'location_id' => $product->location_id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $product->fresh(),
                'message' => 'Stock ajustado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al ajustar el stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock movements for a product.
     */
    public function stockMovements(Product $product): JsonResponse
    {
        $movements = $product->stockMovements()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $movements,
            'message' => 'Movimientos de stock obtenidos exitosamente'
        ]);
    }

    /**
     * Get low stock products.
     */
    public function lowStock(): JsonResponse
    {
        $products = Product::lowStock()
            ->with(['category', 'brand', 'location'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => 'Productos con stock bajo obtenidos exitosamente'
        ]);
    }

    /**
     * Get out of stock products.
     */
    public function outOfStock(): JsonResponse
    {
        $products = Product::outOfStock()
            ->with(['category', 'brand', 'location'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => 'Productos sin stock obtenidos exitosamente'
        ]);
    }

    /**
     * Get inventory summary.
     */
    public function summary(): JsonResponse
    {
        $summary = [
            'total_products' => Product::count(),
            'active_products' => Product::active()->count(),
            'low_stock_products' => Product::lowStock()->count(),
            'out_of_stock_products' => Product::outOfStock()->count(),
            'total_stock_value' => Product::sum(DB::raw('stock_quantity * cost_price')),
            'categories_count' => Category::count(),
            'brands_count' => Brand::count(),
            'locations_count' => Location::count()
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
            'message' => 'Resumen de inventario obtenido exitosamente'
        ]);
    }
}
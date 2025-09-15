<?php

namespace App\Http\Controllers;

use App\Models\SaleTemplate;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SaleTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SaleTemplate::with(['user', 'customer'])
            ->forUser(auth()->id());

        // Filtros
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'usage_count');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $templates = $query->paginate($perPage);

        // Agregar totales estimados
        $templates->getCollection()->transform(function ($template) {
            $template->estimated_total = $template->estimated_total;
            $template->items_with_products = $template->items_with_products;
            return $template;
        });

        return response()->json($templates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,card,transfer,check,credit',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        // Validar que los productos existan y tengan stock
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                throw ValidationException::withMessages([
                    'items' => "Producto con ID {$item['product_id']} no encontrado"
                ]);
            }
        }

        $validated['user_id'] = auth()->id();
        $validated['discount_percentage'] = $validated['discount_percentage'] ?? 0;
        $validated['tax_rate'] = $validated['tax_rate'] ?? 19;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $template = SaleTemplate::create($validated);
        $template->load(['user', 'customer']);
        $template->estimated_total = $template->estimated_total;
        $template->items_with_products = $template->items_with_products;

        return response()->json([
            'message' => 'Plantilla de venta creada exitosamente',
            'template' => $template
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SaleTemplate $saleTemplate): JsonResponse
    {
        // Verificar que el usuario sea el propietario
        if ($saleTemplate->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $saleTemplate->load(['user', 'customer']);
        $saleTemplate->estimated_total = $saleTemplate->estimated_total;
        $saleTemplate->items_with_products = $saleTemplate->items_with_products;

        return response()->json($saleTemplate);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SaleTemplate $saleTemplate): JsonResponse
    {
        // Verificar que el usuario sea el propietario
        if ($saleTemplate->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,card,transfer,check,credit',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        // Validar que los productos existan
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                throw ValidationException::withMessages([
                    'items' => "Producto con ID {$item['product_id']} no encontrado"
                ]);
            }
        }

        $validated['discount_percentage'] = $validated['discount_percentage'] ?? 0;
        $validated['tax_rate'] = $validated['tax_rate'] ?? 19;

        $saleTemplate->update($validated);
        $saleTemplate->load(['user', 'customer']);
        $saleTemplate->estimated_total = $saleTemplate->estimated_total;
        $saleTemplate->items_with_products = $saleTemplate->items_with_products;

        return response()->json([
            'message' => 'Plantilla de venta actualizada exitosamente',
            'template' => $saleTemplate
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SaleTemplate $saleTemplate): JsonResponse
    {
        // Verificar que el usuario sea el propietario
        if ($saleTemplate->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $saleTemplate->delete();

        return response()->json([
            'message' => 'Plantilla de venta eliminada exitosamente'
        ]);
    }

    /**
     * Crear venta desde plantilla
     */
    public function createSale(Request $request, SaleTemplate $saleTemplate): JsonResponse
    {
        // Verificar que el usuario sea el propietario
        if ($saleTemplate->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (!$saleTemplate->is_active) {
            return response()->json(['message' => 'La plantilla no está activa'], 400);
        }

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'sale_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        try {
            $sale = $saleTemplate->createSale(
                $validated['customer_id'] ?? null,
                [
                    'sale_date' => $validated['sale_date'] ?? now(),
                    'notes' => $validated['notes'] ?? $saleTemplate->notes
                ]
            );

            $sale->load(['customer', 'user', 'saleItems.product']);

            return response()->json([
                'message' => 'Venta creada exitosamente desde plantilla',
                'sale' => $sale
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear venta desde plantilla',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener plantillas más usadas
     */
    public function mostUsed(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        
        $templates = SaleTemplate::with(['user', 'customer'])
            ->forUser(auth()->id())
            ->active()
            ->mostUsed($limit)
            ->get();

        $templates->transform(function ($template) {
            $template->estimated_total = $template->estimated_total;
            return $template;
        });

        return response()->json($templates);
    }

    /**
     * Activar/desactivar plantilla
     */
    public function toggleActive(SaleTemplate $saleTemplate): JsonResponse
    {
        // Verificar que el usuario sea el propietario
        if ($saleTemplate->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $saleTemplate->update(['is_active' => !$saleTemplate->is_active]);

        return response()->json([
            'message' => $saleTemplate->is_active ? 'Plantilla activada' : 'Plantilla desactivada',
            'template' => $saleTemplate
        ]);
    }

    /**
     * Duplicar plantilla
     */
    public function duplicate(SaleTemplate $saleTemplate): JsonResponse
    {
        // Verificar que el usuario sea el propietario
        if ($saleTemplate->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $newTemplate = $saleTemplate->replicate();
        $newTemplate->name = $saleTemplate->name . ' (Copia)';
        $newTemplate->usage_count = 0;
        $newTemplate->last_used_at = null;
        $newTemplate->save();

        $newTemplate->load(['user', 'customer']);
        $newTemplate->estimated_total = $newTemplate->estimated_total;
        $newTemplate->items_with_products = $newTemplate->items_with_products;

        return response()->json([
            'message' => 'Plantilla duplicada exitosamente',
            'template' => $newTemplate
        ], 201);
    }
}

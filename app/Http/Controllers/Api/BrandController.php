<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class BrandController extends Controller
{
    /**
     * Display a listing of brands.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query();

        // Filtros
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->has('active_only') && $request->get('active_only')) {
            $query->active();
        }

        $brands = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $brands,
            'message' => 'Marcas obtenidas exitosamente'
        ]);
    }

    /**
     * Store a newly created brand.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:brands,name',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'website' => 'nullable|url',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:20',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $brand = Brand::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $brand,
                'message' => 'Marca creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la marca: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified brand.
     */
    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $brand->load('products'),
            'message' => 'Marca obtenida exitosamente'
        ]);
    }

    /**
     * Update the specified brand.
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:brands,name,' . $brand->id,
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'website' => 'nullable|url',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:20',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $brand->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $brand,
                'message' => 'Marca actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la marca: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified brand.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        try {
            // Verificar si tiene productos asociados
            if ($brand->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la marca porque tiene productos asociados'
                ], 400);
            }

            $brand->delete();

            return response()->json([
                'success' => true,
                'message' => 'Marca eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la marca: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get brands for select dropdown.
     */
    public function select(): JsonResponse
    {
        $brands = Brand::active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($brand) {
                return [
                    'value' => $brand->id,
                    'label' => $brand->name
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $brands,
            'message' => 'Marcas para select obtenidas exitosamente'
        ]);
    }

    /**
     * Get brand statistics.
     */
    public function statistics(Brand $brand): JsonResponse
    {
        $stats = [
            'total_products' => $brand->productCount(),
            'total_stock_value' => $brand->totalStockValue(),
            'active_products' => $brand->products()->where('is_active', true)->count(),
            'low_stock_products' => $brand->products()->whereRaw('stock_quantity <= min_stock_level')->count(),
            'out_of_stock_products' => $brand->products()->where('stock_quantity', 0)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Estadísticas de la marca obtenidas exitosamente'
        ]);
    }
}
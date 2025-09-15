<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::with(['parent', 'children']);

        // Filtros
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->has('parent_only') && $request->get('parent_only')) {
            $query->parent();
        }

        if ($request->has('active_only') && $request->get('active_only')) {
            $query->active();
        }

        $categories = $query->orderBy('sort_order')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Categorías obtenidas exitosamente'
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image_url' => 'nullable|url',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category = Category::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $category->load(['parent', 'children']),
                'message' => 'Categoría creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la categoría: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $category->load(['parent', 'children', 'products']),
            'message' => 'Categoría obtenida exitosamente'
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id|not_in:' . $category->id,
            'image_url' => 'nullable|url',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $category->load(['parent', 'children']),
                'message' => 'Categoría actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la categoría: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): JsonResponse
    {
        try {
            // Verificar si tiene productos asociados
            if ($category->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la categoría porque tiene productos asociados'
                ], 400);
            }

            // Verificar si tiene subcategorías
            if ($category->children()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la categoría porque tiene subcategorías'
                ], 400);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Categoría eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la categoría: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category tree structure.
     */
    public function tree(): JsonResponse
    {
        $categories = Category::with('children.children')
            ->parent()
            ->active()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Árbol de categorías obtenido exitosamente'
        ]);
    }

    /**
     * Get categories for select dropdown.
     */
    public function select(): JsonResponse
    {
        $categories = Category::active()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(function ($category) {
                return [
                    'value' => $category->id,
                    'label' => $category->full_name,
                    'parent_id' => $category->parent_id
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Categorías para select obtenidas exitosamente'
        ]);
    }
}
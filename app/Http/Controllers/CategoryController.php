<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * GET /categories
     * Filtros opcionales: ?query=...&parent_id=...&only_active=1&per_page=10
     */
    public function index(Request $request)
    {
        $perPage    = (int) $request->get('per_page', 0);
        $query      = $request->get('query');
        $parentId   = $request->get('parent_id');
        $onlyActive = (bool) $request->boolean('only_active', false);

        $q = Category::query()
            ->when($query, function ($w) use ($query) {
                $w->where(function ($x) use ($query) {
                    $x->where('name', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%");
                });
            })
            ->when($parentId !== null && $parentId !== '', function ($w) use ($parentId) {
                $w->where('parent_id', $parentId);
            })
            ->when($onlyActive, fn($w) => $w->where('is_active', true))
            ->withCount('children')
            ->orderBy('sort_order')
            ->orderBy('name');

        $data = $perPage > 0 ? $q->paginate($perPage) : $q->get();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * POST /categories
     */
    public function store(Request $request)
    {
        // Normaliza strings vacíos a null para pasar "nullable"
        foreach (['description', 'image_url'] as $k) {
            if ($request->has($k) && $request->input($k) === '') {
                $request->merge([$k => null]);
            }
        }

        $data = $request->validate([
            'name'        => [
                'required', 'string', 'max:255',
                // Único por parent (permitiendo null para raíces) e ignorando eliminados suaves
                Rule::unique('categories', 'name')
                    ->where(fn($q) => $q->whereNull('deleted_at')
                        ->where('parent_id', $request->input('parent_id'))),
            ],
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|integer|exists:categories,id',
            'image_url'   => 'nullable|string|max:2048',
            'is_active'   => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $data['is_active']  = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'data'    => $category->loadCount('children'),
        ], 201);
    }

    /**
     * GET /categories/select
     * Retorna id, name ordenados (útil para selects de padre)
     */
    public function select()
    {
        $rows = Category::query()
            ->select('id', 'name', 'parent_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rows,
        ]);
    }

    /**
     * GET /categories/tree
     * Árbol de categorías (top-level con hijos directos)
     */
    public function tree()
    {
        $roots = Category::whereNull('parent_id')
            ->orderBy('sort_order')->orderBy('name')
            ->with(['children' => function ($q) {
                $q->orderBy('sort_order')->orderBy('name');
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $roots,
        ]);
    }

    /**
     * GET /categories/{category}
     */
    public function show(Category $category)
    {
        $category->load('parent')->loadCount('children');

        return response()->json([
            'success' => true,
            'data'    => $category,
        ]);
    }

    /**
     * PUT/PATCH /categories/{category}
     * Updates parciales con "sometimes"
     */
    public function update(Request $request, Category $category)
    {
        foreach (['description', 'image_url'] as $k) {
            if ($request->has($k) && $request->input($k) === '') {
                $request->merge([$k => null]);
            }
        }

        // Evita que el padre sea el mismo registro
        if ($request->filled('parent_id') && (int)$request->input('parent_id') === (int)$category->id) {
            return response()->json([
                'success' => false,
                'message' => 'La categoría no puede ser su propio padre.',
            ], 422);
        }

        $data = $request->validate([
            'name'        => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('categories', 'name')
                    ->where(fn($q) => $q->whereNull('deleted_at')
                        ->where('parent_id', $request->has('parent_id')
                            ? $request->input('parent_id')
                            : $category->parent_id))
                    ->ignore($category->id),
            ],
            'description' => 'sometimes|nullable|string',
            'parent_id'   => 'sometimes|nullable|integer|exists:categories,id',
            'image_url'   => 'sometimes|nullable|string|max:2048',
            'is_active'   => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
        ]);

        $category->update($data);

        return response()->json([
            'success' => true,
            'data'    => $category->fresh()->loadCount('children'),
        ]);
    }

    /**
     * DELETE /categories/{category}
     * (por FK, los hijos quedan con parent_id = null)
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada correctamente',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    /**
     * Display a listing of locations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Location::with(['parent', 'children', 'warehouse']);

        // Filtros
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->byType($request->get('type'));
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        if ($request->has('active_only') && $request->get('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->has('available_only') && $request->get('available_only')) {
            $query->available();
        }

        $locations = $query->orderBy('code')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $locations,
            'message' => 'Ubicaciones obtenidas exitosamente'
        ]);
    }

    /**
     * Store a newly created location.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:locations,code',
            'description' => 'nullable|string',
            'type' => 'required|in:warehouse,zone,aisle,rack,shelf,bin',
            'parent_id' => 'nullable|exists:locations,id',
            'warehouse_id' => 'nullable|exists:locations,id',
            'aisle' => 'nullable|string',
            'rack' => 'nullable|string',
            'shelf' => 'nullable|string',
            'bin' => 'nullable|string',
            'capacity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'temperature_controlled' => 'boolean',
            'security_level' => 'in:low,medium,high'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Si es un almacén, no debe tener parent_id ni warehouse_id
            if ($request->type === 'warehouse') {
                $request->merge([
                    'parent_id' => null,
                    'warehouse_id' => null
                ]);
            }

            $location = Location::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $location->load(['parent', 'children', 'warehouse']),
                'message' => 'Ubicación creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la ubicación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified location.
     */
    public function show(Location $location): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $location->load(['parent', 'children', 'warehouse', 'products', 'stockMovements']),
            'message' => 'Ubicación obtenida exitosamente'
        ]);
    }

    /**
     * Update the specified location.
     */
    public function update(Request $request, Location $location): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:locations,code,' . $location->id,
            'description' => 'nullable|string',
            'type' => 'required|in:warehouse,zone,aisle,rack,shelf,bin',
            'parent_id' => 'nullable|exists:locations,id|not_in:' . $location->id,
            'warehouse_id' => 'nullable|exists:locations,id|not_in:' . $location->id,
            'aisle' => 'nullable|string',
            'rack' => 'nullable|string',
            'shelf' => 'nullable|string',
            'bin' => 'nullable|string',
            'capacity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'temperature_controlled' => 'boolean',
            'security_level' => 'in:low,medium,high'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Si es un almacén, no debe tener parent_id ni warehouse_id
            if ($request->type === 'warehouse') {
                $request->merge([
                    'parent_id' => null,
                    'warehouse_id' => null
                ]);
            }

            $location->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $location->load(['parent', 'children', 'warehouse']),
                'message' => 'Ubicación actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la ubicación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified location.
     */
    public function destroy(Location $location): JsonResponse
    {
        try {
            // Verificar si tiene productos asociados
            if ($location->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la ubicación porque tiene productos asociados'
                ], 400);
            }

            // Verificar si tiene sub-ubicaciones
            if ($location->children()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la ubicación porque tiene sub-ubicaciones'
                ], 400);
            }

            $location->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ubicación eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la ubicación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouses only.
     */
    public function warehouses(): JsonResponse
    {
        $warehouses = Location::warehouses()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses,
            'message' => 'Almacenes obtenidos exitosamente'
        ]);
    }

    /**
     * Get location tree structure.
     */
    public function tree(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        
        $query = Location::with('children.children.children.children')
            ->where('is_active', true);

        if ($warehouseId) {
            $query->where('id', $warehouseId)->orWhere('warehouse_id', $warehouseId);
        } else {
            $query->whereNull('parent_id');
        }

        $locations = $query->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $locations,
            'message' => 'Árbol de ubicaciones obtenido exitosamente'
        ]);
    }

    /**
     * Get locations for select dropdown.
     */
    public function select(Request $request): JsonResponse
    {
        $query = Location::where('is_active', true);

        if ($request->has('type')) {
            $query->byType($request->get('type'));
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        $locations = $query->orderBy('code')
            ->get(['id', 'name', 'code', 'type'])
            ->map(function ($location) {
                return [
                    'value' => $location->id,
                    'label' => $location->full_name,
                    'code' => $location->code,
                    'type' => $location->type
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $locations,
            'message' => 'Ubicaciones para select obtenidas exitosamente'
        ]);
    }

    /**
     * Get location statistics.
     */
    public function statistics(Location $location): JsonResponse
    {
        $stats = [
            'occupancy_percentage' => $location->occupancy_percentage,
            'available_capacity' => $location->available_capacity,
            'current_stock' => $location->current_stock,
            'capacity' => $location->capacity,
            'products_count' => $location->products()->count(),
            'is_full' => $location->isFull(),
            'children_count' => $location->children()->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Estadísticas de la ubicación obtenidas exitosamente'
        ]);
    }

    /**
     * Get location types.
     */
    public function types(): JsonResponse
    {
        $types = Location::getLocationTypes();

        return response()->json([
            'success' => true,
            'data' => $types,
            'message' => 'Tipos de ubicación obtenidos exitosamente'
        ]);
    }
}
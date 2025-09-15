<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\Location;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    /**
     * Listar almacenes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::with(['locations']);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->search($search);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        if ($request->filled('temperature_controlled')) {
            $query->where('temperature_controlled', $request->boolean('temperature_controlled'));
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $warehouses = $query->paginate($perPage);

        return response()->json($warehouses);
    }

    /**
     * Crear nuevo almacén
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20|unique:warehouses,code',
            'description' => 'nullable|string',
            'type' => 'required|string|max:50',
            'is_main' => 'boolean',
            'is_active' => 'boolean',
            
            // Información de contacto
            'manager_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            
            // Dirección
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            
            // Capacidad
            'total_capacity' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:20',
            
            // Temperatura
            'temperature_controlled' => 'boolean',
            'min_temperature' => 'nullable|numeric',
            'max_temperature' => 'nullable|numeric',
            'temperature_unit' => 'nullable|string|max:10',
            
            // Seguridad
            'security_level' => 'nullable|string|max:20',
            'hazmat_approved' => 'boolean',
            'requires_certification' => 'boolean',
            
            // Costos
            'storage_cost_per_unit' => 'nullable|numeric|min:0',
            'handling_cost_per_unit' => 'nullable|numeric|min:0',
            'cost_currency' => 'nullable|string|max:3',
            
            // Otros
            'operating_hours' => 'nullable|array',
            'timezone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array'
        ]);

        // Validar que solo haya un almacén principal
        if ($validated['is_main'] ?? false) {
            Warehouse::where('is_main', true)->update(['is_main' => false]);
        }

        $warehouse = Warehouse::create($validated);
        $warehouse->load(['locations']);

        return response()->json([
            'message' => 'Almacén creado exitosamente',
            'warehouse' => $warehouse
        ], 201);
    }

    /**
     * Mostrar almacén específico
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load([
            'locations' => function ($query) {
                $query->active()->orderBy('name');
            }
        ]);

        // Agregar estadísticas
        $warehouse->stats = $warehouse->getStats();

        return response()->json($warehouse);
    }

    /**
     * Actualizar almacén
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('warehouses', 'code')->ignore($warehouse->id)
            ],
            'description' => 'nullable|string',
            'type' => 'required|string|max:50',
            'is_main' => 'boolean',
            'is_active' => 'boolean',
            
            // Información de contacto
            'manager_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            
            // Dirección
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            
            // Capacidad
            'total_capacity' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:20',
            
            // Temperatura
            'temperature_controlled' => 'boolean',
            'min_temperature' => 'nullable|numeric',
            'max_temperature' => 'nullable|numeric',
            'temperature_unit' => 'nullable|string|max:10',
            
            // Seguridad
            'security_level' => 'nullable|string|max:20',
            'hazmat_approved' => 'boolean',
            'requires_certification' => 'boolean',
            
            // Costos
            'storage_cost_per_unit' => 'nullable|numeric|min:0',
            'handling_cost_per_unit' => 'nullable|numeric|min:0',
            'cost_currency' => 'nullable|string|max:3',
            
            // Otros
            'operating_hours' => 'nullable|array',
            'timezone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array'
        ]);

        // Validar que solo haya un almacén principal
        if ($validated['is_main'] ?? false) {
            Warehouse::where('is_main', true)
                ->where('id', '!=', $warehouse->id)
                ->update(['is_main' => false]);
        }

        $warehouse->update($validated);
        $warehouse->load(['locations']);

        return response()->json([
            'message' => 'Almacén actualizado exitosamente',
            'warehouse' => $warehouse
        ]);
    }

    /**
     * Eliminar almacén
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if (!$warehouse->canBeDeleted()) {
            return response()->json([
                'message' => 'No se puede eliminar el almacén porque tiene stock o movimientos asociados'
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'message' => 'Almacén eliminado exitosamente'
        ]);
    }

    /**
     * Cambiar estado activo/inactivo
     */
    public function toggleStatus(Warehouse $warehouse): JsonResponse
    {
        $warehouse->is_active = !$warehouse->is_active;
        $warehouse->save();

        $status = $warehouse->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'message' => "Almacén {$status} exitosamente",
            'warehouse' => $warehouse
        ]);
    }

    /**
     * Establecer como almacén principal
     */
    public function setAsMain(Warehouse $warehouse): JsonResponse
    {
        if (!$warehouse->is_active) {
            return response()->json([
                'message' => 'No se puede establecer como principal un almacén inactivo'
            ], 422);
        }

        $warehouse->setAsMain();

        return response()->json([
            'message' => 'Almacén establecido como principal exitosamente',
            'warehouse' => $warehouse
        ]);
    }

    /**
     * Obtener estadísticas del almacén
     */
    public function stats(Request $request): JsonResponse
    {
        $query = Warehouse::query();

        if ($request->filled('warehouse_id')) {
            $warehouse = Warehouse::findOrFail($request->warehouse_id);
            $stats = $warehouse->getStats();
            
            return response()->json([
                'warehouse' => $warehouse->name,
                'stats' => $stats
            ]);
        }

        // Estadísticas generales
        $stats = [
            'total_warehouses' => Warehouse::count(),
            'active_warehouses' => Warehouse::active()->count(),
            'temperature_controlled' => Warehouse::where('temperature_controlled', true)->count(),
            'hazmat_approved' => Warehouse::where('hazmat_approved', true)->count(),
            'total_capacity' => Warehouse::sum('total_capacity'),
            'used_capacity' => Warehouse::sum('used_capacity'),
            'utilization_percentage' => Warehouse::avg('utilization_percentage'),
            'by_type' => Warehouse::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'by_security_level' => Warehouse::selectRaw('security_level, COUNT(*) as count')
                ->groupBy('security_level')
                ->pluck('count', 'security_level')
        ];

        return response()->json($stats);
    }

    /**
     * Buscar almacenes
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $warehouses = Warehouse::search($request->q)
            ->active()
            ->select('id', 'code', 'name', 'city', 'type')
            ->limit(10)
            ->get();

        return response()->json($warehouses);
    }

    /**
     * Obtener tipos de almacén
     */
    public function getWarehouseTypes(): JsonResponse
    {
        $types = Warehouse::getTypes();
        
        return response()->json($types);
    }

    /**
     * Obtener niveles de seguridad
     */
    public function getSecurityLevels(): JsonResponse
    {
        $levels = Warehouse::getSecurityLevels();
        
        return response()->json($levels);
    }

    /**
     * Obtener ubicaciones de un almacén
     */
    public function getLocations(Warehouse $warehouse): JsonResponse
    {
        $locations = $warehouse->locations()
            ->active()
            ->orderBy('zone')
            ->orderBy('aisle')
            ->orderBy('rack')
            ->orderBy('shelf')
            ->get();

        return response()->json($locations);
    }

    /**
     * Actualizar utilización del almacén
     */
    public function updateUtilization(Warehouse $warehouse): JsonResponse
    {
        $warehouse->updateUtilization();

        return response()->json([
            'message' => 'Utilización actualizada exitosamente',
            'utilization_percentage' => $warehouse->utilization_percentage,
            'used_capacity' => $warehouse->used_capacity
        ]);
    }
}
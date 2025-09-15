<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Listar todos los clientes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::with(['sales' => function($query) {
            $query->select('id', 'customer_id', 'total_amount', 'created_at');
        }]);

        // Filtros
        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('active')) {
            $query->active($request->boolean('active'));
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $customers = $query->paginate($perPage);

        return response()->json($customers);
    }

    /**
     * Crear un nuevo cliente
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|unique:customers,tax_id',
            'customer_type' => 'required|in:individual,business,wholesale,retail',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'required|in:cash,credit,net_15,net_30,net_60',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string'
        ]);

        $customer = Customer::create($validated);
        $customer->load('sales');

        return response()->json([
            'message' => 'Cliente creado exitosamente',
            'customer' => $customer
        ], 201);
    }

    /**
     * Mostrar un cliente específico
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load([
            'sales' => function($query) {
                $query->with('saleItems.product')
                      ->orderBy('created_at', 'desc')
                      ->limit(10);
            }
        ]);

        return response()->json($customer);
    }

    /**
     * Actualizar un cliente
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                Rule::unique('customers')->ignore($customer->id)
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'tax_id' => [
                'nullable',
                'string',
                Rule::unique('customers')->ignore($customer->id)
            ],
            'customer_type' => 'required|in:individual,business,wholesale,retail',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'required|in:cash,credit,net_15,net_30,net_60',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string'
        ]);

        $customer->update($validated);
        $customer->load('sales');

        return response()->json([
            'message' => 'Cliente actualizado exitosamente',
            'customer' => $customer
        ]);
    }

    /**
     * Eliminar un cliente
     */
    public function destroy(Customer $customer): JsonResponse
    {
        // Verificar si el cliente tiene ventas
        if ($customer->sales()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el cliente porque tiene ventas asociadas'
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Cliente eliminado exitosamente'
        ]);
    }

    /**
     * Activar/desactivar cliente
     */
    public function toggleStatus(Customer $customer): JsonResponse
    {
        $customer->update([
            'is_active' => !$customer->is_active
        ]);

        return response()->json([
            'message' => $customer->is_active ? 'Cliente activado' : 'Cliente desactivado',
            'customer' => $customer
        ]);
    }

    /**
     * Obtener estadísticas del cliente
     */
    public function stats(Customer $customer): JsonResponse
    {
        $stats = [
            'total_sales' => $customer->totalSales,
            'sales_count' => $customer->salesCount,
            'average_purchase' => $customer->averagePurchase,
            'available_credit' => $customer->availableCredit,
            'is_frequent_customer' => $customer->isFrequentCustomer(),
            'is_vip_customer' => $customer->isVipCustomer(),
            'last_purchase_date' => $customer->sales()->latest()->first()?->created_at,
            'pending_balance' => $customer->sales()->where('payment_status', '!=', 'paid')->sum('total_amount')
        ];

        return response()->json($stats);
    }

    /**
     * Buscar clientes
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        
        $customers = Customer::search($query)
            ->active()
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone', 'customer_type']);

        return response()->json($customers);
    }

    /**
     * Obtener tipos de cliente
     */
    public function getCustomerTypes(): JsonResponse
    {
        return response()->json(Customer::getCustomerTypes());
    }

    /**
     * Obtener términos de pago
     */
    public function getPaymentTerms(): JsonResponse
    {
        return response()->json(Customer::getPaymentTerms());
    }
}
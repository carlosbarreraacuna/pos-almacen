<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = $request->get('per_page', 15);
        $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $customers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'email'           => 'nullable|email|unique:customers,email',
            'phone'           => 'nullable|string|max:20',
            'document_type'   => 'nullable|string|max:50',
            'document_number' => 'nullable|string|max:50',
            'customer_type'   => 'nullable|in:individual,business,wholesale,retail',
            'address'         => 'nullable|string',
            'city'            => 'nullable|string|max:100',
            'state'           => 'nullable|string|max:100',
            'country'         => 'nullable|string|max:100',
            'postal_code'     => 'nullable|string|max:20',
            'tax_id'          => 'nullable|string|max:50|unique:customers,tax_id',
            'credit_limit'    => 'nullable|numeric|min:0',
            'payment_terms'   => 'nullable|in:cash,credit,net_15,net_30,net_60',
            'notes'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['customer_type'] = $data['customer_type'] ?? 'individual';
        $data['is_active']     = true;

        $customer = Customer::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'data'    => $customer,
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->loadCount('sales');
        $customer->total_sales_amount = $customer->sales()->sum('total_amount');

        return response()->json([
            'success' => true,
            'data'    => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'sometimes|required|string|max:255',
            'email'         => 'nullable|email|unique:customers,email,' . $customer->id,
            'phone'         => 'nullable|string|max:20',
            'customer_type' => 'nullable|in:individual,business,wholesale,retail',
            'address'       => 'nullable|string',
            'city'          => 'nullable|string|max:100',
            'state'         => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'postal_code'   => 'nullable|string|max:20',
            'tax_id'        => 'nullable|string|max:50|unique:customers,tax_id,' . $customer->id,
            'credit_limit'  => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|in:cash,credit,net_15,net_30,net_60',
            'is_active'     => 'nullable|boolean',
            'notes'         => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $customer->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado exitosamente',
            'data'    => $customer->fresh(),
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado exitosamente',
        ]);
    }

    public function toggleStatus(Customer $customer): JsonResponse
    {
        $customer->is_active = !$customer->is_active;
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'data'    => $customer,
        ]);
    }

    public function sales(Customer $customer, Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 10);
        $sales = $customer->sales()
            ->with('saleItems.product')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $sales,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        $customers = Customer::where('is_active', true)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $customers,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        $perPage = $request->get('per_page', 15);
        $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'document_type' => 'nullable|string|max:50',
            'document_number' => 'nullable|string|max:50',
            'customer_type' => 'required|in:individual,business',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::create($request->all());

        return response()->json([
            'message' => 'Cliente creado exitosamente',
            'customer' => $customer
        ], 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|unique:customers,email,' . $customer->id,
            'phone' => 'nullable|string|max:20',
            'document_type' => 'nullable|string|max:50',
            'document_number' => 'nullable|string|max:50',
            'customer_type' => 'sometimes|required|in:individual,business',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer->update($request->all());

        return response()->json([
            'message' => 'Cliente actualizado exitosamente',
            'customer' => $customer
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json([
            'message' => 'Cliente eliminado exitosamente'
        ]);
    }

    public function search(Request $request)
    {
        $search = $request->get('q', '');
        
        $customers = Customer::where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->orWhere('document_number', 'like', "%{$search}%")
            ->where('status', 'active')
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

    public function getCustomerTypes()
    {
        return response()->json([
            ['value' => 'individual', 'label' => 'Individual'],
            ['value' => 'business', 'label' => 'Empresa']
        ]);
    }

    public function getPaymentTerms()
    {
        return response()->json([
            ['value' => 'immediate', 'label' => 'Inmediato'],
            ['value' => 'net_15', 'label' => 'Neto 15 días'],
            ['value' => 'net_30', 'label' => 'Neto 30 días'],
            ['value' => 'net_60', 'label' => 'Neto 60 días'],
            ['value' => 'net_90', 'label' => 'Neto 90 días']
        ]);
    }

    public function toggleStatus(Customer $customer)
    {
        $customer->status = $customer->status === 'active' ? 'inactive' : 'active';
        $customer->save();

        return response()->json([
            'message' => 'Estado actualizado exitosamente',
            'customer' => $customer
        ]);
    }

    public function stats(Customer $customer)
    {
        $stats = [
            'total_orders' => $customer->orders()->count(),
            'total_spent' => $customer->orders()->sum('total'),
            'pending_orders' => $customer->orders()->where('status', 'pending')->count(),
            'completed_orders' => $customer->orders()->where('status', 'completed')->count(),
        ];

        return response()->json($stats);
    }
}

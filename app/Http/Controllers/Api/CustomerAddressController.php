<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    /** GET /api/cliente/direcciones */
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->orderByDesc('created_at')->get();

        return response()->json(['success' => true, 'data' => $addresses]);
    }

    /** POST /api/cliente/direcciones */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label'           => 'nullable|string|max:50',
            'full_name'       => 'required|string|max:255',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'required|string',
            'city'            => 'required|string|max:100',
            'state'           => 'required|string|max:100',
            'postal_code'     => 'nullable|string|max:20',
            'country'         => 'nullable|string|max:50',
            'additional_info' => 'nullable|string|max:255',
            'is_default'      => 'boolean',
        ]);

        $customer = $request->user();

        if (!empty($validated['is_default'])) {
            $customer->addresses()->update(['is_default' => false]);
        }

        // Si es la primera dirección, hacerla default
        if ($customer->addresses()->count() === 0) {
            $validated['is_default'] = true;
        }

        $address = $customer->addresses()->create($validated);

        return response()->json(['success' => true, 'data' => $address], 201);
    }

    /** PUT /api/cliente/direcciones/{id} */
    public function update(Request $request, $id)
    {
        $customer = $request->user();
        $address = $customer->addresses()->findOrFail($id);

        $validated = $request->validate([
            'label'           => 'nullable|string|max:50',
            'full_name'       => 'sometimes|required|string|max:255',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'sometimes|required|string',
            'city'            => 'sometimes|required|string|max:100',
            'state'           => 'sometimes|required|string|max:100',
            'postal_code'     => 'nullable|string|max:20',
            'country'         => 'nullable|string|max:50',
            'additional_info' => 'nullable|string|max:255',
            'is_default'      => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            $customer->addresses()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json(['success' => true, 'data' => $address->fresh()]);
    }

    /** DELETE /api/cliente/direcciones/{id} */
    public function destroy(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return response()->json(['success' => true, 'message' => 'Dirección eliminada.']);
    }
}

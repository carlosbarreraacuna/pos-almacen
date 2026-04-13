<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerPaymentMethod;
use Illuminate\Http\Request;

class CustomerPaymentMethodController extends Controller
{
    /** GET /api/cliente/tarjetas */
    public function index(Request $request)
    {
        $cards = $request->user()->paymentMethods()->orderByDesc('is_default')->get();
        return response()->json(['success' => true, 'data' => $cards]);
    }

    /** POST /api/cliente/tarjetas */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'card_brand'  => 'required|string|max:20',
            'last_four'   => 'required|string|size:4',
            'holder_name' => 'required|string|max:100',
            'exp_month'   => 'required|string|size:2',
            'exp_year'    => 'required|string|size:4',
            'token'       => 'nullable|string',
            'is_default'  => 'boolean',
        ]);

        $customer = $request->user();

        if (!empty($validated['is_default'])) {
            $customer->paymentMethods()->update(['is_default' => false]);
        }

        if ($customer->paymentMethods()->count() === 0) {
            $validated['is_default'] = true;
        }

        $card = $customer->paymentMethods()->create($validated);
        return response()->json(['success' => true, 'data' => $card], 201);
    }

    /** DELETE /api/cliente/tarjetas/{id} */
    public function destroy(Request $request, $id)
    {
        $card = $request->user()->paymentMethods()->findOrFail($id);
        $card->delete();
        return response()->json(['success' => true, 'message' => 'Tarjeta eliminada.']);
    }

    /** PATCH /api/cliente/tarjetas/{id}/default */
    public function setDefault(Request $request, $id)
    {
        $customer = $request->user();
        $customer->paymentMethods()->update(['is_default' => false]);
        $customer->paymentMethods()->where('id', $id)->update(['is_default' => true]);
        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    /**
     * Validar un cupón por código
     * POST /api/store/coupons/validate
     */
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'order_total' => 'required|numeric|min:0',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $code = strtoupper(trim($request->code));
        $orderTotal = $request->order_total;
        $productIds = $request->product_ids ?? [];

        // Buscar cupón por código
        $coupon = Coupon::byCode($code)->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Cupón no encontrado o código inválido'
            ], 404);
        }

        // Validar si el cupón puede ser aplicado
        $errors = $coupon->canBeAppliedToOrder($orderTotal, $productIds);

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => $errors[0],
                'errors' => $errors
            ], 400);
        }

        // Calcular descuento
        $discount = $coupon->calculateDiscount($orderTotal);

        return response()->json([
            'success' => true,
            'message' => 'Cupón válido',
            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'description' => $coupon->description,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                ],
                'discount' => $discount,
                'new_total' => max(0, $orderTotal - $discount)
            ]
        ]);
    }

    /**
     * Listar cupones activos (para el admin)
     * GET /api/coupons
     */
    public function index()
    {
        $coupons = Coupon::with(['freeProduct', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }

    /**
     * Listar cupones válidos (para la tienda)
     * GET /api/store/coupons
     */
    public function activeForStore()
    {
        $coupons = Coupon::valid()
            ->select(['id', 'code', 'name', 'description', 'type', 'value', 'min_purchase', 'max_discount', 'valid_until'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }

    /**
     * Crear nuevo cupón
     * POST /api/coupons
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:coupons,code|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed,free_product',
            'value' => 'required|numeric|min:0',
            'free_product_id' => 'nullable|exists:products,id',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'usage_limit' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'customer_restrictions' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['code'] = strtoupper($data['code']);
        $data['created_by'] = auth()->id();

        $coupon = Coupon::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Cupón creado exitosamente',
            'data' => $coupon
        ], 201);
    }

    /**
     * Actualizar cupón
     * PUT /api/coupons/{id}
     */
    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|unique:coupons,code,' . $id . '|max:50',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:percentage,fixed,free_product',
            'value' => 'sometimes|numeric|min:0',
            'free_product_id' => 'nullable|exists:products,id',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'valid_from' => 'sometimes|date',
            'valid_until' => 'sometimes|date',
            'usage_limit' => 'sometimes|integer|min:1',
            'is_active' => 'boolean',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'customer_restrictions' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $coupon->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cupón actualizado exitosamente',
            'data' => $coupon
        ]);
    }

    /**
     * Eliminar cupón
     * DELETE /api/coupons/{id}
     */
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cupón eliminado exitosamente'
        ]);
    }

    /**
     * Obtener detalles de un cupón
     * GET /api/coupons/{id}
     */
    public function show($id)
    {
        $coupon = Coupon::with(['freeProduct', 'creator'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $coupon
        ]);
    }
}

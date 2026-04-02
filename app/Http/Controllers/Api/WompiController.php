<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WompiController extends Controller
{
    /**
     * Generate integrity signature for Wompi transaction
     */
    public function generateIntegrity(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
            'amount_in_cents' => 'required|integer',
            'currency' => 'required|string',
        ]);

        $reference = $request->reference;
        $amountInCents = $request->amount_in_cents;
        $currency = $request->currency;
        $integritySecret = env('WOMPI_INTEGRITY_SECRET');

        if (!$integritySecret) {
            return response()->json([
                'success' => false,
                'message' => 'WOMPI_INTEGRITY_SECRET no está configurado'
            ], 500);
        }

        // Generar firma de integridad según documentación de Wompi
        // integrity = SHA256(reference + amountInCents + currency + integritySecret)
        $concatenated = $reference . $amountInCents . $currency . $integritySecret;
        $integrity = hash('sha256', $concatenated);

        return response()->json([
            'success' => true,
            'data' => [
                'integrity' => $integrity
            ]
        ]);
    }

    /**
     * Verify and retrieve order details securely
     */
    public function verifyOrder(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string',
            'transaction_id' => 'required|string',
        ]);

        try {
            // Buscar la orden por número de orden
            $order = \App\Models\Order::where('numero_orden', $request->order_id)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada'
                ], 404);
            }

            // Verificar que la referencia de pago coincida
            if ($order->referencia_pago !== $request->transaction_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Referencia de pago no coincide'
                ], 403);
            }

            // Verificar el estado del pago con Wompi
            $wompiStatus = $this->verifyWompiTransaction($request->transaction_id);

            // Actualizar estado de la orden si es necesario
            if ($wompiStatus && $wompiStatus['status'] === 'APPROVED' && $order->estado_pago !== 'pagado') {
                $order->update([
                    'estado_pago' => 'pagado',
                    'estado' => 'confirmado'
                ]);
            }

            // Cargar relaciones
            $order->load('items.producto');

            return response()->json([
                'success' => true,
                'data' => [
                    'order' => [
                        'numero_orden' => $order->numero_orden,
                        'fecha' => $order->created_at->format('d/m/Y H:i'),
                        'cliente_nombre' => $order->cliente_nombre,
                        'cliente_email' => $order->cliente_email,
                        'cliente_telefono' => $order->cliente_telefono,
                        'cliente_direccion' => $order->cliente_direccion,
                        'cliente_ciudad' => $order->cliente_ciudad,
                        'cliente_departamento' => $order->cliente_departamento,
                        'subtotal' => $order->subtotal,
                        'envio' => $order->envio,
                        'total' => $order->total,
                        'estado' => $order->estado,
                        'estado_pago' => $order->estado_pago,
                        'metodo_pago' => $order->metodo_pago,
                        'cupon_codigo' => $order->cupon_codigo,
                        'cupon_descuento' => $order->cupon_descuento,
                        'items' => $order->items->map(function ($item) {
                            return [
                                'producto_nombre' => $item->producto_nombre,
                                'cantidad' => $item->cantidad,
                                'precio' => $item->precio,
                                'subtotal' => $item->cantidad * $item->precio,
                            ];
                        }),
                    ],
                    'payment_status' => $wompiStatus,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error verifying order: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar la orden'
            ], 500);
        }
    }

    /**
     * Verify transaction status with Wompi API
     */
    private function verifyWompiTransaction(string $transactionId): ?array
    {
        try {
            $publicKey = env('WOMPI_PUBLIC_KEY');
            
            if (!$publicKey) {
                return null;
            }

            // Llamar a la API de Wompi para verificar el estado de la transacción
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $publicKey,
            ])->get("https://production.wompi.co/v1/transactions/{$transactionId}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['data']['status'] ?? 'UNKNOWN',
                    'payment_method' => $data['data']['payment_method_type'] ?? null,
                    'amount' => $data['data']['amount_in_cents'] ?? 0,
                ];
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Error verifying Wompi transaction: ' . $e->getMessage());
            return null;
        }
    }
}

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
}

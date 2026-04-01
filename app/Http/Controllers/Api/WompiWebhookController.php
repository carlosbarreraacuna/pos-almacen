<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WompiWebhookController extends Controller
{
    /**
     * Handle Wompi webhook events
     * POST /api/webhooks/wompi
     */
    public function handle(Request $request)
    {
        // Verificar la firma del webhook
        $signature = $request->header('X-Event-Signature');
        $payload = $request->getContent();
        
        $expectedSignature = hash_hmac(
            'sha256',
            $payload,
            env('WOMPI_EVENTS_SECRET')
        );

        if ($signature !== $expectedSignature) {
            Log::warning('Wompi webhook signature mismatch', [
                'received' => $signature,
                'expected' => $expectedSignature
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature'
            ], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        Log::info('Wompi webhook received', [
            'event' => $event,
            'data' => $data
        ]);

        // Procesar según el tipo de evento
        switch ($event) {
            case 'transaction.updated':
                $this->handleTransactionUpdated($data);
                break;
                
            case 'transaction.created':
                $this->handleTransactionCreated($data);
                break;
                
            default:
                Log::info('Unhandled Wompi event type: ' . $event);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed'
        ]);
    }

    /**
     * Handle transaction updated event
     */
    private function handleTransactionUpdated($data)
    {
        $transaction = $data['transaction'];
        $reference = $transaction['reference'];
        $status = $transaction['status'];

        // Buscar la orden por referencia de pago
        $order = Order::where('payment_reference', $reference)->first();

        if (!$order) {
            Log::warning('Order not found for Wompi transaction', [
                'reference' => $reference
            ]);
            return;
        }

        // Actualizar estado de la orden según el estado de la transacción
        switch ($status) {
            case 'APPROVED':
                $order->update([
                    'status' => 'paid',
                    'payment_status' => 'approved',
                    'payment_date' => now()
                ]);
                
                // Aquí puedes enviar email de confirmación, etc.
                Log::info('Order payment approved', ['order_id' => $order->id]);
                break;
                
            case 'DECLINED':
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'declined'
                ]);
                
                Log::info('Order payment declined', ['order_id' => $order->id]);
                break;
                
            case 'ERROR':
                $order->update([
                    'payment_status' => 'error'
                ]);
                
                Log::error('Order payment error', ['order_id' => $order->id]);
                break;
                
            case 'VOIDED':
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'voided'
                ]);
                
                Log::info('Order payment voided', ['order_id' => $order->id]);
                break;
        }
    }

    /**
     * Handle transaction created event
     */
    private function handleTransactionCreated($data)
    {
        $transaction = $data['transaction'];
        
        Log::info('Wompi transaction created', [
            'reference' => $transaction['reference'],
            'amount' => $transaction['amount_in_cents']
        ]);
    }
}

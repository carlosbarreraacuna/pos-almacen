<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminOrderController extends Controller
{
    private array $validStatuses = [
        'pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled',
    ];

    public function index(Request $request)
    {
        $query = Order::with('items')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'ilike', "%{$s}%")
                  ->orWhere('customer_name', 'ilike', "%{$s}%")
                  ->orWhere('customer_email', 'ilike', "%{$s}%");
            });
        }

        $orders = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $orders->items(),
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
            ],
        ]);
    }

    public function show($id)
    {
        $order = Order::with('items')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $order]);
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status'           => 'sometimes|required|string|in:' . implode(',', $this->validStatuses),
            'tracking_number'  => 'nullable|string|max:100',
            'shipping_company' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $prevStatus = $order->status;

        // Set timestamps on status transitions
        if (isset($validated['status'])) {
            if ($validated['status'] === 'shipped' && $prevStatus !== 'shipped') {
                $validated['shipped_at'] = now();
            }
            if ($validated['status'] === 'delivered' && $prevStatus !== 'delivered') {
                $validated['delivered_at'] = now();
            }
        }

        $order->update($validated);

        // Notify customer when order is shipped
        if (isset($validated['status']) && $validated['status'] === 'shipped' && $prevStatus !== 'shipped') {
            try {
                $trackingInfo = $order->tracking_number
                    ? "<p>Número de seguimiento: <strong>{$order->tracking_number}</strong></p>"
                    . ($order->shipping_company ? "<p>Transportadora: <strong>{$order->shipping_company}</strong></p>" : '')
                    : '';

                $html = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#333'>
                  <div style='background:#111;padding:24px;text-align:center'>
                    <h1 style='color:#fff;margin:0;font-size:22px'>MOTO SPA</h1>
                    <p style='color:#6b7280;margin:4px 0 0;font-size:13px'>Tu pedido está en camino</p>
                  </div>
                  <div style='padding:28px 24px'>
                    <h2 style='font-size:18px;color:#111;margin:0 0 8px'>¡Tu pedido fue enviado! 🚚</h2>
                    <p style='color:#666;font-size:14px;margin:0 0 20px'>Hola {$order->customer_name}, tu pedido <strong>#{$order->order_number}</strong> está en camino.</p>
                    {$trackingInfo}
                    <p style='color:#666;font-size:13px;margin:20px 0 0'>Pronto recibirás tu pedido. ¡Gracias por comprar en Moto Spa!</p>
                  </div>
                  <div style='background:#f9fafb;padding:16px 24px;text-align:center;border-top:1px solid #e5e7eb'>
                    <p style='margin:0;color:#999;font-size:12px'>© Moto Spa</p>
                  </div>
                </div>";

                Mail::html($html, function ($message) use ($order) {
                    $message->to($order->customer_email, $order->customer_name)
                            ->subject("Tu pedido #{$order->order_number} fue enviado");
                });
            } catch (\Exception $e) {
                // Don't fail the update if email fails
            }
        }

        return response()->json(['success' => true, 'data' => $order->fresh('items')]);
    }

    public function stats()
    {
        $counts = Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'data'    => [
                'total'      => Order::count(),
                'pending'    => $counts['pending']    ?? 0,
                'paid'       => $counts['paid']       ?? 0,
                'processing' => $counts['processing'] ?? 0,
                'shipped'    => $counts['shipped']    ?? 0,
                'delivered'  => $counts['delivered']  ?? 0,
                'cancelled'  => $counts['cancelled']  ?? 0,
                'revenue'    => Order::whereIn('status', ['paid','processing','shipped','delivered'])->sum('total'),
            ],
        ]);
    }
}

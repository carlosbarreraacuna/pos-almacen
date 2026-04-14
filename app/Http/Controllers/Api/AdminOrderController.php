<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $order = Order::with('items')->findOrFail($id);

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
            if ($validated['status'] === 'paid' && $prevStatus !== 'paid') {
                $validated['paid_at'] = now();
            }
        }

        DB::transaction(function () use ($order, $validated, $prevStatus) {
            $order->update($validated);

            // Descontar stock si el admin marca manualmente como pagado
            if (isset($validated['status']) && $validated['status'] === 'paid' && $prevStatus !== 'paid') {
                foreach ($order->items as $item) {
                    Product::where('id', $item->product_id)
                        ->decrement('stock_quantity', $item->quantity);
                }
            }
        });
        $order->refresh();

        // Email: pedido enviado
        if (isset($validated['status']) && $validated['status'] === 'shipped' && $prevStatus !== 'shipped') {
            try {
                $trackingBlock = '';
                if ($order->tracking_number) {
                    $company = $order->shipping_company ? "<p style='margin:4px 0;font-size:13px;color:#555'>Transportadora: <strong>{$order->shipping_company}</strong></p>" : '';
                    $trackingBlock = "
                    <div style='background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin:20px 0'>
                      <p style='margin:0 0 4px;font-size:12px;color:#3b82f6;font-weight:600;text-transform:uppercase'>Número de guía</p>
                      <p style='margin:4px 0;font-size:20px;font-weight:700;color:#1d4ed8;font-family:monospace'>{$order->tracking_number}</p>
                      {$company}
                    </div>";
                }

                $html = "
                <div style='font-family:Arial,sans-serif;max-width:620px;margin:0 auto;color:#333;background:#fff'>
                  <div style='background:#1a1a1a;padding:28px 24px;text-align:center'>
                    <h1 style='color:#fff;margin:0;font-size:24px;letter-spacing:-0.5px'>Moto Spa</h1>
                    <p style='color:#999;margin:6px 0 0;font-size:13px'>Tu pedido está en camino</p>
                  </div>
                  <div style='padding:32px 24px'>
                    <h2 style='font-size:20px;color:#111;margin:0 0 8px'>¡Tu pedido fue enviado! 🚚</h2>
                    <p style='color:#666;font-size:14px;margin:0 0 4px'>
                      Hola <strong>{$order->customer_name}</strong>, tu pedido
                      <strong style='color:#111'>#{$order->order_number}</strong> está en camino.
                    </p>
                    {$trackingBlock}
                    <p style='color:#666;font-size:13px;margin:20px 0 0'>
                      Recibirás tu pedido pronto. ¡Gracias por comprar en Moto Spa!
                    </p>
                  </div>
                  <div style='background:#f9fafb;padding:16px 24px;text-align:center;border-top:1px solid #e5e7eb'>
                    <p style='margin:0;color:#aaa;font-size:12px'>© Moto Spa · Todos los derechos reservados</p>
                  </div>
                </div>";

                Mail::html($html, function ($message) use ($order) {
                    $message->to($order->customer_email, $order->customer_name)
                            ->subject("🚚 Tu pedido #{$order->order_number} fue enviado - Moto Spa");
                });
            } catch (\Exception) {}
        }

        // Email: pedido entregado
        if (isset($validated['status']) && $validated['status'] === 'delivered' && $prevStatus !== 'delivered') {
            try {
                $html = "
                <div style='font-family:Arial,sans-serif;max-width:620px;margin:0 auto;color:#333;background:#fff'>
                  <div style='background:#1a1a1a;padding:28px 24px;text-align:center'>
                    <h1 style='color:#fff;margin:0;font-size:24px;letter-spacing:-0.5px'>Moto Spa</h1>
                    <p style='color:#999;margin:6px 0 0;font-size:13px'>Pedido entregado</p>
                  </div>
                  <div style='padding:32px 24px;text-align:center'>
                    <div style='font-size:48px;margin-bottom:16px'>✅</div>
                    <h2 style='font-size:22px;color:#111;margin:0 0 10px'>¡Tu pedido llegó!</h2>
                    <p style='color:#666;font-size:14px;margin:0 0 20px'>
                      Hola <strong>{$order->customer_name}</strong>,<br>
                      tu pedido <strong style='color:#111'>#{$order->order_number}</strong> fue entregado exitosamente.
                    </p>
                    <p style='color:#888;font-size:13px;margin:0'>
                      Esperamos que disfrutes tu compra. ¡Gracias por elegirnos!
                    </p>
                  </div>
                  <div style='background:#f9fafb;padding:16px 24px;text-align:center;border-top:1px solid #e5e7eb'>
                    <p style='margin:0;color:#aaa;font-size:12px'>© Moto Spa · Todos los derechos reservados</p>
                  </div>
                </div>";

                Mail::html($html, function ($message) use ($order) {
                    $message->to($order->customer_email, $order->customer_name)
                            ->subject("✅ Tu pedido #{$order->order_number} fue entregado - Moto Spa");
                });
            } catch (\Exception) {}
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

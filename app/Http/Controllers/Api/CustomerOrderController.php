<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Sale;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    /** GET /api/cliente/pedidos — muestra órdenes web + ventas POS del cliente */
    public function index(Request $request)
    {
        $customerId = $request->user()->id;

        // Órdenes de la tienda web
        $webOrders = Order::with('items')
            ->where('customer_id', $customerId)
            ->get()
            ->map(fn($o) => $this->formatOrder($o));

        // Ventas del punto de venta físico
        $posOrders = Sale::with('saleItems.product')
            ->where('customer_id', $customerId)
            ->get()
            ->map(fn($s) => $this->formatSale($s));

        $all = $webOrders->concat($posOrders)
            ->sortByDesc('created_at')
            ->values();

        return response()->json(['success' => true, 'data' => $all]);
    }

    /** GET /api/cliente/pedidos/{id}?type=order|sale */
    public function show(Request $request, $id)
    {
        $customerId = $request->user()->id;
        $type = $request->query('type', 'order');

        if ($type === 'sale') {
            $sale = Sale::with('saleItems.product')
                ->where('id', $id)
                ->where('customer_id', $customerId)
                ->firstOrFail();
            return response()->json(['success' => true, 'data' => $this->formatSale($sale, true)]);
        }

        $order = Order::with('items')
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $this->formatOrder($order, true)]);
    }

    // ─── Formatos ─────────────────────────────────────────────────────────────

    private function formatOrder(Order $o, bool $detail = false): array
    {
        $statusMap = [
            'pending'    => 'Pendiente',
            'paid'       => 'Pago aprobado',
            'processing' => 'En preparación',
            'shipped'    => 'Enviando',
            'delivered'  => 'Entregado',
            'cancelled'  => 'Cancelado',
        ];
        $statusSteps = ['pending', 'paid', 'processing', 'shipped', 'delivered'];

        $data = [
            'id'             => $o->id,
            'type'           => 'order',
            'source'         => 'online',
            'source_label'   => 'Tienda online',
            'order_number'   => $o->order_number,
            'status'         => $o->status,
            'status_label'   => $statusMap[$o->status] ?? $o->status,
            'status_steps'   => $statusSteps,
            'current_step'   => array_search($o->status, $statusSteps),
            'total'          => (float) $o->total,
            'subtotal'       => (float) $o->subtotal,
            'shipping_cost'  => (float) $o->shipping_cost,
            'payment_method' => $o->payment_method,
            'created_at'     => $o->created_at->toISOString(),
            'items_count'    => $o->items->count(),
            'items'          => $o->items->map(fn($i) => [
                'id'           => $i->id,
                'product_name' => $i->product_name,
                'product_sku'  => $i->product_sku,
                'quantity'     => $i->quantity,
                'price'        => (float) $i->price,
                'subtotal'     => (float) $i->subtotal,
            ]),
        ];

        if ($detail) {
            $data['shipping_address']  = $o->shipping_address;
            $data['shipping_city']     = $o->shipping_city;
            $data['shipping_state']    = $o->shipping_state;
            $data['tracking_number']   = $o->tracking_number;
            $data['shipping_company']  = $o->shipping_company;
            $data['notes']             = $o->notes;
            $data['paid_at']           = $o->paid_at?->toISOString();
            $data['shipped_at']        = $o->shipped_at?->toISOString();
            $data['delivered_at']      = $o->delivered_at?->toISOString();
        }

        return $data;
    }

    private function formatSale(Sale $s, bool $detail = false): array
    {
        $statusMap = [
            'completed'  => 'Completado',
            'cancelled'  => 'Cancelado',
            'pending'    => 'Pendiente',
            'refunded'   => 'Reembolsado',
        ];

        $data = [
            'id'             => $s->id,
            'type'           => 'sale',
            'source'         => 'pos',
            'source_label'   => 'Tienda física',
            'order_number'   => $s->sale_number ?? ('POS-' . $s->id),
            'status'         => $s->status === 'completed' ? 'delivered' : ($s->status ?? 'delivered'),
            'status_label'   => $statusMap[$s->status] ?? 'Completado',
            'status_steps'   => ['pending', 'paid', 'processing', 'shipped', 'delivered'],
            'current_step'   => 4, // POS sales are immediately "delivered"
            'total'          => (float) $s->total_amount,
            'subtotal'       => (float) $s->subtotal,
            'shipping_cost'  => 0.0,
            'payment_method' => $s->payment_method,
            'created_at'     => $s->created_at->toISOString(),
            'items_count'    => $s->saleItems->count(),
            'items'          => $s->saleItems->map(fn($i) => [
                'id'           => $i->id,
                'product_name' => $i->product?->name ?? 'Producto',
                'product_sku'  => $i->product?->sku ?? '',
                'quantity'     => $i->quantity,
                'price'        => (float) $i->unit_price,
                'subtotal'     => (float) $i->total_amount,
            ]),
        ];

        if ($detail) {
            $data['shipping_address'] = null;
            $data['shipping_city']    = null;
            $data['shipping_state']   = null;
            $data['tracking_number']  = null;
            $data['shipping_company'] = null;
            $data['notes']            = $s->notes;
            $data['paid_at']          = $s->created_at?->toISOString();
            $data['shipped_at']       = null;
            $data['delivered_at']     = $s->created_at?->toISOString();
        }

        return $data;
    }
}

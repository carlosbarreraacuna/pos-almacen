<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer', 'user', 'saleItems.product']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                  ->orWhere('invoice_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date_to);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $perPage = $request->get('per_page', 15);
        $sales = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $sales,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id'              => 'nullable|exists:customers,id',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.quantity'         => 'required|integer|min:1',
            'items.*.unit_price'       => 'required|numeric|min:0',
            'items.*.discount_amount'  => 'nullable|numeric|min:0',
            'payment_method'           => 'required|in:cash,card,transfer,check,credit',
            'subtotal'                 => 'required|numeric|min:0',
            'tax_amount'               => 'nullable|numeric|min:0',
            'discount_amount'          => 'nullable|numeric|min:0',
            'total_amount'             => 'required|numeric|min:0',
            'notes'                    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Verificar stock antes de proceder
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                if ($product->stock_quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuficiente para '{$product->name}'. Disponible: {$product->stock_quantity}",
                    ], 422);
                }
            }

            $taxAmount      = $request->tax_amount      ?? 0;
            $discountAmount = $request->discount_amount ?? 0;

            $sale = Sale::create([
                'customer_id'     => $request->customer_id,
                'user_id'         => auth()->user()?->id,
                'sale_date'       => now(),
                'subtotal'        => $request->subtotal,
                'tax_amount'      => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount'    => $request->total_amount,
                'payment_method'  => $request->payment_method,
                'payment_status'  => 'paid',
                'status'          => 'completed',
                'notes'           => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $unitPrice      = $item['unit_price'];
                $discountItem   = $item['discount_amount'] ?? 0;
                $totalAmount    = ($unitPrice - $discountItem) * $item['quantity'];

                $sale->saleItems()->create([
                    'product_id'      => $item['product_id'],
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $unitPrice,
                    'discount_amount' => $discountItem,
                    'tax_rate'        => 0,
                    'tax_amount'      => 0,
                    'total_amount'    => $totalAmount,
                ]);

                Product::where('id', $item['product_id'])
                    ->decrement('stock_quantity', $item['quantity']);
            }

            DB::commit();

            // Enviar correo de confirmación si el cliente tiene email
            try {
                $sale->load(['saleItems.product', 'customer']);
                $customer = $sale->customer;
                if ($customer && $customer->email) {
                    $fmt = fn($n) => '$' . number_format($n, 0, ',', '.');

                    $itemsHtml = '';
                    foreach ($sale->saleItems as $item) {
                        $nombre = $item->product?->name ?? 'Producto';
                        $itemsHtml .= "<tr>
                            <td style='padding:8px;border-bottom:1px solid #eee'>{$nombre}</td>
                            <td style='padding:8px;border-bottom:1px solid #eee;text-align:center'>{$item->quantity}</td>
                            <td style='padding:8px;border-bottom:1px solid #eee;text-align:right'>{$fmt($item->unit_price)}</td>
                            <td style='padding:8px;border-bottom:1px solid #eee;text-align:right'>{$fmt($item->total_amount)}</td>
                        </tr>";
                    }

                    $saleRef = $sale->sale_number ?? ('POS-' . $sale->id);
                    $html = "
                    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#333'>
                      <div style='background:#1a1a1a;padding:24px;text-align:center'>
                        <h1 style='color:#fff;margin:0;font-size:22px'>Moto Spa</h1>
                        <p style='color:#aaa;margin:6px 0 0'>Comprobante de compra — Tienda física</p>
                      </div>
                      <div style='padding:24px'>
                        <p>Hola <strong>{$customer->name}</strong>,</p>
                        <p>Gracias por tu compra en nuestra tienda. Aquí tienes el resumen:</p>
                        <div style='background:#f5f5f5;padding:16px;border-radius:8px;margin:16px 0'>
                          <p style='margin:0 0 4px'><strong>Referencia:</strong> {$saleRef}</p>
                          <p style='margin:0 0 4px'><strong>Pago:</strong> " . ucfirst($sale->payment_method ?? '') . "</p>
                          <p style='margin:0'><strong>Total:</strong> {$fmt($sale->total_amount)}</p>
                        </div>
                        <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                          <thead>
                            <tr style='background:#f0f0f0'>
                              <th style='padding:8px;text-align:left'>Producto</th>
                              <th style='padding:8px;text-align:center'>Cant.</th>
                              <th style='padding:8px;text-align:right'>Precio</th>
                              <th style='padding:8px;text-align:right'>Total</th>
                            </tr>
                          </thead>
                          <tbody>{$itemsHtml}</tbody>
                        </table>
                        <p style='text-align:right;font-size:18px'><strong>Total: {$fmt($sale->total_amount)}</strong></p>
                        <hr style='border:none;border-top:1px solid #eee;margin:20px 0'>
                        <p style='color:#666;font-size:13px'>Puedes ver esta compra en tu historial de pedidos en motospa.com.co</p>
                      </div>
                    </div>";

                    Mail::html($html, function ($message) use ($customer, $saleRef) {
                        $message->to($customer->email, $customer->name)
                                ->subject("Comprobante de compra {$saleRef} - Moto Spa");
                    });
                }
            } catch (\Exception $mailEx) {
                // No bloquear si falla el correo
            }

            return response()->json([
                'success' => true,
                'message' => 'Venta registrada exitosamente',
                'data'    => $sale,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la venta: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $sale->load(['customer', 'user', 'saleItems.product']),
        ]);
    }

    public function cancel(Sale $sale): JsonResponse
    {
        if ($sale->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'La venta ya está cancelada',
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($sale->saleItems as $item) {
                Product::where('id', $item->product_id)
                    ->increment('stock_quantity', $item->quantity);
            }

            $sale->update(['status' => 'cancelled']);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venta cancelada. Stock restaurado.',
                'data'    => $sale->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la venta: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Sale $sale): JsonResponse
    {
        DB::beginTransaction();
        try {
            foreach ($sale->saleItems as $item) {
                Product::where('id', $item->product_id)
                    ->increment('stock_quantity', $item->quantity);
            }

            $sale->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venta eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la venta: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = $request->get('date_to',   now()->toDateString());

        $sales = Sale::where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo]);

        return response()->json([
            'success' => true,
            'data' => [
                'total_sales'   => (clone $sales)->count(),
                'total_revenue' => (clone $sales)->sum('total_amount'),
                'total_items'   => (clone $sales)->withCount('saleItems')->get()->sum('sale_items_count'),
                'by_payment_method' => (clone $sales)
                    ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
                    ->groupBy('payment_method')
                    ->get(),
            ],
        ]);
    }
}

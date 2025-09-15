<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Listar todos los pagos
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['sale.customer', 'user']);

        // Filtros
        if ($request->has('sale_id')) {
            $query->where('sale_id', $request->sale_id);
        }

        if ($request->has('payment_method')) {
            $query->byMethod($request->payment_method);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        if ($request->has('today') && $request->boolean('today')) {
            $query->today();
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'payment_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        return response()->json($payments);
    }

    /**
     * Crear un nuevo pago
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'payment_method' => 'required|in:cash,card,transfer,check,credit',
            'amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $sale = Sale::findOrFail($validated['sale_id']);

        // Verificar que la venta no esté cancelada
        if ($sale->status === 'cancelled') {
            return response()->json([
                'message' => 'No se pueden agregar pagos a una venta cancelada'
            ], 422);
        }

        // Verificar que el monto no exceda el saldo pendiente
        $pendingBalance = $sale->pendingBalance;
        if ($validated['amount'] > $pendingBalance) {
            return response()->json([
                'message' => "El monto del pago ({$validated['amount']}) excede el saldo pendiente ({$pendingBalance})"
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Crear el pago
            $payment = Payment::create([
                'sale_id' => $validated['sale_id'],
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'payment_date' => now(),
                'reference_number' => $validated['reference_number'],
                'notes' => $validated['notes'],
                'user_id' => auth()->id(),
                'status' => 'completed'
            ]);

            // Actualizar estado de pago de la venta
            $totalPaid = $sale->totalPaid + $validated['amount'];
            
            if ($totalPaid >= $sale->total_amount) {
                $sale->payment_status = 'paid';
            } elseif ($totalPaid > 0) {
                $sale->payment_status = 'partial';
            }
            
            $sale->save();

            DB::commit();

            $payment->load(['sale.customer', 'user']);

            return response()->json([
                'message' => 'Pago registrado exitosamente',
                'payment' => $payment
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mostrar un pago específico
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['sale.customer', 'user']);
        return response()->json($payment);
    }

    /**
     * Actualizar un pago (solo si está pendiente)
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden editar pagos pendientes'
            ], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,transfer,check,credit',
            'amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        // Verificar que el nuevo monto no exceda el saldo pendiente
        $sale = $payment->sale;
        $pendingBalance = $sale->pendingBalance + $payment->amount; // Incluir el monto actual del pago
        
        if ($validated['amount'] > $pendingBalance) {
            return response()->json([
                'message' => "El monto del pago ({$validated['amount']}) excede el saldo pendiente ({$pendingBalance})"
            ], 422);
        }

        $payment->update($validated);
        $payment->load(['sale.customer', 'user']);

        return response()->json([
            'message' => 'Pago actualizado exitosamente',
            'payment' => $payment
        ]);
    }

    /**
     * Completar un pago pendiente
     */
    public function complete(Payment $payment): JsonResponse
    {
        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden completar pagos pendientes'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $payment->complete();

            // Actualizar estado de pago de la venta
            $sale = $payment->sale;
            $totalPaid = $sale->totalPaid;
            
            if ($totalPaid >= $sale->total_amount) {
                $sale->payment_status = 'paid';
            } elseif ($totalPaid > 0) {
                $sale->payment_status = 'partial';
            }
            
            $sale->save();

            DB::commit();

            return response()->json([
                'message' => 'Pago completado exitosamente',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancelar un pago
     */
    public function cancel(Payment $payment): JsonResponse
    {
        if ($payment->status === 'completed') {
            return response()->json([
                'message' => 'No se pueden cancelar pagos completados'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $payment->cancel();

            // Actualizar estado de pago de la venta
            $sale = $payment->sale;
            $totalPaid = $sale->totalPaid;
            
            if ($totalPaid == 0) {
                $sale->payment_status = 'pending';
            } elseif ($totalPaid < $sale->total_amount) {
                $sale->payment_status = 'partial';
            }
            
            $sale->save();

            DB::commit();

            return response()->json([
                'message' => 'Pago cancelado exitosamente',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtener pagos de una venta
     */
    public function getBySale(Sale $sale): JsonResponse
    {
        $payments = $sale->payments()->with('user')->orderBy('payment_date', 'desc')->get();
        
        $summary = [
            'total_paid' => $sale->totalPaid,
            'pending_balance' => $sale->pendingBalance,
            'payment_status' => $sale->payment_status,
            'payments' => $payments
        ];

        return response()->json($summary);
    }

    /**
     * Obtener estadísticas de pagos
     */
    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now()->endOfMonth());

        $stats = [
            'total_payments' => Payment::completed()->byDateRange($dateFrom, $dateTo)->sum('amount'),
            'payments_count' => Payment::completed()->byDateRange($dateFrom, $dateTo)->count(),
            'today_payments' => Payment::completed()->today()->sum('amount'),
            'today_count' => Payment::completed()->today()->count(),
            'pending_payments' => Payment::where('status', 'pending')->sum('amount'),
            'failed_payments' => Payment::where('status', 'failed')->sum('amount')
        ];

        return response()->json($stats);
    }

    /**
     * Obtener pagos por método
     */
    public function paymentsByMethod(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now()->endOfMonth());

        $paymentsByMethod = Payment::completed()
            ->byDateRange($dateFrom, $dateTo)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get();

        return response()->json($paymentsByMethod);
    }

    /**
     * Obtener métodos de pago disponibles
     */
    public function getPaymentMethods(): JsonResponse
    {
        return response()->json(Payment::getPaymentMethods());
    }
}
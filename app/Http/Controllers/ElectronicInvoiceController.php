<?php

namespace App\Http\Controllers;

use App\Models\ElectronicInvoice;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ElectronicInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ElectronicInvoice::with(['sale.customer', 'sale.user']);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->get('document_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('cufe', 'like', "%{$search}%")
                  ->orWhereHas('sale.customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('document_number', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $invoices = $query->paginate($perPage);

        return response()->json($invoices);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'document_type' => 'required|string|in:invoice,credit_note,debit_note',
            'operation_type' => 'required|string|in:standard,export,exempt',
            'payment_form' => 'required|string|in:cash,credit,mixed',
            'payment_due_days' => 'nullable|integer|min:0|max:365',
            'observations' => 'nullable|string|max:1000'
        ]);

        // Verificar que la venta existe y no tiene factura electrónica
        $sale = Sale::find($validated['sale_id']);
        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        if ($sale->electronicInvoice) {
            return response()->json(['message' => 'La venta ya tiene una factura electrónica'], 400);
        }

        // Verificar que la venta requiere factura electrónica
        if (!$sale->requiresElectronicInvoice()) {
            return response()->json(['message' => 'La venta no requiere factura electrónica'], 400);
        }

        try {
            $invoice = ElectronicInvoice::create([
                'sale_id' => $validated['sale_id'],
                'document_type' => $validated['document_type'],
                'operation_type' => $validated['operation_type'],
                'payment_form' => $validated['payment_form'],
                'payment_due_days' => $validated['payment_due_days'] ?? 0,
                'observations' => $validated['observations'],
                'status' => ElectronicInvoice::STATUS_PENDING,
                'invoice_number' => $this->generateInvoiceNumber($validated['document_type']),
                'cufe' => $this->generateCUFE($sale, $validated),
                'issue_date' => now(),
                'due_date' => $this->calculateDueDate($validated['payment_form'], $validated['payment_due_days'] ?? 0)
            ]);

            // Actualizar la venta
            $sale->update([
                'requires_electronic_invoice' => true,
                'invoice_type' => $validated['document_type'],
                'operation_type' => $validated['operation_type'],
                'payment_form' => $validated['payment_form'],
                'payment_due_days' => $validated['payment_due_days'] ?? 0,
                'observations' => $validated['observations']
            ]);

            $invoice->load(['sale.customer', 'sale.user']);

            return response()->json([
                'message' => 'Factura electrónica creada exitosamente',
                'invoice' => $invoice
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating electronic invoice: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear la factura electrónica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ElectronicInvoice $electronicInvoice): JsonResponse
    {
        $electronicInvoice->load(['sale.customer', 'sale.user', 'sale.saleItems.product']);
        return response()->json($electronicInvoice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ElectronicInvoice $electronicInvoice): JsonResponse
    {
        // Solo permitir actualización si está en estado pendiente
        if ($electronicInvoice->status !== ElectronicInvoice::STATUS_PENDING) {
            return response()->json([
                'message' => 'No se puede actualizar una factura que no está en estado pendiente'
            ], 400);
        }

        $validated = $request->validate([
            'document_type' => 'required|string|in:invoice,credit_note,debit_note',
            'operation_type' => 'required|string|in:standard,export,exempt',
            'payment_form' => 'required|string|in:cash,credit,mixed',
            'payment_due_days' => 'nullable|integer|min:0|max:365',
            'observations' => 'nullable|string|max:1000'
        ]);

        try {
            $electronicInvoice->update([
                'document_type' => $validated['document_type'],
                'operation_type' => $validated['operation_type'],
                'payment_form' => $validated['payment_form'],
                'payment_due_days' => $validated['payment_due_days'] ?? 0,
                'observations' => $validated['observations'],
                'due_date' => $this->calculateDueDate($validated['payment_form'], $validated['payment_due_days'] ?? 0)
            ]);

            // Actualizar la venta relacionada
            $electronicInvoice->sale->update([
                'invoice_type' => $validated['document_type'],
                'operation_type' => $validated['operation_type'],
                'payment_form' => $validated['payment_form'],
                'payment_due_days' => $validated['payment_due_days'] ?? 0,
                'observations' => $validated['observations']
            ]);

            $electronicInvoice->load(['sale.customer', 'sale.user']);

            return response()->json([
                'message' => 'Factura electrónica actualizada exitosamente',
                'invoice' => $electronicInvoice
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating electronic invoice: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar la factura electrónica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ElectronicInvoice $electronicInvoice): JsonResponse
    {
        // Solo permitir eliminación si está en estado pendiente
        if ($electronicInvoice->status !== ElectronicInvoice::STATUS_PENDING) {
            return response()->json([
                'message' => 'No se puede eliminar una factura que no está en estado pendiente'
            ], 400);
        }

        try {
            // Actualizar la venta para quitar la referencia
            $electronicInvoice->sale->update([
                'requires_electronic_invoice' => false,
                'invoice_type' => null,
                'operation_type' => null,
                'payment_form' => null,
                'payment_due_days' => null
            ]);

            $electronicInvoice->delete();

            return response()->json([
                'message' => 'Factura electrónica eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting electronic invoice: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar la factura electrónica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar factura a DIAN
     */
    public function sendToDian(ElectronicInvoice $electronicInvoice): JsonResponse
    {
        if ($electronicInvoice->status !== ElectronicInvoice::STATUS_PENDING) {
            return response()->json([
                'message' => 'Solo se pueden enviar facturas en estado pendiente'
            ], 400);
        }

        try {
            // Validar campos requeridos
            $validation = $electronicInvoice->validateDianFields();
            if (!$validation['valid']) {
                return response()->json([
                    'message' => 'Faltan campos requeridos para DIAN',
                    'errors' => $validation['errors']
                ], 400);
            }

            // Simular envío a DIAN (aquí iría la integración real)
            $this->simulateDianSubmission($electronicInvoice);

            return response()->json([
                'message' => 'Factura enviada a DIAN exitosamente',
                'invoice' => $electronicInvoice->fresh(['sale.customer', 'sale.user'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending invoice to DIAN: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al enviar factura a DIAN',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar PDF de la factura
     */
    public function generatePdf(ElectronicInvoice $electronicInvoice): JsonResponse
    {
        try {
            // Aquí iría la lógica para generar el PDF
            // Por ahora simulamos la generación
            $pdfPath = 'invoices/pdf/' . $electronicInvoice->invoice_number . '.pdf';
            
            // Simular contenido PDF
            $pdfContent = $this->generatePdfContent($electronicInvoice);
            Storage::disk('public')->put($pdfPath, $pdfContent);

            $electronicInvoice->update([
                'pdf_path' => $pdfPath,
                'pdf_generated_at' => now()
            ]);

            return response()->json([
                'message' => 'PDF generado exitosamente',
                'pdf_url' => Storage::disk('public')->url($pdfPath)
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating PDF: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al generar PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar XML de la factura
     */
    public function generateXml(ElectronicInvoice $electronicInvoice): JsonResponse
    {
        try {
            // Aquí iría la lógica para generar el XML según estándares DIAN
            $xmlPath = 'invoices/xml/' . $electronicInvoice->invoice_number . '.xml';
            
            $xmlContent = $this->generateXmlContent($electronicInvoice);
            Storage::disk('public')->put($xmlPath, $xmlContent);

            $electronicInvoice->update([
                'xml_path' => $xmlPath,
                'xml_generated_at' => now()
            ]);

            return response()->json([
                'message' => 'XML generado exitosamente',
                'xml_url' => Storage::disk('public')->url($xmlPath)
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating XML: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al generar XML',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de facturación
     */
    public function statistics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now()->endOfMonth());

        $stats = [
            'total_invoices' => ElectronicInvoice::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'pending_invoices' => ElectronicInvoice::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', ElectronicInvoice::STATUS_PENDING)->count(),
            'sent_invoices' => ElectronicInvoice::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', ElectronicInvoice::STATUS_SENT)->count(),
            'accepted_invoices' => ElectronicInvoice::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', ElectronicInvoice::STATUS_ACCEPTED)->count(),
            'rejected_invoices' => ElectronicInvoice::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', ElectronicInvoice::STATUS_REJECTED)->count(),
            'total_amount' => ElectronicInvoice::whereBetween('created_at', [$dateFrom, $dateTo])
                ->whereHas('sale')
                ->with('sale')
                ->get()
                ->sum('sale.total_amount')
        ];

        return response()->json($stats);
    }

    /**
     * Métodos privados auxiliares
     */
    private function generateInvoiceNumber(string $documentType): string
    {
        $prefix = match($documentType) {
            'invoice' => 'FE',
            'credit_note' => 'NC',
            'debit_note' => 'ND',
            default => 'FE'
        };

        $lastNumber = ElectronicInvoice::where('document_type', $documentType)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->value('invoice_number');

        if ($lastNumber) {
            $number = (int) substr($lastNumber, strlen($prefix)) + 1;
        } else {
            $number = 1;
        }

        return $prefix . str_pad($number, 8, '0', STR_PAD_LEFT);
    }

    private function generateCUFE(Sale $sale, array $data): string
    {
        // Generar CUFE según especificaciones DIAN
        $string = $sale->sale_number . 
                 $sale->created_at->format('Y-m-d') . 
                 $sale->total_amount . 
                 ($sale->customer->document_number ?? 'N/A') . 
                 $data['document_type'];
        
        return strtoupper(hash('sha256', $string));
    }

    private function calculateDueDate(string $paymentForm, int $paymentDueDays): Carbon
    {
        if ($paymentForm === 'cash') {
            return now();
        }

        return now()->addDays($paymentDueDays);
    }

    private function simulateDianSubmission(ElectronicInvoice $invoice): void
    {
        // Simular proceso de envío a DIAN
        $invoice->markAsSent();
        
        // Simular respuesta de DIAN (90% aceptadas, 10% rechazadas)
        if (rand(1, 10) <= 9) {
            $invoice->markAsAccepted();
        } else {
            $invoice->markAsRejected(['Error simulado de validación DIAN']);
        }
    }

    private function generatePdfContent(ElectronicInvoice $invoice): string
    {
        // Simular contenido PDF básico
        return "PDF Content for Invoice: {$invoice->invoice_number}\nCUFE: {$invoice->cufe}\nDate: {$invoice->issue_date}";
    }

    private function generateXmlContent(ElectronicInvoice $invoice): string
    {
        // Simular contenido XML básico según estándares DIAN
        return "<?xml version='1.0' encoding='UTF-8'?>\n<Invoice>\n  <Number>{$invoice->invoice_number}</Number>\n  <CUFE>{$invoice->cufe}</CUFE>\n  <IssueDate>{$invoice->issue_date}</IssueDate>\n</Invoice>";
    }
}

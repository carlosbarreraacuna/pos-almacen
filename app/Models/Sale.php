<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_number',
        'customer_id',
        'user_id',
        'sale_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'payment_reference',
        'notes',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        // Nuevos campos DIAN
        'requires_electronic_invoice',
        'invoice_type',
        'operation_type',
        'payment_form',
        'payment_due_days',
        'observations',
        'is_electronic_invoice_sent',
        'electronic_invoice_sent_at',
        'dian_status',
        'dian_errors'
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'invoice_date' => 'datetime',
        'due_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        // Nuevos casts DIAN
        'requires_electronic_invoice' => 'boolean',
        'is_electronic_invoice_sent' => 'boolean',
        'electronic_invoice_sent_at' => 'datetime'
    ];

    // Estados de la venta
    const STATUS_DRAFT = 'draft';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    // Métodos de pago
    const PAYMENT_CASH = 'cash';
    const PAYMENT_CARD = 'card';
    const PAYMENT_TRANSFER = 'transfer';
    const PAYMENT_CHECK = 'check';
    const PAYMENT_CREDIT = 'credit';

    // Estados de pago
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_PARTIAL = 'partial';
    const PAYMENT_OVERDUE = 'overdue';

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (empty($sale->sale_number)) {
                $sale->sale_number = self::generateSaleNumber();
            }
        });
    }

    /**
     * Relación con cliente
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relación con usuario (vendedor)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con items de venta
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Relación con pagos
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Relación con factura electrónica
     */
    public function electronicInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ElectronicInvoice::class);
    }

    /**
     * Relación con historial de ventas
     */
    public function saleHistories(): HasMany
    {
        return $this->hasMany(SaleHistory::class, 'customer_id', 'customer_id');
    }

    /**
     * Scope para ventas completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope para ventas por fecha
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('sale_date', [$startDate, $endDate]);
    }

    /**
     * Scope para ventas de hoy
     */
    public function scopeToday($query)
    {
        return $query->whereDate('sale_date', today());
    }

    /**
     * Scope para ventas por método de pago
     */
    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope para ventas por estado de pago
     */
    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    /**
     * Scope para ventas por vendedor
     */
    public function scopeBySeller($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Generar número de venta
     */
    public static function generateSaleNumber(): string
    {
        $prefix = 'VTA';
        $date = now()->format('Ymd');
        $lastSale = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastSale ? (int) substr($lastSale->sale_number, -4) + 1 : 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generar número de factura
     */
    public function generateInvoiceNumber(): string
    {
        if ($this->invoice_number) {
            return $this->invoice_number;
        }

        $prefix = 'FAC';
        $date = now()->format('Ymd');
        $lastInvoice = self::whereNotNull('invoice_number')
            ->whereDate('invoice_date', today())
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastInvoice ? (int) substr($lastInvoice->invoice_number, -4) + 1 : 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calcular totales
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->saleItems->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });

        $this->subtotal = $subtotal;
        $this->tax_amount = $subtotal * 0.19; // 19% IVA por defecto
        $this->total_amount = $this->subtotal + $this->tax_amount - $this->discount_amount;
    }

    /**
     * Completar venta
     */
    public function complete(): bool
    {
        if ($this->status !== self::STATUS_DRAFT) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;
        $this->sale_date = now();
        
        // Generar factura si es necesario
        if ($this->payment_method !== self::PAYMENT_CASH) {
            $this->invoice_number = $this->generateInvoiceNumber();
            $this->invoice_date = now();
            
            // Calcular fecha de vencimiento según términos del cliente
            if ($this->customer && $this->customer->payment_terms !== Customer::PAYMENT_CASH) {
                $days = match($this->customer->payment_terms) {
                    Customer::PAYMENT_NET_15 => 15,
                    Customer::PAYMENT_NET_30 => 30,
                    Customer::PAYMENT_NET_60 => 60,
                    default => 0
                };
                $this->due_date = now()->addDays($days);
            }
        }

        return $this->save();
    }

    /**
     * Cancelar venta
     */
    public function cancel(): bool
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Verificar si la venta está vencida
     */
    public function isOverdue(): bool
    {
        return $this->due_date && 
               $this->due_date->isPast() && 
               $this->payment_status !== self::PAYMENT_PAID;
    }

    /**
     * Obtener total pagado
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Obtener saldo pendiente
     */
    public function getPendingBalanceAttribute(): float
    {
        return $this->total_amount - $this->total_paid;
    }

    /**
     * Verificar si está completamente pagada
     */
    public function isFullyPaid(): bool
    {
        return $this->pending_balance <= 0;
    }

    /**
     * Obtener estados de venta
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_COMPLETED => 'Completada',
            self::STATUS_CANCELLED => 'Cancelada',
            self::STATUS_REFUNDED => 'Reembolsada'
        ];
    }

    /**
     * Obtener métodos de pago
     */
    public static function getPaymentMethods(): array
    {
        return [
            self::PAYMENT_CASH => 'Efectivo',
            self::PAYMENT_CARD => 'Tarjeta',
            self::PAYMENT_TRANSFER => 'Transferencia',
            self::PAYMENT_CHECK => 'Cheque',
            self::PAYMENT_CREDIT => 'Crédito'
        ];
    }

    /**
     * Verifica si la venta requiere facturación electrónica
     */
    public function requiresElectronicInvoice(): bool
    {
        return $this->requires_electronic_invoice || $this->total_amount >= config('dian.electronic_invoice_threshold', 1000000);
    }

    /**
     * Crea la factura electrónica para esta venta
     */
    public function createElectronicInvoice(): ElectronicInvoice
    {
        if ($this->electronicInvoice) {
            return $this->electronicInvoice;
        }

        $invoice = new ElectronicInvoice([
            'sale_id' => $this->id,
            'issue_date' => $this->sale_date,
            'due_date' => $this->due_date,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'customer_document_type' => $this->customer->document_type ?? 'CC',
            'customer_document_number' => $this->customer->document_number ?? '',
            'customer_name' => $this->customer->name ?? '',
            'customer_address' => $this->customer->address ?? '',
            'customer_city' => $this->customer->city ?? '',
            'customer_department' => $this->customer->department ?? '',
            'customer_phone' => $this->customer->phone ?? '',
            'customer_email' => $this->customer->email ?? '',
            // Datos del emisor desde configuración
            'issuer_nit' => config('dian.issuer.nit'),
            'issuer_name' => config('dian.issuer.name'),
            'issuer_address' => config('dian.issuer.address'),
            'issuer_city' => config('dian.issuer.city'),
            'issuer_department' => config('dian.issuer.department'),
            'issuer_phone' => config('dian.issuer.phone'),
            'issuer_email' => config('dian.issuer.email')
        ]);

        $invoice->generateInvoiceNumber();
        $invoice->generateCufe();
        $invoice->save();

        return $invoice;
    }

    /**
     * Actualiza el historial de ventas
     */
    public function updateSaleHistory(): void
    {
        if ($this->status === self::STATUS_COMPLETED && $this->customer_id) {
            SaleHistory::updateFromSale($this);
        }
    }

    /**
     * Obtiene recomendaciones basadas en el historial
     */
    public function getRecommendations(): \Illuminate\Support\Collection
    {
        if (!$this->customer_id) {
            return collect([]);
        }

        return SaleHistory::getRecommendationsForCustomer($this->customer_id);
    }

    /**
     * Verifica si puede generar factura electrónica
     */
    public function canGenerateElectronicInvoice(): bool
    {
        return $this->status === self::STATUS_COMPLETED && 
               $this->requiresElectronicInvoice() && 
               !$this->is_electronic_invoice_sent;
    }

    /**
      * Marca como enviada a DIAN
      */
     public function markElectronicInvoiceAsSent(): void
     {
         $this->update([
             'is_electronic_invoice_sent' => true,
             'electronic_invoice_sent_at' => now(),
             'dian_status' => 'sent'
         ]);
     }

     /**
     * Obtener estados de pago
     */
    public static function getPaymentStatuses(): array
    {
        return [
            self::PAYMENT_PENDING => 'Pendiente',
            self::PAYMENT_PAID => 'Pagado',
            self::PAYMENT_PARTIAL => 'Parcial',
            self::PAYMENT_OVERDUE => 'Vencido'
        ];
    }
}
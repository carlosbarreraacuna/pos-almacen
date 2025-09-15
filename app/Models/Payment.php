<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'payment_method',
        'amount',
        'payment_date',
        'reference_number',
        'notes',
        'user_id',
        'status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Métodos de pago
    const METHOD_CASH = 'cash';
    const METHOD_CARD = 'card';
    const METHOD_TRANSFER = 'transfer';
    const METHOD_CHECK = 'check';
    const METHOD_CREDIT = 'credit';

    // Estados del pago
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Relación con venta
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Relación con usuario que procesó el pago
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para pagos completados
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope para pagos por método
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope para pagos por fecha
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    /**
     * Scope para pagos de hoy
     */
    public function scopeToday($query)
    {
        return $query->whereDate('payment_date', today());
    }

    /**
     * Verificar si el pago está completado
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Verificar si el pago está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Completar el pago
     */
    public function complete(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;
        $this->payment_date = now();
        
        return $this->save();
    }

    /**
     * Cancelar el pago
     */
    public function cancel(): bool
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Marcar como fallido
     */
    public function markAsFailed(): bool
    {
        $this->status = self::STATUS_FAILED;
        return $this->save();
    }

    /**
     * Obtener métodos de pago
     */
    public static function getPaymentMethods(): array
    {
        return [
            self::METHOD_CASH => 'Efectivo',
            self::METHOD_CARD => 'Tarjeta',
            self::METHOD_TRANSFER => 'Transferencia',
            self::METHOD_CHECK => 'Cheque',
            self::METHOD_CREDIT => 'Crédito'
        ];
    }

    /**
     * Obtener estados de pago
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_FAILED => 'Fallido',
            self::STATUS_CANCELLED => 'Cancelado'
        ];
    }

    /**
     * Obtener nombre del método de pago
     */
    public function getPaymentMethodNameAttribute(): string
    {
        return self::getPaymentMethods()[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Obtener nombre del estado
     */
    public function getStatusNameAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }
}
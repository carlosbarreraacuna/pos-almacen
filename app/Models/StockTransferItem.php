<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'quantity',
        'quantity_received',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_received' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con transferencia de stock
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * Relación con producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Obtener cantidad pendiente
     */
    public function getPendingQuantityAttribute(): int
    {
        return $this->quantity - ($this->quantity_received ?? 0);
    }

    /**
     * Verificar si está completamente recibido
     */
    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity;
    }

    /**
     * Verificar si está parcialmente recibido
     */
    public function isPartiallyReceived(): bool
    {
        return $this->quantity_received > 0 && $this->quantity_received < $this->quantity;
    }

    /**
     * Recibir cantidad
     */
    public function receive(int $quantity): bool
    {
        if ($quantity <= 0 || $quantity > $this->pendingQuantity) {
            return false;
        }

        $this->quantity_received = ($this->quantity_received ?? 0) + $quantity;
        return $this->save();
    }

    /**
     * Obtener porcentaje recibido
     */
    public function getReceivedPercentageAttribute(): float
    {
        if ($this->quantity == 0) {
            return 0;
        }
        return (($this->quantity_received ?? 0) / $this->quantity) * 100;
    }

    /**
     * Obtener estado del item
     */
    public function getStatusAttribute(): string
    {
        if ($this->quantity_received == 0) {
            return 'pending';
        } elseif ($this->isFullyReceived()) {
            return 'completed';
        } else {
            return 'partial';
        }
    }

    /**
     * Obtener nombre del estado
     */
    public function getStatusNameAttribute(): string
    {
        $statuses = [
            'pending' => 'Pendiente',
            'partial' => 'Parcial',
            'completed' => 'Completado'
        ];

        return $statuses[$this->status] ?? $this->status;
    }
}
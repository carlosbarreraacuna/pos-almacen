<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'current_quantity',
        'adjusted_quantity',
        'quantity_adjustment',
        'unit_cost',
        'value_adjustment',
        'reason',
        'notes'
    ];

    protected $casts = [
        'current_quantity' => 'integer',
        'adjusted_quantity' => 'integer',
        'quantity_adjustment' => 'integer',
        'unit_cost' => 'decimal:2',
        'value_adjustment' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con ajuste de stock
     */
    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    /**
     * Relación con producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Obtener tipo de ajuste basado en la cantidad
     */
    public function getAdjustmentTypeAttribute(): string
    {
        if ($this->quantity_adjustment > 0) {
            return 'increase';
        } elseif ($this->quantity_adjustment < 0) {
            return 'decrease';
        } else {
            return 'no_change';
        }
    }

    /**
     * Obtener nombre del tipo de ajuste
     */
    public function getAdjustmentTypeNameAttribute(): string
    {
        $types = [
            'increase' => 'Incremento',
            'decrease' => 'Decremento',
            'no_change' => 'Sin cambio'
        ];

        return $types[$this->adjustmentType] ?? $this->adjustmentType;
    }

    /**
     * Obtener porcentaje de variación
     */
    public function getVariancePercentageAttribute(): float
    {
        if ($this->current_quantity == 0) {
            return $this->adjusted_quantity > 0 ? 100 : 0;
        }

        return (($this->adjusted_quantity - $this->current_quantity) / $this->current_quantity) * 100;
    }

    /**
     * Verificar si es un ajuste significativo
     */
    public function isSignificantAdjustment(float $threshold = 10.0): bool
    {
        return abs($this->variancePercentage) >= $threshold;
    }

    /**
     * Calcular valor del ajuste
     */
    public function calculateValueAdjustment(): void
    {
        $this->value_adjustment = $this->quantity_adjustment * $this->unit_cost;
    }

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            // Calcular ajuste de cantidad
            $item->quantity_adjustment = $item->adjusted_quantity - $item->current_quantity;
            
            // Calcular valor del ajuste si hay costo unitario
            if ($item->unit_cost) {
                $item->calculateValueAdjustment();
            }
        });
    }
}
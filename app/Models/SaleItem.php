<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con venta
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Relación con producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calcular subtotal (sin impuestos ni descuentos)
     */
    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Calcular total con descuentos e impuestos
     */
    public function calculateTotal(): void
    {
        $subtotal = $this->subtotal;
        $discountedAmount = $subtotal - $this->discount_amount;
        $this->tax_amount = $discountedAmount * ($this->tax_rate / 100);
        $this->total_amount = $discountedAmount + $this->tax_amount;
    }

    /**
     * Obtener precio unitario con descuento
     */
    public function getDiscountedUnitPriceAttribute(): float
    {
        $discountPerUnit = $this->discount_amount / $this->quantity;
        return $this->unit_price - $discountPerUnit;
    }

    /**
     * Obtener porcentaje de descuento
     */
    public function getDiscountPercentageAttribute(): float
    {
        if ($this->subtotal == 0) {
            return 0;
        }
        return ($this->discount_amount / $this->subtotal) * 100;
    }

    /**
     * Verificar si el item tiene descuento
     */
    public function hasDiscount(): bool
    {
        return $this->discount_amount > 0;
    }

    /**
     * Verificar si el item tiene impuestos
     */
    public function hasTax(): bool
    {
        return $this->tax_rate > 0;
    }

    /**
     * Obtener margen de ganancia
     */
    public function getProfitMarginAttribute(): float
    {
        if (!$this->product || $this->unit_price == 0) {
            return 0;
        }
        
        $costPrice = $this->product->cost_price;
        $profit = $this->unit_price - $costPrice;
        
        return ($profit / $this->unit_price) * 100;
    }

    /**
     * Obtener ganancia total del item
     */
    public function getTotalProfitAttribute(): float
    {
        if (!$this->product) {
            return 0;
        }
        
        $costPrice = $this->product->cost_price;
        $profit = $this->unit_price - $costPrice;
        
        return $profit * $this->quantity;
    }

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($saleItem) {
            $saleItem->calculateTotal();
        });
    }
}
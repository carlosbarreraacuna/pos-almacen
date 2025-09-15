<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_received',
        'quantity_pending',
        'unit_of_measure',
        'unit_cost',
        'discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'line_total',
        'supplier_product_code',
        'description',
        'notes',
        'expected_delivery_date',
        'actual_delivery_date'
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'quantity_pending' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date'
    ];

    // Relaciones
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Métodos de utilidad
    public function calculateLineTotal()
    {
        $subtotal = $this->quantity_ordered * $this->unit_cost;
        $discountAmount = $this->discount_amount ?: ($subtotal * $this->discount_percentage / 100);
        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = $taxableAmount * $this->tax_percentage / 100;
        
        $this->line_total = $subtotal - $discountAmount + $taxAmount;
        $this->tax_amount = $taxAmount;
        $this->discount_amount = $discountAmount;
        
        return $this->line_total;
    }

    public function updatePendingQuantity()
    {
        $this->quantity_pending = $this->quantity_ordered - $this->quantity_received;
        $this->save();
    }

    public function isFullyReceived()
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }
}

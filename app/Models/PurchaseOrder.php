<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'order_number',
        'supplier_id',
        'warehouse_id',
        'user_id',
        'status',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'shipping_cost',
        'total_amount',
        'currency',
        'payment_terms',
        'delivery_terms',
        'notes',
        'internal_notes',
        'approved_by',
        'approved_at',
        'received_by',
        'received_at'
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2'
    ];

    // Relaciones
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // Métodos de utilidad
    public function calculateTotal()
    {
        $this->subtotal = $this->items->sum('line_total');
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->shipping_cost - $this->discount_amount;
        $this->save();
    }

    public function isEditable()
    {
        return in_array($this->status, ['draft', 'pending']);
    }
}

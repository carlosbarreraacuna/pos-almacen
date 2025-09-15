<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class SaleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'customer_id',
        'items',
        'payment_method',
        'discount_percentage',
        'tax_rate',
        'notes',
        'is_active',
        'usage_count',
        'last_used_at'
    ];

    protected $casts = [
        'items' => 'array',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con el usuario que creó la plantilla
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el cliente (opcional)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope para plantillas activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para plantillas del usuario
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para plantillas más usadas
     */
    public function scopeMostUsed($query, $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    /**
     * Incrementa el contador de uso
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => Carbon::now()]);
    }

    /**
     * Calcula el total estimado de la plantilla
     */
    public function getEstimatedTotalAttribute()
    {
        $subtotal = 0;
        
        foreach ($this->items as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $subtotal += $product->price * $item['quantity'];
            }
        }
        
        $discountAmount = $subtotal * ($this->discount_percentage / 100);
        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = $taxableAmount * ($this->tax_rate / 100);
        
        return $subtotal - $discountAmount + $taxAmount;
    }

    /**
     * Obtiene los productos de la plantilla con sus detalles
     */
    public function getItemsWithProductsAttribute()
    {
        $items = [];
        
        foreach ($this->items as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $items[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $product->price * $item['quantity']
                ];
            }
        }
        
        return $items;
    }

    /**
     * Crea una venta basada en esta plantilla
     */
    public function createSale($customerId = null, $additionalData = [])
    {
        $saleData = [
            'customer_id' => $customerId ?? $this->customer_id,
            'user_id' => auth()->id(),
            'payment_method' => $this->payment_method,
            'discount_amount' => 0,
            'notes' => $this->notes,
            'status' => Sale::STATUS_DRAFT
        ];
        
        $saleData = array_merge($saleData, $additionalData);
        
        $sale = Sale::create($saleData);
        
        // Agregar items
        foreach ($this->items as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $sale->saleItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'discount_percentage' => $this->discount_percentage,
                    'tax_rate' => $this->tax_rate
                ]);
            }
        }
        
        // Incrementar uso de la plantilla
        $this->incrementUsage();
        
        return $sale;
    }
}
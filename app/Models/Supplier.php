<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'code',
        'type',
        'business_name',
        'trade_name',
        'tax_id',
        'contact_person',
        'email',
        'phone',
        'mobile',
        'website',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'payment_terms',
        'credit_limit',
        'currency',
        'lead_time_days',
        'minimum_order_amount',
        'discount_percentage',
        'is_active',
        'is_preferred',
        'rating',
        'notes'
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'rating' => 'decimal:1',
        'is_active' => 'boolean',
        'is_preferred' => 'boolean'
    ];

    // Relaciones
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->trade_name ?: $this->business_name;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_id',
        'customer_type',
        'credit_limit',
        'payment_terms',
        'discount_percentage',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Tipos de cliente
    const TYPE_INDIVIDUAL = 'individual';
    const TYPE_BUSINESS = 'business';
    const TYPE_WHOLESALE = 'wholesale';
    const TYPE_RETAIL = 'retail';

    // Términos de pago
    const PAYMENT_CASH = 'cash';
    const PAYMENT_CREDIT = 'credit';
    const PAYMENT_NET_15 = 'net_15';
    const PAYMENT_NET_30 = 'net_30';
    const PAYMENT_NET_60 = 'net_60';

    /**
     * Relación con ventas
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Scope para clientes activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para buscar clientes
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('tax_id', 'like', "%{$search}%");
        });
    }

    /**
     * Scope por tipo de cliente
     */
    public function scopeByType($query, $type)
    {
        return $query->where('customer_type', $type);
    }

    /**
     * Obtener nombre completo con tipo
     */
    public function getFullNameAttribute(): string
    {
        $type = ucfirst($this->customer_type);
        return "{$this->name} ({$type})";
    }

    /**
     * Obtener dirección completa
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Calcular total de ventas
     */
    public function getTotalSalesAttribute(): float
    {
        return $this->sales()->sum('total_amount');
    }

    /**
     * Calcular número de ventas
     */
    public function getSalesCountAttribute(): int
    {
        return $this->sales()->count();
    }

    /**
     * Calcular promedio de compra
     */
    public function getAveragePurchaseAttribute(): float
    {
        $salesCount = $this->sales_count;
        return $salesCount > 0 ? $this->total_sales / $salesCount : 0;
    }

    /**
     * Verificar si el cliente tiene crédito disponible
     */
    public function hasAvailableCredit(float $amount = 0): bool
    {
        if ($this->payment_terms === self::PAYMENT_CASH) {
            return true;
        }

        $pendingAmount = $this->sales()
            ->where('payment_status', 'pending')
            ->sum('total_amount');

        return ($pendingAmount + $amount) <= $this->credit_limit;
    }

    /**
     * Obtener crédito disponible
     */
    public function getAvailableCreditAttribute(): float
    {
        if ($this->payment_terms === self::PAYMENT_CASH) {
            return 0;
        }

        $pendingAmount = $this->sales()
            ->where('payment_status', 'pending')
            ->sum('total_amount');

        return max(0, $this->credit_limit - $pendingAmount);
    }

    /**
     * Obtener tipos de cliente
     */
    public static function getCustomerTypes(): array
    {
        return [
            self::TYPE_INDIVIDUAL => 'Individual',
            self::TYPE_BUSINESS => 'Empresa',
            self::TYPE_WHOLESALE => 'Mayorista',
            self::TYPE_RETAIL => 'Minorista'
        ];
    }

    /**
     * Obtener términos de pago
     */
    public static function getPaymentTerms(): array
    {
        return [
            self::PAYMENT_CASH => 'Contado',
            self::PAYMENT_CREDIT => 'Crédito',
            self::PAYMENT_NET_15 => 'Neto 15 días',
            self::PAYMENT_NET_30 => 'Neto 30 días',
            self::PAYMENT_NET_60 => 'Neto 60 días'
        ];
    }

    /**
     * Verificar si es cliente frecuente
     */
    public function isFrequentCustomer(): bool
    {
        return $this->sales_count >= 10;
    }

    /**
     * Verificar si es cliente VIP
     */
    public function isVipCustomer(): bool
    {
        return $this->total_sales >= 10000;
    }
}
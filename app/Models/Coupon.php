<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'free_product_id',
        'min_purchase',
        'max_discount',
        'valid_from',
        'valid_until',
        'usage_limit',
        'used_count',
        'is_active',
        'applicable_products',
        'applicable_categories',
        'customer_restrictions',
        'created_by'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'applicable_products' => 'array',
        'applicable_categories' => 'array',
        'customer_restrictions' => 'array',
    ];

    // Relaciones
    public function freeProduct()
    {
        return $this->belongsTo(Product::class, 'free_product_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Métodos de validación
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();
        if ($now->lt($this->valid_from) || $now->gt($this->valid_until)) {
            return false;
        }

        if ($this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function canBeAppliedToOrder(float $orderTotal, array $productIds = []): array
    {
        $errors = [];

        if (!$this->isValid()) {
            if (!$this->is_active) {
                $errors[] = 'Este cupón está desactivado';
            } elseif (Carbon::now()->lt($this->valid_from)) {
                $errors[] = 'Este cupón será válido desde ' . $this->valid_from->format('d/m/Y H:i');
            } elseif (Carbon::now()->gt($this->valid_until)) {
                $errors[] = 'Este cupón venció el ' . $this->valid_until->format('d/m/Y H:i');
            } elseif ($this->used_count >= $this->usage_limit) {
                $errors[] = 'Este cupón ha alcanzado su límite de uso';
            }
        }

        if ($orderTotal < $this->min_purchase) {
            $errors[] = 'Compra mínima requerida: $' . number_format($this->min_purchase, 0, ',', '.');
        }

        // Validar productos aplicables
        if (!empty($this->applicable_products) && !empty($productIds)) {
            $hasApplicableProduct = !empty(array_intersect($this->applicable_products, $productIds));
            if (!$hasApplicableProduct) {
                $errors[] = 'Este cupón no es aplicable a los productos seleccionados';
            }
        }

        return $errors;
    }

    public function calculateDiscount(float $orderTotal): float
    {
        $discount = 0;

        switch ($this->type) {
            case 'percentage':
                $discount = $orderTotal * ($this->value / 100);
                if ($this->max_discount && $discount > $this->max_discount) {
                    $discount = $this->max_discount;
                }
                break;

            case 'fixed':
                $discount = min($this->value, $orderTotal);
                break;

            case 'free_product':
                // El descuento sería el precio del producto gratis
                // Esto se manejaría en el controlador
                $discount = 0;
                break;
        }

        return round($discount, 2);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        $now = Carbon::now();
        return $query->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where('valid_until', '>=', $now)
            ->whereColumn('used_count', '<', 'usage_limit');
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }
}

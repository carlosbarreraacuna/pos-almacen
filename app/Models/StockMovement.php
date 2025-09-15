<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reference_type',
        'reference_id',
        'notes',
        'user_id',
        'location_id'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer'
    ];

    // Tipos de movimiento
    const TYPE_SALE = 'sale';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_RETURN = 'return';
    const TYPE_DAMAGE = 'damage';
    const TYPE_LOSS = 'loss';

    // Relaciones
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeIncoming($query)
    {
        return $query->whereIn('type', [self::TYPE_PURCHASE, self::TYPE_RETURN, self::TYPE_ADJUSTMENT])
                    ->where('quantity', '>', 0);
    }

    public function scopeOutgoing($query)
    {
        return $query->whereIn('type', [self::TYPE_SALE, self::TYPE_DAMAGE, self::TYPE_LOSS, self::TYPE_TRANSFER])
                    ->where('quantity', '<', 0);
    }

    // Métodos auxiliares
    public function getMovementTypeAttribute()
    {
        $types = [
            self::TYPE_SALE => 'Venta',
            self::TYPE_PURCHASE => 'Compra',
            self::TYPE_ADJUSTMENT => 'Ajuste',
            self::TYPE_TRANSFER => 'Transferencia',
            self::TYPE_RETURN => 'Devolución',
            self::TYPE_DAMAGE => 'Daño',
            self::TYPE_LOSS => 'Pérdida'
        ];

        return $types[$this->type] ?? $this->type;
    }

    public function isIncoming()
    {
        return in_array($this->type, [self::TYPE_PURCHASE, self::TYPE_RETURN]) || 
               ($this->type === self::TYPE_ADJUSTMENT && $this->quantity > 0);
    }

    public function isOutgoing()
    {
        return in_array($this->type, [self::TYPE_SALE, self::TYPE_DAMAGE, self::TYPE_LOSS, self::TYPE_TRANSFER]) ||
               ($this->type === self::TYPE_ADJUSTMENT && $this->quantity < 0);
    }

    public function getQuantityChangeAttribute()
    {
        return $this->new_stock - $this->previous_stock;
    }

    public static function getMovementTypes()
    {
        return [
            self::TYPE_SALE => 'Venta',
            self::TYPE_PURCHASE => 'Compra',
            self::TYPE_ADJUSTMENT => 'Ajuste',
            self::TYPE_TRANSFER => 'Transferencia',
            self::TYPE_RETURN => 'Devolución',
            self::TYPE_DAMAGE => 'Daño',
            self::TYPE_LOSS => 'Pérdida'
        ];
    }
}
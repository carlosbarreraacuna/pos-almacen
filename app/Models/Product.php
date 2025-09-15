<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'barcode',
        'category_id',
        'brand_id',
        'unit_price',
        'cost_price',
        'stock_quantity',
        'min_stock_level',
        'max_stock_level',
        'unit_of_measure',
        'weight',
        'dimensions',
        'image_url',
        'is_active',
        'tax_rate',
        'supplier_id',
        'location_id'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer',
        'weight' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'dimensions' => 'json'
    ];

    // Relaciones
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_level');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', 0);
    }

    // Métodos auxiliares
    public function isLowStock()
    {
        return $this->stock_quantity <= $this->min_stock_level;
    }

    public function isOutOfStock()
    {
        return $this->stock_quantity <= 0;
    }

    public function updateStock($quantity, $type = 'sale')
    {
        if ($type === 'sale') {
            $this->stock_quantity -= $quantity;
        } elseif ($type === 'purchase') {
            $this->stock_quantity += $quantity;
        } elseif ($type === 'adjustment') {
            $this->stock_quantity = $quantity;
        }
        
        $this->save();
        
        // Registrar movimiento de stock
        StockMovement::create([
            'product_id' => $this->id,
            'type' => $type,
            'quantity' => $quantity,
            'previous_stock' => $this->getOriginal('stock_quantity'),
            'new_stock' => $this->stock_quantity,
            'reference_type' => ucfirst($type),
            'notes' => "Stock updated via {$type}",
            'user_id' => auth()->id()
        ]);
    }

    public function getProfitMarginAttribute()
    {
        if ($this->cost_price > 0) {
            return (($this->unit_price - $this->cost_price) / $this->cost_price) * 100;
        }
        return 0;
    }

    public function getStockValueAttribute()
    {
        return $this->stock_quantity * $this->cost_price;
    }

    public function getStockStatusAttribute()
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        } elseif ($this->isLowStock()) {
            return 'low_stock';
        } elseif ($this->stock_quantity >= $this->max_stock_level) {
            return 'overstock';
        }
        return 'normal';
    }
}